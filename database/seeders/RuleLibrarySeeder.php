<?php

namespace Database\Seeders;

use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\RuleCategory;
use App\Domain\Ontology\SreDimension;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot Rule Library stub covering FR-30 documentation/threshold and FR-31
 * escalation/review-threshold Rules across the four FR-26 dimensions.
 *
 * Partial coverage only — uncovered dimensions remain Uncovered, not success.
 */
class RuleLibrarySeeder extends Seeder
{
    /**
     * @var list<array{
     *     code: string,
     *     label: string,
     *     dimension: SreDimension,
     *     category: RuleCategory,
     *     condition: array<string, mixed>,
     *     evaluation: array<string, mixed>,
     *     outcome: array<string, mixed>,
     *     sort_order: int
     * }>
     */
    public const RULES = [
        [
            'code' => 'SEQ_DOC_INITIAL',
            'label' => 'Sequential Compliance — initial documentation sequence',
            'dimension' => SreDimension::SequentialCompliance,
            'category' => RuleCategory::Documentation,
            'condition' => [
                'all' => [
                    ['type' => 'evidence_types_present', 'types' => ['observation', 'intervention']],
                ],
            ],
            'evaluation' => [
                'type' => 'chronological_sequence',
                'requires_order' => ['observation', 'intervention'],
            ],
            'outcome' => [
                'result' => 'met',
                'gap_code' => null,
            ],
            'sort_order' => 1,
        ],
        [
            'code' => 'EVID_THR_INITIAL',
            'label' => 'Evidential Sufficiency — initial review threshold',
            'dimension' => SreDimension::EvidentialSufficiency,
            'category' => RuleCategory::Threshold,
            'condition' => [
                'all' => [
                    ['type' => 'threshold_code', 'code' => 'INITIAL_REVIEW'],
                    ['type' => 'evidence_min_count', 'min_count' => 2],
                ],
            ],
            'evaluation' => [
                'type' => 'sufficiency_check',
                'min_distinct_types' => 2,
            ],
            'outcome' => [
                'result' => 'met',
                'gap_code' => null,
            ],
            'sort_order' => 2,
        ],
        [
            'code' => 'PROP_DOC_INITIAL',
            'label' => 'Proportionality — documentation intensity match',
            'dimension' => SreDimension::Proportionality,
            'category' => RuleCategory::Documentation,
            'condition' => [
                'all' => [
                    ['type' => 'provision_and_need_linked'],
                ],
            ],
            'evaluation' => [
                'type' => 'proportionality_check',
                'compare' => ['need', 'provision'],
            ],
            'outcome' => [
                'result' => 'met',
                'gap_code' => null,
            ],
            'sort_order' => 3,
        ],
        [
            'code' => 'OUT_DOC_INITIAL',
            'label' => 'Outcome Progression — documentation of progress markers',
            'dimension' => SreDimension::OutcomeProgression,
            'category' => RuleCategory::Documentation,
            'condition' => [
                'all' => [
                    ['type' => 'evidence_type_present', 'type' => 'response'],
                ],
            ],
            'evaluation' => [
                'type' => 'outcome_marker_present',
            ],
            'outcome' => [
                'result' => 'met',
                'gap_code' => null,
            ],
            'sort_order' => 4,
        ],
        [
            'code' => 'ESC_SEQ_REVIEW',
            'label' => 'Sequential Compliance — escalation review path',
            'dimension' => SreDimension::SequentialCompliance,
            'category' => RuleCategory::Escalation,
            'condition' => [
                'all' => [
                    ['type' => 'threshold_code', 'code' => 'ESCALATION_REVIEW'],
                    ['type' => 'prior_determination', 'result' => 'unmet'],
                ],
            ],
            'evaluation' => [
                'type' => 'escalation_sequence',
                'requires' => ['review_note'],
            ],
            'outcome' => [
                'result' => 'escalated',
                'gap_code' => 'ESC_SEQ',
            ],
            'sort_order' => 5,
        ],
        [
            'code' => 'REV_THR_EVID',
            'label' => 'Evidential Sufficiency — review threshold gate',
            'dimension' => SreDimension::EvidentialSufficiency,
            'category' => RuleCategory::ReviewThreshold,
            'condition' => [
                'all' => [
                    ['type' => 'threshold_code', 'code' => 'INITIAL_REVIEW'],
                    ['type' => 'review_cycle_open'],
                ],
            ],
            'evaluation' => [
                'type' => 'review_threshold_check',
                'min_evidence_since_last_review' => 1,
            ],
            'outcome' => [
                'result' => 'review_required',
                'gap_code' => 'REV_THR',
            ],
            'sort_order' => 6,
        ],
    ];

    public function run(): void
    {
        $version = PilotRuleLibrary::ensurePublishedVersion();

        /** @var list<string> $seedCodes */
        $seedCodes = [];

        foreach (self::RULES as $rule) {
            $seedCodes[] = $rule['code'];

            Rule::query()->updateOrCreate(
                [
                    'rule_library_version_id' => $version->id,
                    'code' => $rule['code'],
                ],
                [
                    'label' => $rule['label'],
                    'dimension' => $rule['dimension'],
                    'category' => $rule['category'],
                    'condition' => $rule['condition'],
                    'evaluation' => $rule['evaluation'],
                    'outcome' => $rule['outcome'],
                    'is_active' => true,
                    'sort_order' => $rule['sort_order'],
                ],
            );
        }

        Rule::query()
            ->forVersion($version->id)
            ->whereNotIn('code', $seedCodes)
            ->update(['is_active' => false]);
    }
}
