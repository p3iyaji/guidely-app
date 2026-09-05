<?php

namespace Tests\Feature;

use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_gated_route_returns_403_feature_not_available_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        $this->assertDatabaseHas('tenant_feature_flags', [
            'tenant_id' => $tenant->id,
            'key' => FeatureFlagKey::TrustDashboard->value,
            'enabled' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/trust-dashboard');

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'trust_dashboard',
            ]);
    }

    #[DataProvider('gatedFeatures')]
    public function test_gated_route_returns_200_placeholder_when_flag_is_on(
        FeatureFlagKey $key,
        string $path,
    ): void {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', $key->value)
            ->update(['enabled' => true]);

        $response = $this->actingAs($user)->getJson($path);

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('feature', $key->value)
            ->assertJsonPath('placeholder', true)
            ->assertJsonMissing(['kpi' => 0])
            ->assertJsonMissing(['value' => 0]);
    }

    public function test_list_flags_returns_known_keys_for_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/tenant/feature-flags');

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'trust_dashboard' => false,
                    'connectors' => false,
                    'advanced_documentation_packs' => false,
                ],
            ]);
    }

    public function test_unauthenticated_flag_list_returns_401(): void
    {
        $response = $this->getJson('/api/v1/tenant/feature-flags');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_patch_enables_flag_and_gated_route_respects_new_value(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($user)->getJson('/api/v1/connectors')->assertForbidden();

        $update = $this->actingAs($user)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'connectors',
            'enabled' => true,
        ]);

        $update->assertOk()
            ->assertJsonPath('data.connectors', true)
            ->assertJsonPath('data.trust_dashboard', false);

        $this->assertDatabaseHas('tenant_feature_flags', [
            'tenant_id' => $tenant->id,
            'key' => 'connectors',
            'enabled' => true,
        ]);

        $this->actingAs($user)->getJson('/api/v1/connectors')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('feature', 'connectors');
    }

    public function test_patch_unknown_key_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'not_a_real_flag',
            'enabled' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_patch_invalid_body_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'trust_dashboard',
            'enabled' => 'yes-please',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled']);
    }

    public function test_new_tenant_defaults_advanced_flags_to_false(): void
    {
        $tenant = Tenant::factory()->create();

        foreach (FeatureFlagKey::cases() as $key) {
            $this->assertDatabaseHas('tenant_feature_flags', [
                'tenant_id' => $tenant->id,
                'key' => $key->value,
                'enabled' => false,
            ]);
        }
    }

    public function test_tenant_resource_includes_feature_flag_map(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::Connectors->value)
            ->update(['enabled' => true]);

        $response = $this->actingAs($user)->getJson('/api/v1/tenant');

        $response->assertOk()
            ->assertJsonPath('data.feature_flags.trust_dashboard', false)
            ->assertJsonPath('data.feature_flags.connectors', true)
            ->assertJsonPath('data.feature_flags.advanced_documentation_packs', false);
    }

    public function test_cross_tenant_cannot_read_or_mutate_other_tenant_flags(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenantB->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $userA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $list = $this->actingAs($userA)->getJson('/api/v1/tenant/feature-flags');

        $list->assertOk()
            ->assertJsonPath('data.trust_dashboard', false)
            ->assertJsonMissing(['trust_dashboard' => true]);

        $patch = $this->actingAs($userA)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'trust_dashboard',
            'enabled' => true,
        ]);

        $patch->assertOk()->assertJsonPath('data.trust_dashboard', true);

        $this->assertDatabaseHas('tenant_feature_flags', [
            'tenant_id' => $tenantA->id,
            'key' => 'trust_dashboard',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('tenant_feature_flags', [
            'tenant_id' => $tenantB->id,
            'key' => 'trust_dashboard',
            'enabled' => true,
        ]);

        $userB = User::factory()->forTenant($tenantB)->create();

        $this->actingAs($userA)->getJson('/api/v1/trust-dashboard')->assertOk();
        $this->actingAs($userB)->getJson('/api/v1/trust-dashboard')->assertOk();

        $this->actingAs($userA)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'trust_dashboard',
            'enabled' => false,
        ])->assertOk();

        $this->actingAs($userA)->getJson('/api/v1/trust-dashboard')->assertForbidden();
        $this->actingAs($userB)->getJson('/api/v1/trust-dashboard')->assertOk();
    }

    /**
     * @return array<string, array{0: FeatureFlagKey, 1: string}>
     */
    public static function gatedFeatures(): array
    {
        return [
            'trust_dashboard' => [FeatureFlagKey::TrustDashboard, '/api/v1/trust-dashboard'],
            'connectors' => [FeatureFlagKey::Connectors, '/api/v1/connectors'],
            'advanced_documentation_packs' => [
                FeatureFlagKey::AdvancedDocumentationPacks,
                '/api/v1/advanced-documentation-packs',
            ],
        ];
    }
}
