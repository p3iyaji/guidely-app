<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessPermission;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Database\Seeders\AccessCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessPermissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogueSeeder::class);
    }

    public function test_active_tenant_admin_can_manage_custom_permissions_and_view_system_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $custom = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);
        $system = AccessPermission::query()->where('key', 'manage_users')->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', AccessPermission::class));
        $this->assertTrue($admin->can('create', AccessPermission::class));
        $this->assertTrue($admin->can('view', $custom));
        $this->assertTrue($admin->can('update', $custom));
        $this->assertTrue($admin->can('delete', $custom));
        $this->assertTrue($admin->can('view', $system));
        $this->assertTrue($admin->can('update', $system));
        $this->assertTrue($admin->can('delete', $system));
    }

    public function test_deactivated_tenant_admin_cannot_manage_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->deactivated()->create();
        $custom = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);

        $this->assertFalse($admin->can('viewAny', AccessPermission::class));
        $this->assertFalse($admin->can('view', $custom));
        $this->assertFalse($admin->can('create', AccessPermission::class));
        $this->assertFalse($admin->can('update', $custom));
        $this->assertFalse($admin->can('delete', $custom));
    }

    #[DataProvider('deniedRoles')]
    public function test_non_admin_roles_cannot_manage_permissions(Role $role): void
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
        $custom = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);

        $this->assertFalse($user->can('viewAny', AccessPermission::class));
        $this->assertFalse($user->can('view', $custom));
        $this->assertFalse($user->can('create', AccessPermission::class));
        $this->assertFalse($user->can('update', $custom));
        $this->assertFalse($user->can('delete', $custom));
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
