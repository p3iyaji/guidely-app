<?php

namespace Database\Seeders;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\ProvisionTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot published Ontology Provision taxonomy stub for Interventions.
 */
class ProvisionOntologySeeder extends Seeder
{
    public const STUB_VERSION_CODE = 'pilot-provision-stub-v1';

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
        $version = OntologyVersion::query()->firstOrCreate(
            ['code' => self::STUB_VERSION_CODE],
            [
                'label' => 'Pilot Provision taxonomy stub',
                'status' => OntologyVersionStatus::Published,
                'published_at' => now(),
            ],
        );

        if ($version->status !== OntologyVersionStatus::Published) {
            $version->forceFill([
                'status' => OntologyVersionStatus::Published,
                'published_at' => $version->published_at ?? now(),
            ])->save();
        }

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
