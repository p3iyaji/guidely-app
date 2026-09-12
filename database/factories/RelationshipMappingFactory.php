<?php

namespace Database\Factories;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\RelationshipMapping;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RelationshipMapping>
 */
class RelationshipMappingFactory extends Factory
{
    protected $model = RelationshipMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ontology_version_id' => fn (): string => PilotOntology::ensurePublishedVersion()->id,
            'code' => strtoupper(fake()->unique()->bothify('REL-##')),
            'label' => fake()->unique()->words(4, true),
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) Str::ulid(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function forVersion(OntologyVersion $version): static
    {
        return $this->state(fn (array $attributes) => [
            'ontology_version_id' => $version->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
