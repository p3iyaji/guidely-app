<?php

namespace App\Jobs;

use App\Domain\Sre\SreEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Async SRE re-evaluation for a Pupil. Capture/submit paths enqueue this when Evidence is submitted.
 * Documentation Status / Gaps are story 4.7 — this job only wires the sync evaluator.
 */
class SreReevaluatePupil implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
        public string $pupilId,
        public string $reason,
    ) {}

    public function handle(SreEvaluator $evaluator): void
    {
        try {
            $evaluator->evaluate($this->tenantId, $this->pupilId, $this->reason);
        } catch (InvalidArgumentException $exception) {
            Log::warning('SreReevaluatePupil skipped: missing or mismatched Tenant/Pupil.', [
                'tenant_id' => $this->tenantId,
                'pupil_id' => $this->pupilId,
                'reason' => $this->reason,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('SreReevaluatePupil failed.', [
                'tenant_id' => $this->tenantId,
                'pupil_id' => $this->pupilId,
                'reason' => $this->reason,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
