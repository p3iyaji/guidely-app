<?php

namespace Database\Seeders;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds minimal Need→Provision relationship mappings onto the unified Ontology version.
 */
class RelationshipOntologySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NeedOntologySeeder::class,
            ProvisionOntologySeeder::class,
        ]);

        $version = PilotOntology::ensurePublishedVersion();

        $ci = NeedTerm::query()
            ->forVersion($version->id)
            ->where('code', 'CI')
            ->first();
        $semh = NeedTerm::query()
            ->forVersion($version->id)
            ->where('code', 'SEMH')
            ->first();
        $universal = ProvisionTerm::query()
            ->forVersion($version->id)
            ->where('code', 'UNIVERSAL')
            ->first();
        $semhSupport = ProvisionTerm::query()
            ->forVersion($version->id)
            ->where('code', 'SEMH_SUPPORT')
            ->first();

        $mappings = [
            [
                'code' => 'CI_TO_UNIVERSAL',
                'label' => 'Communication need → universal strategies',
                'relationship_type' => 'need_to_provision',
                'from_domain' => 'need',
                'from_term_id' => $ci?->id,
                'to_domain' => 'provision',
                'to_term_id' => $universal?->id,
                'sort_order' => 1,
            ],
            [
                'code' => 'SEMH_TO_SEMH_SUPPORT',
                'label' => 'SEMH need → SEMH pastoral support',
                'relationship_type' => 'need_to_provision',
                'from_domain' => 'need',
                'from_term_id' => $semh?->id,
                'to_domain' => 'provision',
                'to_term_id' => $semhSupport?->id,
                'sort_order' => 2,
            ],
        ];

        foreach ($mappings as $mapping) {
            if ($mapping['from_term_id'] === null || $mapping['to_term_id'] === null) {
                continue;
            }

            RelationshipMapping::query()->updateOrCreate(
                [
                    'ontology_version_id' => $version->id,
                    'code' => $mapping['code'],
                ],
                [
                    'label' => $mapping['label'],
                    'relationship_type' => $mapping['relationship_type'],
                    'from_domain' => $mapping['from_domain'],
                    'from_term_id' => $mapping['from_term_id'],
                    'to_domain' => $mapping['to_domain'],
                    'to_term_id' => $mapping['to_term_id'],
                    'is_active' => true,
                    'sort_order' => $mapping['sort_order'],
                ],
            );
        }
    }
}
