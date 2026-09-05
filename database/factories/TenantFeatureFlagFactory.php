<?php

namespace Database\Factories;

use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantFeatureFlag>
 */
class TenantFeatureFlagFactory extends Factory
{
    protected $model = TenantFeatureFlag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => FeatureFlagKey::TrustDashboard,
            'enabled' => false,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function enabled(bool $enabled = true): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => $enabled,
        ]);
    }

    public function key(FeatureFlagKey $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }
}
