<?php

namespace Database\Seeders;

use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ThresholdTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds a minimal Pilot Threshold Definitions set onto the unified Ontology version.
 */
class ThresholdOntologySeeder extends Seeder
{
    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    public const THRESHOLD_TERMS = [
        [
            'code' => 'INITIAL_REVIEW',
            'label' => 'Initial documentation review threshold',
            'sort_order' => 1,
        ],
        [
            'code' => 'ESCALATION_REVIEW',
            'label' => 'Escalation review threshold',
            'sort_order' => 2,
        ],
    ];

    public function run(): void
    {
        $version = PilotOntology::ensurePublishedVersion();

        foreach (self::THRESHOLD_TERMS as $term) {
            ThresholdTerm::query()->updateOrCreate(
                [
                    'ontology_version_id' => $version->id,
                    'code' => $term['code'],
                ],
                [
                    'label' => $term['label'],
                    'is_active' => true,
                    'sort_order' => $term['sort_order'],
                ],
            );
        }
    }
}
