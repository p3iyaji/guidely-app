<?php

namespace App\Domain\Reporting;

final readonly class TrustIndicator
{
    /**
     * @param  array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}  $byStatus
     * @param  list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>  $escalations
     * @param  list<array{school_id: string, name: string, pupils_in_scope: int, by_status: array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}, gap_density: float, gaps: int, lateness_rate: float, overdue_open_cycles: int, open_cycles: int, escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}>|null  $schools
     */
    public function __construct(
        public int $pupilsInScope,
        public array $byStatus,
        public float $gapDensity,
        public int $gaps,
        public float $latenessRate,
        public int $overdueOpenCycles,
        public int $openCycles,
        public int $escalatedPupils,
        public int $flaggedSchools,
        public array $escalations,
        public ?array $schools,
    ) {}
}
