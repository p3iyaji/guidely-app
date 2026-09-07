<?php

namespace App\Jobs;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\DocumentationStatusDeriver;
use App\Domain\Sre\GapMaterialiser;
use App\Domain\Sre\SreEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Async SRE re-evaluation for a Pupil. Capture/submit paths enqueue this when Evidence is submitted.
 * After evaluate: materialise Gaps and derive terminal Documentation Status (story 4.7).
 */
class SreReevaluatePupil implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
        public string $pupilId,
        public string $reason,
    ) {}

    public function handle(
        SreEvaluator $evaluator,
        GapMaterialiser $gapMaterialiser,
        DocumentationStatusDeriver $statusDeriver,
    ): void {
        try {
            $determinations = $evaluator->evaluate($this->tenantId, $this->pupilId, $this->reason);

            $pupil = Pupil::withoutGlobalScope('tenant')->find($this->pupilId);

            if ($pupil === null || $pupil->tenant_id !== $this->tenantId) {
                // Evaluate succeeded but follow-up reload failed / mismatched — clear Evaluating
                // for the job's pupil id when that pupil exists.
                $this->clearEvaluatingFromPriorState();

                return;
            }

            $gapMaterialiser->materialise($pupil, $determinations);
            $statusDeriver->apply($pupil, $determinations);
        } catch (InvalidArgumentException $exception) {
            Log::warning('SreReevaluatePupil skipped: missing or mismatched Tenant/Pupil.', [
                'tenant_id' => $this->tenantId,
                'pupil_id' => $this->pupilId,
                'reason' => $this->reason,
                'message' => $exception->getMessage(),
            ]);

            $this->clearEvaluatingFromPriorState();
        } catch (Throwable $exception) {
            Log::error('SreReevaluatePupil failed.', [
                'tenant_id' => $this->tenantId,
                'pupil_id' => $this->pupilId,
                'reason' => $this->reason,
                'message' => $exception->getMessage(),
            ]);

            $this->clearEvaluatingFromPriorState();

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $pupil = Pupil::withoutGlobalScope('tenant')->find($this->pupilId);

        if ($pupil === null || $pupil->documentation_status !== DocumentationStatus::Evaluating) {
            return;
        }

        $this->clearEvaluatingFromPriorState();
    }

    /**
     * Do not leave the Pupil stuck in evaluating after skip/failure.
     * Clears Evaluating for the job's pupil id when that pupil exists (tenant mismatch included).
     * Recompute Gaps + terminal status from last current Determinations.
     */
    private function clearEvaluatingFromPriorState(): void
    {
        $pupil = Pupil::withoutGlobalScope('tenant')->find($this->pupilId);

        if ($pupil === null) {
            return;
        }

        if ($pupil->documentation_status !== DocumentationStatus::Evaluating) {
            return;
        }

        app(GapMaterialiser::class)->materialise($pupil);
        app(DocumentationStatusDeriver::class)->apply($pupil);
    }
}
