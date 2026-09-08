<?php

namespace App\Domain\Sre;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\EffectiveRuleLibraryVersion;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\SreDimension;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pure in-process SRE evaluator (AD-4 / AD-15).
 *
 * (Evidence snapshot, Ontology version, Rule Library version) → Determinations + pathways.
 * No HTTP/UI imports; no generative models.
 *
 * Concurrent re-evals: writes run in a transaction and lock existing current rows for the
 * pupil (tenant-scoped) before superseding. A partial unique index on
 * (pupil_id, dimension) WHERE is_current may come later if SQLite Pilot limits allow.
 */
class SreEvaluator
{
    public function __construct(
        private readonly EffectiveOntologyVersion $effectiveOntologyVersion,
        private readonly EffectiveRuleLibraryVersion $effectiveRuleLibraryVersion,
        private readonly RuleConditionInterpreter $conditionInterpreter,
        private readonly RuleEvaluationInterpreter $evaluationInterpreter,
    ) {}

    /**
     * Evaluate all four FR-26 dimensions for a Pupil and persist current Determinations.
     *
     * @return Collection<int, Determination>
     */
    public function evaluate(string $tenantId, string $pupilId, string $reason = 'manual'): Collection
    {
        $tenant = Tenant::query()->find($tenantId);
        $pupil = Pupil::withoutGlobalScope('tenant')->find($pupilId);

        if ($tenant === null || $pupil === null) {
            throw new InvalidArgumentException(
                'SreEvaluator requires an existing Tenant and Pupil.',
            );
        }

        if ($pupil->tenant_id !== $tenant->id) {
            throw new InvalidArgumentException(
                'SreEvaluator Pupil does not belong to the given Tenant.',
            );
        }

        $ontologyVersion = $this->effectiveOntologyVersion->resolve($tenant);
        $ruleLibraryVersion = $this->effectiveRuleLibraryVersion->resolve($tenant);

        if ($ontologyVersion === null || $ruleLibraryVersion === null) {
            throw new RuntimeException(
                'SreEvaluator could not pin Ontology and Rule Library versions for the Tenant.',
            );
        }

        $evidence = EvidenceRecord::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('pupil_id', $pupil->id)
            ->where('lifecycle', EvidenceLifecycle::Submitted)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $thresholdTerms = ThresholdTerm::query()
            ->forVersion($ontologyVersion->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $relationshipMappings = RelationshipMapping::query()
            ->forVersion($ontologyVersion->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rules = Rule::query()
            ->forVersion($ruleLibraryVersion->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $hasOpenReviewCycle = ReviewCycle::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('pupil_id', $pupil->id)
            ->open()
            ->exists();

        $context = new EvaluationContext(
            tenant: $tenant,
            pupil: $pupil,
            ontologyVersion: $ontologyVersion,
            ruleLibraryVersion: $ruleLibraryVersion,
            evidence: $evidence,
            thresholdTerms: $thresholdTerms,
            relationshipMappings: $relationshipMappings,
            reason: $reason,
            hasOpenReviewCycle: $hasOpenReviewCycle,
        );

        $evaluatedAt = now();

        return DB::transaction(function () use ($context, $rules, $evaluatedAt): Collection {
            // Serialize concurrent SRE writes for this pupil. Partial unique index deferred
            // (SQLite Pilot may not support partial unique indexes cleanly).
            Determination::withoutGlobalScope('tenant')
                ->where('tenant_id', $context->tenant->id)
                ->where('pupil_id', $context->pupil->id)
                ->current()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $written = collect();

            foreach (SreDimension::cases() as $dimension) {
                $draft = $this->evaluateDimension($dimension, $context, $rules);

                Determination::withoutGlobalScope('tenant')
                    ->where('tenant_id', $context->tenant->id)
                    ->forPupil($context->pupil->id)
                    ->forDimension($dimension)
                    ->current()
                    ->update(['is_current' => false]);

                $determination = new Determination;
                $determination->forceFill([
                    'tenant_id' => $context->tenant->id,
                    'pupil_id' => $context->pupil->id,
                    'dimension' => $dimension,
                    'result' => $draft['result'],
                    'rule_id' => $draft['rule_id'],
                    'reasoning_pathway' => $draft['reasoning_pathway'],
                    'is_current' => true,
                    'evaluated_at' => $evaluatedAt,
                    'ontology_version_id' => $context->ontologyVersion->id,
                    'rule_library_version_id' => $context->ruleLibraryVersion->id,
                ])->save();

                $written->push($determination);
            }

            return $written;
        });
    }

    /**
     * @param  Collection<int, Rule>  $rules
     * @return array{
     *     result: DeterminationResult,
     *     rule_id: ?string,
     *     reasoning_pathway: array<string, mixed>
     * }
     */
    private function evaluateDimension(
        SreDimension $dimension,
        EvaluationContext $context,
        Collection $rules,
    ): array {
        $evidenceIds = $context->evidenceIds();

        if ($context->evidence->isEmpty()) {
            return [
                'result' => DeterminationResult::Insufficient,
                'rule_id' => null,
                'reasoning_pathway' => $this->pathway(
                    dimension: $dimension,
                    result: DeterminationResult::Insufficient,
                    rule: null,
                    ontologyVersionId: $context->ontologyVersion->id,
                    ruleLibraryVersionId: $context->ruleLibraryVersion->id,
                    conditionSteps: [[
                        'type' => 'evidence_snapshot',
                        'status' => 'failed',
                        'detail' => 'Zero submitted Evidence; result is insufficient (not met).',
                    ]],
                    evaluationSteps: [],
                    evidenceIds: [],
                    notes: ['zero_evidence' => true],
                ),
            ];
        }

        $dimensionRules = $rules
            ->filter(fn (Rule $rule): bool => $rule->dimension === $dimension)
            ->values();

        $skippedPathways = [];

        foreach ($dimensionRules as $rule) {
            $condition = is_array($rule->condition) ? $rule->condition : [];
            $conditionResult = $this->conditionInterpreter->interpret($condition, $context);

            if (! $conditionResult['applicable']) {
                $skippedPathways[] = [
                    'rule' => $this->ruleCitation($rule, $context->ruleLibraryVersion->id),
                    'condition_steps' => $conditionResult['steps'],
                    'reason' => 'condition_not_applicable',
                ];

                continue;
            }

            $evaluation = is_array($rule->evaluation) ? $rule->evaluation : [];
            $evaluationResult = $this->evaluationInterpreter->interpret($evaluation, $context);

            if (! $evaluationResult['applicable']) {
                $skippedPathways[] = [
                    'rule' => $this->ruleCitation($rule, $context->ruleLibraryVersion->id),
                    'condition_steps' => $conditionResult['steps'],
                    'evaluation_steps' => $evaluationResult['steps'],
                    'reason' => 'evaluation_unsupported',
                ];

                continue;
            }

            if ($evaluationResult['passed']) {
                $result = $this->resultFromRuleOutcome($rule);

                if ($result === null) {
                    $skippedPathways[] = [
                        'rule' => $this->ruleCitation($rule, $context->ruleLibraryVersion->id),
                        'condition_steps' => $conditionResult['steps'],
                        'evaluation_steps' => $evaluationResult['steps'],
                        'reason' => 'invalid_or_missing_outcome',
                    ];

                    continue;
                }
            } else {
                $result = DeterminationResult::Unmet;
            }

            return [
                'result' => $result,
                'rule_id' => $rule->id,
                'reasoning_pathway' => $this->pathway(
                    dimension: $dimension,
                    result: $result,
                    rule: $rule,
                    ontologyVersionId: $context->ontologyVersion->id,
                    ruleLibraryVersionId: $context->ruleLibraryVersion->id,
                    conditionSteps: $conditionResult['steps'],
                    evaluationSteps: $evaluationResult['steps'],
                    evidenceIds: $evidenceIds,
                    notes: [
                        'prior_skipped_rules' => $skippedPathways,
                    ],
                ),
            ];
        }

        return [
            'result' => DeterminationResult::Uncovered,
            'rule_id' => null,
            'reasoning_pathway' => $this->pathway(
                dimension: $dimension,
                result: DeterminationResult::Uncovered,
                rule: null,
                ontologyVersionId: $context->ontologyVersion->id,
                ruleLibraryVersionId: $context->ruleLibraryVersion->id,
                conditionSteps: [[
                    'type' => 'applicable_rule',
                    'status' => 'failed',
                    'detail' => 'No applicable Rule for this dimension.',
                ]],
                evaluationSteps: [],
                evidenceIds: $evidenceIds,
                notes: [
                    'no_applicable_rule' => true,
                    'skipped_rules' => $skippedPathways,
                ],
            ),
        ];
    }

    /**
     * Map Rule outcome.result onto a DeterminationResult. Unknown/missing → null (Rule non-applicable).
     * Never silently defaults to Met.
     */
    private function resultFromRuleOutcome(Rule $rule): ?DeterminationResult
    {
        $outcome = is_array($rule->outcome) ? $rule->outcome : [];
        $raw = is_string($outcome['result'] ?? null) ? $outcome['result'] : null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return DeterminationResult::fromRuleOutcome($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $conditionSteps
     * @param  list<array<string, mixed>>  $evaluationSteps
     * @param  list<string>  $evidenceIds
     * @param  array<string, mixed>  $notes
     * @return array<string, mixed>
     */
    private function pathway(
        SreDimension $dimension,
        DeterminationResult $result,
        ?Rule $rule,
        string $ontologyVersionId,
        string $ruleLibraryVersionId,
        array $conditionSteps,
        array $evaluationSteps,
        array $evidenceIds,
        array $notes = [],
    ): array {
        return [
            'dimension' => $dimension->value,
            'ontology_version_id' => $ontologyVersionId,
            'rule_library_version_id' => $ruleLibraryVersionId,
            'rule' => $rule === null ? null : $this->ruleCitation($rule, $ruleLibraryVersionId),
            'condition_steps' => $conditionSteps,
            'evaluation_steps' => $evaluationSteps,
            'evidence_ids' => $evidenceIds,
            'result' => $result->value,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{id: string, code: string, label: string, version_id: string}
     */
    private function ruleCitation(Rule $rule, string $ruleLibraryVersionId): array
    {
        return [
            'id' => $rule->id,
            'code' => $rule->code,
            'label' => $rule->label,
            'version_id' => $ruleLibraryVersionId,
        ];
    }
}
