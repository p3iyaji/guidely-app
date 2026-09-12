<?php

namespace Tests\Feature;

use App\Domains\Identity\Role;
use App\Domains\Ontology\RelationshipMapping;
use App\Domains\Tenancy\FeatureFlagKey;
use App\Domains\Tenancy\Tenant;
use App\Domains\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelationshipMappingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_admin_can_manage_effective_version_mappings(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $mapping = RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', RelationshipMapping::class));
        $this->assertTrue($admin->can('view', $mapping));
        $this->assertTrue($admin->can('create', RelationshipMapping::class));
        $this->assertTrue($admin->can('update', $mapping));
        $this->assertTrue($admin->can('delete', $mapping));
    }

    public function test_deactivated_tenant_admin_cannot_manage_mappings(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->deactivated()->create();
        $mapping = RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $this->assertFalse($admin->can('viewAny', RelationshipMapping::class));
        $this->assertFalse($admin->can('view', $mapping));
        $this->assertFalse($admin->can('create', RelationshipMapping::class));
        $this->assertFalse($admin->can('update', $mapping));
        $this->assertFalse($admin->can('delete', $mapping));
    }

    #[DataProvider('deniedRoles')]
    public function test_non_admin_roles_cannot_manage_mappings(Role $role): void
    {
        $tenant = Tenant::factory()->trust()->create();

        if ($role->isTrustRole()) {
            TenantFeatureFlag::query()
                ->where('tenant_id', $tenant->id)
                ->where('key', FeatureFlagKey::TrustDashboard->value)
                ->update(['enabled' => true]);
        }

        $user = match ($role) {
            Role::PlatformOperator => User::factory()->platformOperator()->create(),
            default => User::factory()->forTenant($tenant)->state(['role' => $role])->create(),
        };
        $mapping = RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $this->assertFalse($user->can('viewAny', RelationshipMapping::class));
        $this->assertFalse($user->can('view', $mapping));
        $this->assertFalse($user->can('create', RelationshipMapping::class));
        $this->assertFalse($user->can('update', $mapping));
        $this->assertFalse($user->can('delete', $mapping));
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function deniedRoles(): array
    {
        return [
            'senco' => [Role::Senco],
            'teacher' => [Role::Teacher],
            'support_staff' => [Role::SupportStaff],
            'school_leader' => [Role::SchoolLeader],
            'trust_send_lead' => [Role::TrustSendLead],
            'trust_executive' => [Role::TrustExecutive],
            'platform_operator' => [Role::PlatformOperator],
        ];
    }
}