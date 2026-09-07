<?php

namespace Database\Seeders;

use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\PilotOntology;
use Illuminate\Database\Seeder;

/**
 * Seeds a minimal Pilot Outcome framework onto the unified Ontology version.
 */
class OutcomeOntologySeeder extends Seeder
{
    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    public const OUTCOME_TERMS = [
        [
            'code' => 'ENGAGEMENT',
            'label' => 'Engagement in learning',
            'sort_order' => 1,
        ],
        [
            'code' => 'INDEPENDENCE',
            'label' => 'Independence / self-help',
            'sort_order' => 2,
        ],
        [
            'code' => 'WELLBEING',
            'label' => 'Wellbeing / SEMH progress',
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        $version = PilotOntology::ensurePublishedVersion();

        foreach (self::OUTCOME_TERMS as $term) {
            OutcomeTerm::query()->updateOrCreate(
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
