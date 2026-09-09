<?php

namespace App\Domain\Reporting;

final readonly class DashboardSummary
{
    public function __construct(
        public ?int $pupilsInScope,
        public ?int $openGaps,
        public ?int $reviewCyclesDue,
        public ?int $drafts,
        public int $windowDays,
    ) {}
}
