<?php

namespace App\Domain\Reporting;

final readonly class SchoolReport
{
    /**
     * @param  array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}  $byStatus
     * @param  list<array{id: string, given_name: string, family_name: string, documentation_status: string}>  $pupils
     * @param  list<array{id: string, given_name: string, family_name: string, due_on: string, type_label: string}>  $cyclesDue
     */
    public function __construct(
        public int $pupilsInScope,
        public array $byStatus,
        public int $ready,
        public int $gaps,
        public int $reviewCyclesDue,
        public int $windowDays,
        public array $pupils,
        public array $cyclesDue,
    ) {}
}
