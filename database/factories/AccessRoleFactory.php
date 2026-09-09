<?php

namespace Database\Factories;

use App\Domain\Identity\AccessRole;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRole>
 */
class AccessRoleFactory extends Factory
{
    protected $model = AccessRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => CurrentTenant::id() ?? Tenant::factory()->create()->id,
            'key' => 'role_'.fake()->unique()->numerify('####'),
            'label' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
            'is_system' => false,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'is_system' => true,
            'key' => 'sys_role_'.fake()->unique()->numerify('####'),
        ]);
    }
}
