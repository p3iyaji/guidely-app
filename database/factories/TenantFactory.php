<?php

namespace Database\Factories;

use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => TenantType::School,
            'cohort_enabled' => false,
            'cohort_label' => null,
            'sso_enabled' => false,
            'sso_provider' => null,
            'sso_entity_id' => null,
            'sso_client_id' => null,
        ];
    }

    public function school(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TenantType::School,
        ]);
    }

    public function trust(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TenantType::Trust,
        ]);
    }

    public function withCohort(?string $label = 'Pilot cohort'): static
    {
        return $this->state(fn (array $attributes) => [
            'cohort_enabled' => true,
            'cohort_label' => $label,
        ]);
    }
}
