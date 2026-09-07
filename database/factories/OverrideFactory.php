<?php

namespace Database\Factories;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\Override;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Override>
 *
 * Writer fields are not mass-assignable on Override;
 * Factory::create uses Model::unguarded so they can still be set here.
 */
class OverrideFactory extends Factory
{
    protected $model = Override::class;

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
            'determination_id' => fn (array $attributes): string => Determination::factory()
                ->forPupil(Pupil::query()->findOrFail($attributes['pupil_id']))
                ->current()
                ->create()
                ->id,
            'dimension' => fn (array $attributes): SreDimension => Determination::query()
                ->findOrFail($attributes['determination_id'])
                ->dimension,
            'user_id' => fn (array $attributes): int => User::factory()
                ->forTenant(Tenant::query()->findOrFail($attributes['tenant_id']))
                ->create()
                ->id,
            'rationale' => 'Professional judgement that this documentation gap is not blocking.',
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
        ]);
    }
}
