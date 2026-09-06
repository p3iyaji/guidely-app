<?php

namespace Database\Seeders;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\SettingTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot published Ontology Setting taxonomy stub for Observations.
 */
class SettingOntologySeeder extends Seeder
{
    public const STUB_VERSION_CODE = 'pilot-setting-stub-v1';

    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    public const SETTING_TERMS = [
        [
            'code' => 'CLASSROOM',
            'label' => 'Classroom',
            'sort_order' => 1,
        ],
        [
            'code' => 'PLAYGROUND',
            'label' => 'Playground / outdoor',
            'sort_order' => 2,
        ],
        [
            'code' => 'SMALL_GROUP',
            'label' => 'Small group',
            'sort_order' => 3,
        ],
        [
            'code' => 'ONE_TO_ONE',
            'label' => '1:1',
            'sort_order' => 4,
        ],
        [
            'code' => 'TRANSITION',
            'label' => 'Transition',
            'sort_order' => 5,
        ],
        [
            'code' => 'LUNCH',
            'label' => 'Lunch / dining',
            'sort_order' => 6,
        ],
    ];

    public function run(): void
    {
        $version = OntologyVersion::query()->firstOrCreate(
            ['code' => self::STUB_VERSION_CODE],
            [
                'label' => 'Pilot Setting taxonomy stub',
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

        foreach (self::SETTING_TERMS as $term) {
            SettingTerm::query()->updateOrCreate(
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
