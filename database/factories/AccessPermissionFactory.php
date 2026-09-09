<?php

namespace Database\Factories;

use App\Domain\Identity\AccessPermission;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessPermission>
 */
class AccessPermissionFactory extends Factory
{
    protected $model = AccessPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => CurrentTenant::id() ?? Tenant::factory()->create()->id,
            'key' => 'perm_'.fake()->unique()->numerify('####'),
            'label' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'group' => 'Custom',
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
            'key' => 'sys_'.fake()->unique()->numerify('####'),
            'group' => 'Administration',
        ]);
    }
}
