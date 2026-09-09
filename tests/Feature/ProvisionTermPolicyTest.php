<?php

namespace Tests\Feature;

use App\Domain\Identity\Role;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProvisionTermPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_active_tenant_admin_can_manage_effective_version_terms(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $term = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', ProvisionTerm::class));
        $this->assertTrue($admin->can('view', $term));
        $this->assertTrue($admin->can('create', ProvisionTerm::class));
        $this->assertTrue($admin->can('update', $term));
        $this->assertTrue($admin->can('delete', $term));
    }

    public function test_deactivated_tenant_admin_cannot_manage_terms(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->deactivated()->create();
        $term = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        $this->assertFalse($admin->can('viewAny', ProvisionTerm::class));
        $this->assertFalse($admin->can('view', $term));
        $this->assertFalse($admin->can('create', ProvisionTerm::class));
        $this->assertFalse($admin->can('update', $term));
        $this->assertFalse($admin->can('delete', $term));
    }

    #[DataProvider('deniedRoles')]
    public function test_non_admin_roles_cannot_manage_terms(Role $role): void
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
        $term = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        $this->assertFalse($user->can('viewAny', ProvisionTerm::class));
        $this->assertFalse($user->can('view', $term));
        $this->assertFalse($user->can('create', ProvisionTerm::class));
        $this->assertFalse($user->can('update', $term));
        $this->assertFalse($user->can('delete', $term));
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
