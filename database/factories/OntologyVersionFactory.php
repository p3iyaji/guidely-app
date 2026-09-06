<?php

namespace Database\Factories;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OntologyVersion>
 */
class OntologyVersionFactory extends Factory
{
    protected $model = OntologyVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'stub-'.fake()->unique()->bothify('v##'),
            'label' => 'Ontology stub '.fake()->unique()->numerify('##'),
            'status' => OntologyVersionStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OntologyVersionStatus::Published,
            'published_at' => now(),
        ]);
    }
}
