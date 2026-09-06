<?php

namespace Database\Seeders;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot published Ontology Need taxonomy stub (DfE-style areas of need).
 */
class NeedOntologySeeder extends Seeder
{
    public const STUB_VERSION_CODE = 'pilot-need-stub-v1';

    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    public const NEED_TERMS = [
        [
            'code' => 'CI',
            'label' => 'Communication and interaction',
            'sort_order' => 1,
        ],
        [
            'code' => 'CL',
            'label' => 'Cognition and learning',
            'sort_order' => 2,
        ],
        [
            'code' => 'SEMH',
            'label' => 'Social, emotional and mental health',
            'sort_order' => 3,
        ],
        [
            'code' => 'SP',
            'label' => 'Sensory and/or physical needs',
            'sort_order' => 4,
        ],
    ];

    public function run(): void
    {
        $version = OntologyVersion::query()->firstOrCreate(
            ['code' => self::STUB_VERSION_CODE],
            [
                'label' => 'Pilot Need taxonomy stub',
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

        foreach (self::NEED_TERMS as $term) {
            NeedTerm::query()->updateOrCreate(
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
