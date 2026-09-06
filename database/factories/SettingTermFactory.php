<?php

namespace Database\Factories;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\SettingTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingTerm>
 */
class SettingTermFactory extends Factory
{
    protected $model = SettingTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ontology_version_id' => OntologyVersion::factory()->published(),
            'code' => strtoupper(fake()->unique()->bothify('SET-##')),
            'label' => fake()->unique()->words(2, true),
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
