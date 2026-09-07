<?php

namespace App\Domain\Outputs;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Determination;
use App\Domain\Sre\Gap;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BuildDocumentationOutput
{
    /**
     * Assemble a citation-only payload from current Determinations, submitted Evidence, and open Gaps.
     *
     * @return array<string, mixed>
     */
    public function handle(Pupil $pupil, ReviewCycle $cycle, DocumentationOutputType $type): array
    {
        if ($cycle->pupil_id !== $pupil->id) {
            throw new InvalidArgumentException('Review Cycle does not belong to the Pupil.');
        }

        $pupil->loadMissing(['primaryNeedTerm', 'secondaryNeedTerm']);

        $determinations = Determination::query()
            ->current()
            ->forPupil($pupil->id)
            ->orderBy('id')
            ->get();

        $evidence = EvidenceRecord::query()
            ->where('pupil_id', $pupil->id)
            ->where('lifecycle', EvidenceLifecycle::Submitted)
            ->with(['provisionTerm', 'relatedIntervention.provisionTerm'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $gaps = Gap::query()
            ->open()
            ->forPupil($pupil->id)
            ->orderBy('id')
            ->get();

        $submittedIds = $evidence->modelKeys();

        return match ($type) {
            DocumentationOutputType::ReviewSummary => $this->reviewSummary(
                $determinations,
                $submittedIds,
                $gaps,
            ),
            DocumentationOutputType::EhcpPack => $this->ehcpPack(
                $pupil,
                $determinations,
                $evidence,
                $gaps,
            ),
        };
    }

    /**
     * @param  Collection<int, Determination>  $determinations
     * @param  list<string>  $submittedIds
     * @param  Collection<int, Gap>  $gaps
     * @return array<string, mixed>
     */
    private function reviewSummary(Collection $determinations, array $submittedIds, Collection $gaps): array
    {
        $submittedLookup = array_fill_keys($submittedIds, true);
        $evidenceIds = [];

        foreach ($determinations as $determination) {
            $pathway = $determination->reasoning_pathway;

            if (! is_array($pathway)) {
                continue;
            }

            $pathwayIds = $pathway['evidence_ids'] ?? [];

            if (! is_array($pathwayIds)) {
                continue;
            }

            foreach ($pathwayIds as $evidenceId) {
                if (! is_string($evidenceId) || $evidenceId === '') {
                    continue;
                }

                if (! isset($submittedLookup[$evidenceId])) {
                    continue;
                }

                $evidenceIds[] = $evidenceId;
            }
        }

        return [
            'kind' => DocumentationOutputType::ReviewSummary->value,
            'determinations' => $determinations
                ->map(fn (Determination $determination): array => [
                    'id' => $determination->id,
                    'dimension' => $determination->dimension?->value,
                    'result' => $determination->result?->value,
                ])
                ->values()
                ->all(),
            'determination_ids' => $determinations->modelKeys(),
            'evidence_ids' => array_values(array_unique($evidenceIds)),
            'gaps' => $gaps
                ->map(fn (Gap $gap): array => [
                    'id' => $gap->id,
                    'dimension' => $gap->dimension?->value,
                ])
                ->values()
                ->all(),
            'gap_ids' => $gaps->modelKeys(),
        ];
    }

    /**
     * @param  Collection<int, Determination>  $determinations
     * @param  Collection<int, EvidenceRecord>  $evidence
     * @param  Collection<int, Gap>  $gaps
     * @return array<string, mixed>
     */
    private function ehcpPack(
        Pupil $pupil,
        Collection $determinations,
        Collection $evidence,
        Collection $gaps,
    ): array {
        $needs = $this->presentNeeds($pupil);
        $provisions = $this->presentProvisions($evidence);
        $outcomes = $this->presentOutcomes($evidence);
        $gapIds = $gaps->modelKeys();

        $present = [
            'need' => $needs,
            'provision' => $provisions,
            'outcome' => $outcomes,
        ];

        $absent = [];

        foreach (['need', 'provision', 'outcome'] as $domain) {
            if ($present[$domain] !== []) {
                continue;
            }

            $absent[] = [
                'domain' => $domain,
                'gap_ids' => $gapIds,
            ];
        }

        $evidenceIds = array_values(array_unique([
            ...array_column($provisions, 'evidence_id'),
            ...array_column($outcomes, 'evidence_id'),
        ]));

        return [
            'kind' => DocumentationOutputType::EhcpPack->value,
            'present' => $present,
            'absent' => $absent,
            'determination_ids' => $determinations->modelKeys(),
            'evidence_ids' => $evidenceIds,
            'gap_ids' => $gapIds,
        ];
    }

    /**
     * @return list<array{id: string, code: string, label: string, notes: ?string}>
     */
    private function presentNeeds(Pupil $pupil): array
    {
        $needs = [];

        foreach ([$pupil->primaryNeedTerm, $pupil->secondaryNeedTerm] as $index => $term) {
            if (! $term instanceof NeedTerm) {
                continue;
            }

            $notes = $index === 0 ? $pupil->primary_need_notes : $pupil->secondary_need_notes;

            $needs[] = [
                'id' => $term->id,
                'code' => $term->code,
                'label' => $term->label,
                'notes' => $notes,
            ];
        }

        return $needs;
    }

    /**
     * @param  Collection<int, EvidenceRecord>  $evidence
     * @return list<array{evidence_id: string, provision: array{id: string, code: string, label: string}}>
     */
    private function presentProvisions(Collection $evidence): array
    {
        $provisions = [];

        foreach ($evidence as $record) {
            if ($record->type !== EvidenceType::Intervention) {
                continue;
            }

            $term = $record->provisionTerm;

            if (! $term instanceof ProvisionTerm) {
                continue;
            }

            $provisions[] = [
                'evidence_id' => $record->id,
                'provision' => [
                    'id' => $term->id,
                    'code' => $term->code,
                    'label' => $term->label,
                ],
            ];
        }

        return $provisions;
    }

    /**
     * @param  Collection<int, EvidenceRecord>  $evidence
     * @return list<array{evidence_id: string, related_intervention_id: ?string}>
     */
    private function presentOutcomes(Collection $evidence): array
    {
        $submittedLookup = array_fill_keys($evidence->modelKeys(), true);
        $outcomes = [];

        foreach ($evidence as $record) {
            if ($record->type !== EvidenceType::Response) {
                continue;
            }

            $relatedId = $record->related_intervention_id;

            if ($relatedId !== null && ! isset($submittedLookup[$relatedId])) {
                $relatedId = null;
            }

            $outcomes[] = [
                'evidence_id' => $record->id,
                'related_intervention_id' => $relatedId,
            ];
        }

        return $outcomes;
    }
}
