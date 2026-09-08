<?php

namespace App\Domain\Reporting;

class BuildTrustBenchmark
{
    /**
     * @param  list<array<string, mixed>>  $schoolRows
     * @return array{ranked_by: string, trends: list<array{month: string, lateness_rate: float, gap_density: float, pupils_in_scope: int, gaps: int, lateness_rate_delta: float|null, gap_density_delta: float|null}>, schools?: list<array{school_id: string, name: string, rank: int, gap_density: float, lateness_rate: float, pupils_in_scope: int}>}
     */
    public function payload(array $schoolRows, bool $includeSchools): array
    {
        $payload = [
            'ranked_by' => 'gap_density',
            'trends' => $this->trustTrends(),
        ];

        if ($includeSchools) {
            $payload['schools'] = $this->rankedSchools($schoolRows);
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $schoolRows
     * @return list<array{school_id: string, name: string, rank: int, gap_density: float, lateness_rate: float, pupils_in_scope: int}>
     */
    private function rankedSchools(array $schoolRows): array
    {
        $ranked = $schoolRows;
        usort(
            $ranked,
            function (array $left, array $right): int {
                $gap = ($right['gap_density'] <=> $left['gap_density']);

                if ($gap !== 0) {
                    return $gap;
                }

                $late = ($right['lateness_rate'] <=> $left['lateness_rate']);

                if ($late !== 0) {
                    return $late;
                }

                return [$left['name'], $left['school_id']] <=> [$right['name'], $right['school_id']];
            },
        );

        $rows = [];

        foreach ($ranked as $index => $school) {
            $rows[] = [
                'school_id' => $school['school_id'],
                'name' => $school['name'],
                'rank' => $index + 1,
                'gap_density' => $school['gap_density'],
                'lateness_rate' => $school['lateness_rate'],
                'pupils_in_scope' => $school['pupils_in_scope'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{month: string, lateness_rate: float, gap_density: float, pupils_in_scope: int, gaps: int, lateness_rate_delta: float|null, gap_density_delta: float|null}>
     */
    private function trustTrends(): array
    {
        $snapshots = TrustIndicatorSnapshot::query()
            ->trustTotals()
            ->orderBy('month')
            ->orderBy('id')
            ->get([
                'id',
                'month',
                'lateness_rate',
                'gap_density',
                'pupils_in_scope',
                'gaps',
            ]);

        $trends = [];
        $previous = null;

        foreach ($snapshots as $snapshot) {
            $lateness = (float) $snapshot->lateness_rate;
            $gapDensity = (float) $snapshot->gap_density;
            $trends[] = [
                'month' => $snapshot->month,
                'lateness_rate' => $lateness,
                'gap_density' => $gapDensity,
                'pupils_in_scope' => (int) $snapshot->pupils_in_scope,
                'gaps' => (int) $snapshot->gaps,
                'lateness_rate_delta' => $previous === null
                    ? null
                    : round($lateness - $previous['lateness_rate'], 6),
                'gap_density_delta' => $previous === null
                    ? null
                    : round($gapDensity - $previous['gap_density'], 6),
            ];
            $previous = ['lateness_rate' => $lateness, 'gap_density' => $gapDensity];
        }

        return $trends;
    }
}
