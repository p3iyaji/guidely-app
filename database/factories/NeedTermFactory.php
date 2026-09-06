<?php

namespace Database\Factories;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OntologyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NeedTerm>
 */
class NeedTermFactory extends Factory
{
    protected $model = NeedTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ontology_version_id' => OntologyVersion::factory()->published(),
            'code' => strtoupper(fake()->unique()->bothify('NEED-##')),
            'label' => fake()->unique()->words(3, true),
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
