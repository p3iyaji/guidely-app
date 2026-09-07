<?php

namespace Database\Factories;

use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\RuleLibraryVersionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleLibraryVersion>
 */
class RuleLibraryVersionFactory extends Factory
{
    protected $model = RuleLibraryVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'rl-stub-'.fake()->unique()->bothify('v##'),
            'label' => 'Rule Library stub '.fake()->unique()->numerify('##'),
            'status' => RuleLibraryVersionStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuleLibraryVersionStatus::Published,
            'published_at' => now(),
        ]);
    }
}
