<?php

namespace App\Domain\Reporting;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;

class SnapshotTrustIndicators
{
    public function __construct(private BuildTrustIndicators $build) {}

    public function handle(Tenant $tenant, ?string $month = null): int
    {
        $month ??= now('Europe/London')->format('Y-m');

        return CurrentTenant::using($tenant->id, function () use ($month): int {
            $indicator = $this->build->handle(includeSchools: true, includeBenchmark: false);
            $written = 0;
            $written += $this->upsertRow(null, $month, [
                'pupils_in_scope' => $indicator->pupilsInScope,
                'gaps' => $indicator->gaps,
                'gap_density' => $indicator->gapDensity,
                'lateness_rate' => $indicator->latenessRate,
                'overdue_open_cycles' => $indicator->overdueOpenCycles,
                'open_cycles' => $indicator->openCycles,
            ]);

            foreach ($indicator->schools ?? [] as $school) {
                $written += $this->upsertRow($school['school_id'], $month, [
                    'pupils_in_scope' => $school['pupils_in_scope'],
                    'gaps' => $school['gaps'],
                    'gap_density' => $school['gap_density'],
                    'lateness_rate' => $school['lateness_rate'],
                    'overdue_open_cycles' => $school['overdue_open_cycles'],
                    'open_cycles' => $school['open_cycles'],
                ]);
            }

            return $written;
        });
    }

    /**
     * @param  array{pupils_in_scope: int, gaps: int, gap_density: float, lateness_rate: float, overdue_open_cycles: int, open_cycles: int}  $metrics
     */
    private function upsertRow(?string $schoolId, string $month, array $metrics): int
    {
        $query = TrustIndicatorSnapshot::query()->where('month', $month);

        if ($schoolId === null) {
            $query->whereNull('school_id');
        } else {
            $query->where('school_id', $schoolId);
        }

        $snapshot = $query->first() ?? new TrustIndicatorSnapshot;
        $snapshot->forceFill([
            'school_id' => $schoolId,
            'month' => $month,
            'snapshot_key' => $month.':'.($schoolId ?? 'trust'),
            ...$metrics,
        ])->save();

        return 1;
    }
}
