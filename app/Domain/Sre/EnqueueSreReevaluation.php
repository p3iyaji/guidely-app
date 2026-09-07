<?php

namespace App\Domain\Sre;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Jobs\SreReevaluatePupil;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared AD-17 enqueue: set Documentation Status to evaluating, then dispatch SRE job.
 */
class EnqueueSreReevaluation
{
    public function handle(Pupil $pupil, string $reason, string $logContext = 'sre'): void
    {
        $priorStatus = $pupil->documentation_status instanceof DocumentationStatus
            ? $pupil->documentation_status
            : DocumentationStatus::tryFrom((string) $pupil->documentation_status) ?? DocumentationStatus::NotStarted;

        $pupil->forceFill([
            'documentation_status' => DocumentationStatus::Evaluating,
        ])->save();

        try {
            dispatch(new SreReevaluatePupil($pupil->tenant_id, $pupil->id, $reason));
        } catch (Throwable $exception) {
            Log::warning("{$logContext}.sre_dispatch_failed", [
                'tenant_id' => $pupil->tenant_id,
                'pupil_id' => $pupil->id,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);

            $this->restoreStatusAfterDispatchFailure($pupil, $priorStatus);
        }
    }

    private function restoreStatusAfterDispatchFailure(Pupil $pupil, DocumentationStatus $priorStatus): void
    {
        $pupil->refresh();

        if ($pupil->documentation_status !== DocumentationStatus::Evaluating) {
            return;
        }

        if ($priorStatus === DocumentationStatus::Evaluating) {
            app(DocumentationStatusDeriver::class)->apply($pupil);

            return;
        }

        $pupil->forceFill([
            'documentation_status' => $priorStatus,
        ])->save();
    }
}
