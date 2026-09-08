<?php

namespace Tests\Feature;

use App\Domain\Connectors\Connector;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConnectorPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_admin_can_view_and_update_same_tenant_connector(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $connector = Connector::factory()->forTenant($tenant)->create();

        $this->assertTrue($admin->can('viewAny', Connector::class));
        $this->assertTrue($admin->can('view', $connector));
        $this->assertTrue($admin->can('create', Connector::class));
        $this->assertTrue($admin->can('update', $connector));
        $this->assertTrue($admin->can('sync', $connector));
    }

    public function test_tenant_admin_cannot_view_or_update_other_tenant_connector(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $foreign = Connector::factory()->forTenant($tenantB)->create();

        $this->assertFalse($adminA->can('view', $foreign));
        $this->assertFalse($adminA->can('update', $foreign));
        $this->assertFalse($adminA->can('sync', $foreign));
    }

    public function test_deactivated_tenant_admin_cannot_view_or_update(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->deactivated()->create();
        $connector = Connector::factory()->forTenant($tenant)->create();

        $this->assertFalse($admin->can('viewAny', Connector::class));
        $this->assertFalse($admin->can('view', $connector));
        $this->assertFalse($admin->can('update', $connector));
        $this->assertFalse($admin->can('sync', $connector));
    }

    #[DataProvider('deniedRoles')]
    public function test_non_admin_roles_cannot_view_or_update(Role $role): void
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
        $connector = Connector::factory()->forTenant($tenant)->create();

        $this->assertFalse($user->can('viewAny', Connector::class));
        $this->assertFalse($user->can('view', $connector));
        $this->assertFalse($user->can('create', Connector::class));
        $this->assertFalse($user->can('update', $connector));
        $this->assertFalse($user->can('sync', $connector));
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
