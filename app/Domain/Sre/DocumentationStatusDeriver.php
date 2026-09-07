<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use Illuminate\Support\Collection;

/**
 * Derives terminal Documentation Status from current Determinations / open Gaps.
 *
 * Pilot precedence: gaps > uncovered > ready > not-started.
 */
class DocumentationStatusDeriver
{
    public function __construct(private readonly GapMaterialiser $gapMaterialiser) {}

    /**
     * Persist the derived terminal status onto the Pupil (clears evaluating).
     *
     * @param  Collection<int, Determination>|null  $currentDeterminations
     */
    public function apply(Pupil $pupil, ?Collection $currentDeterminations = null): DocumentationStatus
    {
        $status = $this->derive($pupil, $currentDeterminations);

        $pupil->forceFill([
            'documentation_status' => $status,
        ])->save();

        return $status;
    }

    /**
     * @param  Collection<int, Determination>|null  $currentDeterminations
     */
    public function derive(Pupil $pupil, ?Collection $currentDeterminations = null): DocumentationStatus
    {
        $currentsProvided = $currentDeterminations !== null;

        $currents = $currentsProvided
            ? $currentDeterminations
            : Determination::withoutGlobalScope('tenant')
                ->current()
                ->forPupil($pupil->id)
                ->where('tenant_id', $pupil->tenant_id)
                ->get();

        // When currents are provided (post-materialise), derive gap presence from those
        // currents so rematerialise+derive stay consistent and ignore stale Gap rows.
        if ($currentsProvided) {
            $hasOpenGaps = $currents->contains(
                fn (Determination $row): bool => $this->gapMaterialiser->opensGap($row),
            );
        } else {
            $hasOpenGaps = Gap::withoutGlobalScope('tenant')
                ->forPupil($pupil->id)
                ->where('tenant_id', $pupil->tenant_id)
                ->open()
                ->exists();

            if (! $hasOpenGaps) {
                $hasOpenGaps = $currents->contains(
                    fn (Determination $row): bool => $this->gapMaterialiser->opensGap($row),
                );
            }
        }

        if ($hasOpenGaps) {
            return DocumentationStatus::Gaps;
        }

        if ($currents->contains(
            fn (Determination $row): bool => $row->result === DeterminationResult::Uncovered,
        )) {
            return DocumentationStatus::Uncovered;
        }

        if ($this->allFourDimensionsMet($currents)) {
            return DocumentationStatus::Ready;
        }

        return DocumentationStatus::NotStarted;
    }

    /**
     * @param  Collection<int, Determination>  $currents
     */
    private function allFourDimensionsMet(Collection $currents): bool
    {
        if ($currents->count() < count(SreDimension::cases())) {
            return false;
        }

        $byDimension = $currents->keyBy(
            fn (Determination $row): string => $row->dimension instanceof SreDimension
                ? $row->dimension->value
                : (string) $row->dimension,
        );

        foreach (SreDimension::cases() as $dimension) {
            $row = $byDimension->get($dimension->value);

            if ($row === null || $row->result !== DeterminationResult::Met) {
                return false;
            }
        }

        return true;
    }
}
