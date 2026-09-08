<?php

namespace App\Domain\Reporting;

use App\Domain\Ontology\RuleCategory;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Tenancy\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildTrustIndicators
{
    public function __construct(private BuildTrustBenchmark $benchmark) {}

    public function handle(bool $includeSchools, bool $includeBenchmark = false): TrustIndicator
    {
        $schools = School::query()
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $empty = $this->emptyStatusCounts();

        if ($schools->isEmpty()) {
            return $this->makeIndicator(
                $empty,
                0,
                0,
                $this->emptyEscalationSummary(),
                $includeSchools ? [] : null,
                $includeBenchmark ? $this->benchmark->payload([], $includeSchools) : null,
            );
        }

        $schoolIds = $schools->pluck('id');
        $statusBySchool = $this->statusCountsBySchool($schoolIds);
        $cycleCountsBySchool = $this->cycleCountsBySchool($schoolIds);
        $escalationsBySchool = $this->escalationsBySchool($schoolIds);

        $totalStatus = $empty;
        $totalOpen = 0;
        $totalOverdue = 0;
        $schoolRows = [];

        foreach ($schools as $school) {
            $byStatus = $empty;
            $schoolStatus = $statusBySchool[$school->id] ?? [];

            foreach ($schoolStatus as $status => $count) {
                if (! array_key_exists($status, $byStatus)) {
                    continue;
                }

                $byStatus[$status] = $count;
                $totalStatus[$status] += $count;
            }

            $openCycles = $cycleCountsBySchool[$school->id]['open'] ?? 0;
            $overdueOpenCycles = $cycleCountsBySchool[$school->id]['overdue'] ?? 0;
            $totalOpen += $openCycles;
            $totalOverdue += $overdueOpenCycles;
            $escalation = $escalationsBySchool[$school->id] ?? $this->emptyEscalationSummary();

            $schoolRows[] = [
                'school_id' => $school->id,
                'name' => $school->name,
                ...$this->metricPayload($byStatus, $openCycles, $overdueOpenCycles),
                ...$escalation,
            ];
        }

        return $this->makeIndicator(
            $totalStatus,
            $totalOpen,
            $totalOverdue,
            $this->tenantEscalationSummary($escalationsBySchool),
            $includeSchools ? $schoolRows : null,
            $includeBenchmark ? $this->benchmark->payload($schoolRows, $includeSchools) : null,
        );
    }

    /**
     * @param  array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}  $byStatus
     * @param  array{escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}  $escalation
     * @param  list<array<string, mixed>>|null  $schools
     * @param  array<string, mixed>|null  $benchmark
     */
    private function makeIndicator(
        array $byStatus,
        int $openCycles,
        int $overdueOpenCycles,
        array $escalation,
        ?array $schools,
        ?array $benchmark,
    ): TrustIndicator {
        $metrics = $this->metricPayload($byStatus, $openCycles, $overdueOpenCycles);

        return new TrustIndicator(
            pupilsInScope: $metrics['pupils_in_scope'],
            byStatus: $metrics['by_status'],
            gapDensity: $metrics['gap_density'],
            gaps: $metrics['gaps'],
            latenessRate: $metrics['lateness_rate'],
            overdueOpenCycles: $metrics['overdue_open_cycles'],
            openCycles: $metrics['open_cycles'],
            escalatedPupils: $escalation['escalated_pupils'],
            flaggedSchools: $escalation['flagged_schools'],
            escalations: $escalation['escalations'],
            schools: $schools,
            benchmark: $benchmark,
        );
    }

    /**
     * @param  array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}  $byStatus
     * @return array{pupils_in_scope: int, by_status: array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}, gap_density: float, gaps: int, lateness_rate: float, overdue_open_cycles: int, open_cycles: int}
     */
    private function metricPayload(array $byStatus, int $openCycles, int $overdueOpenCycles): array
    {
        $pupilsInScope = array_sum($byStatus);
        $gaps = $byStatus[DocumentationStatus::Gaps->value];

        return [
            'pupils_in_scope' => $pupilsInScope,
            'by_status' => $byStatus,
            'gap_density' => $this->rate($gaps, $pupilsInScope),
            'gaps' => $gaps,
            'lateness_rate' => $this->rate($overdueOpenCycles, $openCycles),
            'overdue_open_cycles' => $overdueOpenCycles,
            'open_cycles' => $openCycles,
        ];
    }

    /**
     * @return array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}
     */
    private function emptyStatusCounts(): array
    {
        return [
            DocumentationStatus::Ready->value => 0,
            DocumentationStatus::Gaps->value => 0,
            DocumentationStatus::Uncovered->value => 0,
            DocumentationStatus::NotStarted->value => 0,
            DocumentationStatus::Evaluating->value => 0,
        ];
    }

    /**
     * @param  Collection<int, string>  $schoolIds
     * @return array<string, array<string, int>>
     */
    private function statusCountsBySchool(Collection $schoolIds): array
    {
        $rows = Pupil::query()
            ->whereIn('school_id', $schoolIds)
            ->toBase()
            ->selectRaw('school_id, documentation_status as status, count(*) as aggregate')
            ->groupBy('school_id', 'documentation_status')
            ->get();

        $bySchool = [];

        foreach ($rows as $row) {
            $bySchool[(string) $row->school_id][(string) $row->status] = (int) $row->aggregate;
        }

        return $bySchool;
    }

    /**
     * @param  Collection<int, string>  $schoolIds
     * @return array<string, array{open: int, overdue: int}>
     */
    private function cycleCountsBySchool(Collection $schoolIds): array
    {
        $today = now('Europe/London')->toDateString();

        $cycles = ReviewCycle::query()
            ->open()
            ->with('pupil:id,school_id')
            ->whereHas('pupil', function (Builder $pupils) use ($schoolIds): void {
                $pupils->whereIn('school_id', $schoolIds);
            })
            ->get(['id', 'pupil_id', 'due_on']);

        $counts = [];

        foreach ($cycles as $cycle) {
            $schoolId = $cycle->pupil?->school_id;

            if (! is_string($schoolId) || $schoolId === '') {
                continue;
            }

            $counts[$schoolId] ??= ['open' => 0, 'overdue' => 0];
            $counts[$schoolId]['open']++;

            $dueOn = $cycle->due_on?->toDateString();

            if (is_string($dueOn) && $dueOn < $today) {
                $counts[$schoolId]['overdue']++;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, string>  $schoolIds
     * @return array<string, array{escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}>
     */
    private function escalationsBySchool(Collection $schoolIds): array
    {
        $determinations = Determination::query()
            ->current()
            ->where('result', DeterminationResult::Escalated)
            ->whereHas(
                'rule',
                function (Builder $rules): void {
                    $rules->where('category', RuleCategory::Escalation);
                },
            )
            ->whereHas(
                'pupil',
                function (Builder $pupils) use ($schoolIds): void {
                    $pupils->whereIn('school_id', $schoolIds);
                },
            )
            ->with([
                'rule:id,code,label,category,rule_library_version_id',
                'pupil:id,school_id',
            ])
            ->get(['id', 'pupil_id', 'rule_id', 'result']);

        $grouped = [];

        foreach ($determinations as $determination) {
            $schoolId = $determination->pupil?->school_id;
            $rule = $determination->rule;

            if (! is_string($schoolId) || $schoolId === '' || $rule === null) {
                continue;
            }

            $ruleId = $rule->id;
            $grouped[$schoolId][$ruleId] ??= [
                'rule_id' => $ruleId,
                'rule_code' => $rule->code,
                'rule_label' => $rule->label,
                'pupil_ids' => [],
            ];
            $grouped[$schoolId][$ruleId]['pupil_ids'][$determination->pupil_id] = true;
        }

        $bySchool = [];

        foreach ($grouped as $schoolId => $rules) {
            $pupilIds = [];
            $escalations = [];

            foreach ($rules as $rule) {
                $rulePupilIds = array_keys($rule['pupil_ids']);
                foreach ($rulePupilIds as $pupilId) {
                    $pupilIds[$pupilId] = true;
                }

                $escalations[] = [
                    'rule_id' => $rule['rule_id'],
                    'rule_code' => $rule['rule_code'],
                    'rule_label' => $rule['rule_label'],
                    'pupil_count' => count($rulePupilIds),
                ];
            }

            usort(
                $escalations,
                fn (array $left, array $right): int => [$left['rule_code'], $left['rule_id']] <=> [$right['rule_code'], $right['rule_id']],
            );

            $escalatedPupils = count($pupilIds);
            $bySchool[$schoolId] = [
                'escalated_pupils' => $escalatedPupils,
                'flagged_schools' => $escalatedPupils > 0 ? 1 : 0,
                'escalations' => $escalations,
            ];
        }

        return $bySchool;
    }

    /**
     * @param  array<string, array{escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}>  $escalationsBySchool
     * @return array{escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}
     */
    private function tenantEscalationSummary(array $escalationsBySchool): array
    {
        $flaggedSchools = 0;
        $pupilTotal = 0;
        $byRule = [];

        foreach ($escalationsBySchool as $summary) {
            if ($summary['escalated_pupils'] > 0) {
                $flaggedSchools++;
            }

            $pupilTotal += $summary['escalated_pupils'];

            foreach ($summary['escalations'] as $escalation) {
                $ruleId = $escalation['rule_id'];
                $byRule[$ruleId] ??= [
                    'rule_id' => $ruleId,
                    'rule_code' => $escalation['rule_code'],
                    'rule_label' => $escalation['rule_label'],
                    'pupil_count' => 0,
                ];
                $byRule[$ruleId]['pupil_count'] += $escalation['pupil_count'];
            }
        }

        $escalations = array_values($byRule);
        usort(
            $escalations,
            fn (array $left, array $right): int => [$left['rule_code'], $left['rule_id']] <=> [$right['rule_code'], $right['rule_id']],
        );

        return [
            'escalated_pupils' => $pupilTotal,
            'flagged_schools' => $flaggedSchools,
            'escalations' => $escalations,
        ];
    }

    /**
     * @return array{escalated_pupils: int, flagged_schools: int, escalations: list<array{rule_id: string, rule_code: string, rule_label: string, pupil_count: int}>}
     */
    private function emptyEscalationSummary(): array
    {
        return [
            'escalated_pupils' => 0,
            'flagged_schools' => 0,
            'escalations' => [],
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }
}
