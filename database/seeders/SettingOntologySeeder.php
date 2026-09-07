<?php

namespace Database\Seeders;

use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\SettingTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the Pilot Setting taxonomy onto the unified published Ontology version.
 */
class SettingOntologySeeder extends Seeder
{
    /** @deprecated Use PilotOntology::VERSION_CODE */
    public const STUB_VERSION_CODE = PilotOntology::VERSION_CODE;

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
        $version = PilotOntology::ensurePublishedVersion();

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
