<?php

namespace App\Domain\Reporting;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildTrustIndicators
{
    public function handle(bool $includeSchools): TrustIndicator
    {
        $schools = School::query()
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $empty = $this->emptyStatusCounts();

        if ($schools->isEmpty()) {
            return $this->makeIndicator($empty, 0, 0, $includeSchools ? [] : null);
        }

        $schoolIds = $schools->pluck('id');
        $statusBySchool = $this->statusCountsBySchool($schoolIds);
        $cycleCountsBySchool = $this->cycleCountsBySchool($schoolIds);

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

            $schoolRows[] = [
                'school_id' => $school->id,
                'name' => $school->name,
                ...$this->metricPayload($byStatus, $openCycles, $overdueOpenCycles),
            ];
        }

        return $this->makeIndicator(
            $totalStatus,
            $totalOpen,
            $totalOverdue,
            $includeSchools ? $schoolRows : null,
        );
    }

    /**
     * @param  array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}  $byStatus
     * @param  list<array{school_id: string, name: string, pupils_in_scope: int, by_status: array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}, gap_density: float, gaps: int, lateness_rate: float, overdue_open_cycles: int, open_cycles: int}>|null  $schools
     */
    private function makeIndicator(array $byStatus, int $openCycles, int $overdueOpenCycles, ?array $schools): TrustIndicator
    {
        $metrics = $this->metricPayload($byStatus, $openCycles, $overdueOpenCycles);

        return new TrustIndicator(
            pupilsInScope: $metrics['pupils_in_scope'],
            byStatus: $metrics['by_status'],
            gapDensity: $metrics['gap_density'],
            gaps: $metrics['gaps'],
            latenessRate: $metrics['lateness_rate'],
            overdueOpenCycles: $metrics['overdue_open_cycles'],
            openCycles: $metrics['open_cycles'],
            schools: $schools,
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

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }
}
