<?php

namespace Database\Seeders;

use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ProvisionTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot Provision taxonomy onto the unified published Ontology version.
 */
class ProvisionOntologySeeder extends Seeder
{
    /** @deprecated Use PilotOntology::VERSION_CODE */
    public const STUB_VERSION_CODE = PilotOntology::VERSION_CODE;

    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    public const PROVISION_TERMS = [
        [
            'code' => 'UNIVERSAL',
            'label' => 'Universal classroom strategies',
            'sort_order' => 1,
        ],
        [
            'code' => 'TARGETED_GROUP',
            'label' => 'Targeted small-group provision',
            'sort_order' => 2,
        ],
        [
            'code' => 'ONE_TO_ONE',
            'label' => '1:1 support',
            'sort_order' => 3,
        ],
        [
            'code' => 'SENSORY',
            'label' => 'Sensory / environmental adaptation',
            'sort_order' => 4,
        ],
        [
            'code' => 'LITERACY',
            'label' => 'Literacy intervention',
            'sort_order' => 5,
        ],
        [
            'code' => 'SEMH_SUPPORT',
            'label' => 'SEMH / pastoral support',
            'sort_order' => 6,
        ],
    ];

    public function run(): void
    {
        $version = PilotOntology::ensurePublishedVersion();

        foreach (self::PROVISION_TERMS as $term) {
            ProvisionTerm::query()->updateOrCreate(
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
