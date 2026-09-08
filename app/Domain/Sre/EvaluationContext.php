<?php

namespace App\Domain\Sre;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Tenant;
use Illuminate\Support\Collection;

/**
 * Immutable evaluation inputs for one SRE run.
 *
 * @phpstan-type EvidenceRow array{id: string, type: string, occurred_at: string, provision_term_id: ?string}
 */
final class EvaluationContext
{
    /**
     * @param  Collection<int, EvidenceRecord>  $evidence
     * @param  Collection<int, ThresholdTerm>  $thresholdTerms
     * @param  Collection<int, RelationshipMapping>  $relationshipMappings
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Pupil $pupil,
        public readonly OntologyVersion $ontologyVersion,
        public readonly RuleLibraryVersion $ruleLibraryVersion,
        public readonly Collection $evidence,
        public readonly Collection $thresholdTerms,
        public readonly Collection $relationshipMappings,
        public readonly string $reason,
        public readonly bool $hasOpenReviewCycle,
    ) {}

    /**
     * @return list<string>
     */
    public function evidenceIds(): array
    {
        return $this->evidence->pluck('id')->values()->all();
    }

    /**
     * @return list<string>
     */
    public function evidenceTypeValues(): array
    {
        return $this->evidence
            ->map(fn (EvidenceRecord $record): string => $record->type->value)
            ->unique()
            ->values()
            ->all();
    }

    public function hasEvidenceType(EvidenceType|string $type): bool
    {
        $value = $type instanceof EvidenceType ? $type->value : $type;

        return $this->evidence->contains(
            fn (EvidenceRecord $record): bool => $record->type->value === $value,
        );
    }

    /**
     * @param  list<string>  $types
     */
    public function hasAllEvidenceTypes(array $types): bool
    {
        foreach ($types as $type) {
            if (! $this->hasEvidenceType($type)) {
                return false;
            }
        }

        return true;
    }

    public function hasActiveThresholdCode(string $code): bool
    {
        return $this->thresholdTerms->contains(
            fn (ThresholdTerm $term): bool => $term->code === $code && $term->is_active,
        );
    }

    /**
     * Pupil Need term ids (primary then secondary, non-null).
     *
     * @return list<string>
     */
    public function pupilNeedTermIds(): array
    {
        $ids = [];

        if (is_string($this->pupil->primary_need_term_id) && $this->pupil->primary_need_term_id !== '') {
            $ids[] = $this->pupil->primary_need_term_id;
        }

        if (is_string($this->pupil->secondary_need_term_id) && $this->pupil->secondary_need_term_id !== '') {
            $ids[] = $this->pupil->secondary_need_term_id;
        }

        return $ids;
    }

    /**
     * Distinct provision term ids from Intervention Evidence in the snapshot.
     *
     * @return list<string>
     */
    public function interventionProvisionTermIds(): array
    {
        return $this->evidence
            ->filter(fn (EvidenceRecord $record): bool => $record->type === EvidenceType::Intervention)
            ->map(fn (EvidenceRecord $record): ?string => $record->provision_term_id)
            ->filter(fn (?string $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * True when at least one Pupil Need maps to at least one Intervention Provision
     * via an active Need→Provision RelationshipMapping on the pinned Ontology version.
     */
    public function hasProvisionAndNeedLinked(): bool
    {
        $needIds = $this->pupilNeedTermIds();
        $provisionIds = $this->interventionProvisionTermIds();

        if ($needIds === [] || $provisionIds === []) {
            return false;
        }

        return $this->relationshipMappings->contains(function (RelationshipMapping $mapping) use ($needIds, $provisionIds): bool {
            if (! $mapping->is_active) {
                return false;
            }

            if ($mapping->relationship_type !== 'need_to_provision') {
                return false;
            }

            return in_array($mapping->from_term_id, $needIds, true)
                && in_array($mapping->to_term_id, $provisionIds, true);
        });
    }
}
