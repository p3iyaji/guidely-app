<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stub until the SRE domain ships. Capture/submit paths enqueue this when Evidence is submitted.
 */
class SreReevaluatePupil implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
        public string $pupilId,
        public string $reason,
    ) {}

    public function handle(): void
    {
        // No-op stub — real reevaluation arrives with the SRE domain.
    }
}
