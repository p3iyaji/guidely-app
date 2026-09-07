<?php

namespace Database\Factories;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gap>
 *
 * Evaluator write fields are not mass-assignable on Gap;
 * Factory::create uses Model::unguarded so they can still be set here.
 */
class GapFactory extends Factory
{
    protected $model = Gap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pupil_id' => Pupil::factory(),
            'tenant_id' => fn (array $attributes): string => Pupil::query()
                ->findOrFail($attributes['pupil_id'])
                ->tenant_id,
            'determination_id' => null,
            'dimension' => SreDimension::SequentialCompliance,
            'result' => DeterminationResult::Unmet,
            'is_open' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forPupil(Pupil $pupil): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
        ]);
    }

    public function forDetermination(Determination $determination): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $determination->tenant_id,
            'pupil_id' => $determination->pupil_id,
            'determination_id' => $determination->id,
            'dimension' => $determination->dimension,
            'result' => $determination->result,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => true,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => false,
        ]);
    }
}
