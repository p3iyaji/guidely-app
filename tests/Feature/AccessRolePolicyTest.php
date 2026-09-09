<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessRole;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Database\Seeders\AccessCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessRolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogueSeeder::class);
    }

    public function test_active_tenant_admin_can_manage_custom_roles_and_view_system_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $custom = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        $system = AccessRole::query()->where('key', Role::Teacher->value)->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', AccessRole::class));
        $this->assertTrue($admin->can('create', AccessRole::class));
        $this->assertTrue($admin->can('view', $custom));
        $this->assertTrue($admin->can('update', $custom));
        $this->assertTrue($admin->can('delete', $custom));
        $this->assertTrue($admin->can('view', $system));
        $this->assertTrue($admin->can('update', $system));
        $this->assertTrue($admin->can('delete', $system));
    }

    public function test_deactivated_tenant_admin_cannot_manage_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->deactivated()->create();
        $custom = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);

        $this->assertFalse($admin->can('viewAny', AccessRole::class));
        $this->assertFalse($admin->can('view', $custom));
        $this->assertFalse($admin->can('create', AccessRole::class));
        $this->assertFalse($admin->can('update', $custom));
        $this->assertFalse($admin->can('delete', $custom));
    }

    public function test_tenant_admin_cannot_update_another_tenant_custom_role(): void
    {
        $tenantA = Tenant::factory()->create();
        $custom = AccessRole::factory()->forTenant($tenantA)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        $tenantB = Tenant::factory()->create();
        $adminB = User::factory()->forTenant($tenantB)->tenantAdmin()->create();

        $this->assertFalse($adminB->can('view', $custom));
        $this->assertFalse($adminB->can('update', $custom));
        $this->assertFalse($adminB->can('delete', $custom));
    }

    #[DataProvider('deniedRoles')]
    public function test_non_admin_roles_cannot_manage_roles(Role $role): void
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
        $custom = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        $system = AccessRole::query()->where('key', Role::Teacher->value)->firstOrFail();

        $this->assertFalse($user->can('viewAny', AccessRole::class));
        $this->assertFalse($user->can('view', $custom));
        $this->assertFalse($user->can('view', $system));
        $this->assertFalse($user->can('create', AccessRole::class));
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
