<?php

namespace Database\Factories;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ThresholdTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThresholdTerm>
 */
class ThresholdTermFactory extends Factory
{
    protected $model = ThresholdTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ontology_version_id' => fn (): string => PilotOntology::ensurePublishedVersion()->id,
            'code' => strtoupper(fake()->unique()->bothify('THR-##')),
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
