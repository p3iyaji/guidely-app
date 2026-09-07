<?php

namespace App\Domain\Sre;

use App\Domain\Pupils\Pupil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites open Gaps for a Pupil from current Determinations (Domain\Sre sole writer).
 */
class GapMaterialiser
{
    /**
     * Determination results that open a Gap (Pilot policy).
     *
     * @var list<DeterminationResult>
     */
    public const GAP_OPENING_RESULTS = [
        DeterminationResult::Unmet,
        DeterminationResult::Insufficient,
        DeterminationResult::Escalated,
        DeterminationResult::ReviewRequired,
    ];

    /**
     * @param  Collection<int, Determination>|null  $currentDeterminations
     * @return Collection<int, Gap>
     */
    public function materialise(Pupil $pupil, ?Collection $currentDeterminations = null): Collection
    {
        $currents = $currentDeterminations ?? Determination::withoutGlobalScope('tenant')
            ->current()
            ->forPupil($pupil->id)
            ->where('tenant_id', $pupil->tenant_id)
            ->get();

        return DB::transaction(function () use ($pupil, $currents): Collection {
            Gap::withoutGlobalScope('tenant')
                ->forPupil($pupil->id)
                ->where('tenant_id', $pupil->tenant_id)
                ->open()
                ->delete();

            $created = collect();

            foreach ($currents as $determination) {
                if (! $this->opensGap($determination)) {
                    continue;
                }

                $gap = new Gap([
                    'tenant_id' => $pupil->tenant_id,
                    'pupil_id' => $pupil->id,
                ]);
                $gap->forceFill([
                    'determination_id' => $determination->id,
                    'dimension' => $determination->dimension,
                    'result' => $determination->result,
                    'is_open' => true,
                ])->save();

                $created->push($gap);
            }

            return $created;
        });
    }

    public function opensGap(Determination $determination): bool
    {
        $result = $determination->result;

        if (! $result instanceof DeterminationResult) {
            return false;
        }

        return in_array($result, self::GAP_OPENING_RESULTS, true);
    }
}
