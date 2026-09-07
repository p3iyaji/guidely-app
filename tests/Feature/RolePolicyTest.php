<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_mutate_schools_cohort_and_flags(): void
    {
        $tenant = Tenant::factory()->trust()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create(['name' => 'Editable']);

        $this->actingAs($admin)->postJson('/api/v1/schools', [
            'name' => 'New School',
        ])->assertCreated();

        $this->actingAs($admin)->patchJson('/api/v1/schools/'.$school->id, [
            'name' => 'Renamed School',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed School');

        $this->actingAs($admin)->patchJson('/api/v1/tenant', [
            'cohort_enabled' => true,
            'cohort_label' => 'Pilot Cohort',
        ])->assertOk()
            ->assertJsonPath('data.cohort_enabled', true);

        $this->actingAs($admin)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => 'connectors',
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.connectors', true);
    }

    public function test_tenant_admin_can_delete_a_school(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create(['name' => 'To Remove']);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/schools/'.$school->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('schools', [
            'id' => $school->id,
        ]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_mutate_schools_cohort_or_flags(string $factoryState): void
    {
        $tenant = Tenant::factory()->trust()->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $school = School::factory()->forTenant($tenant)->create(['name' => 'Protected']);

        $this->assertForbiddenMutation(
            $this->actingAs($actor)->postJson('/api/v1/schools', ['name' => 'Blocked School'])
        );

        $this->assertForbiddenMutation(
            $this->actingAs($actor)->patchJson('/api/v1/schools/'.$school->id, ['name' => 'Hijack'])
        );

        $this->assertForbiddenMutation(
            $this->actingAs($actor)->deleteJson('/api/v1/schools/'.$school->id)
        );

        $this->assertForbiddenMutation(
            $this->actingAs($actor)->patchJson('/api/v1/tenant', [
                'cohort_enabled' => true,
                'cohort_label' => 'No',
            ])
        );

        $this->assertForbiddenMutation(
            $this->actingAs($actor)->patchJson('/api/v1/tenant/feature-flags', [
                'key' => 'trust_dashboard',
                'enabled' => true,
            ])
        );

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'name' => 'Protected',
        ]);
        $this->assertDatabaseMissing('schools', [
            'tenant_id' => $tenant->id,
            'name' => 'Blocked School',
        ]);
    }

    public function test_non_admin_same_tenant_staff_are_limited_to_assigned_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create(['name' => 'Assigned School']);
        $schoolB = School::factory()->forTenant($tenant)->create(['name' => 'Other School']);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($schoolA->id);

        $this->actingAs($teacher)->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $schoolA->id)
            ->assertJsonMissing(['id' => $schoolB->id]);

        $this->actingAs($teacher)->getJson('/api/v1/schools/'.$schoolA->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Assigned School');

        $this->assertForbiddenMutation(
            $this->actingAs($teacher)->getJson('/api/v1/schools/'.$schoolB->id)
        );

        $this->actingAs($teacher)->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id);
    }

    public function test_teacher_without_school_assignments_sees_empty_school_list(): void
    {
        $tenant = Tenant::factory()->create();
        School::factory()->forTenant($tenant)->create(['name' => 'Unscoped School']);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($teacher)->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_factory_role_states_cover_each_role(): void
    {
        $tenant = Tenant::factory()->create();

        $cases = [
            'teacher' => Role::Teacher,
            'supportStaff' => Role::SupportStaff,
            'senco' => Role::Senco,
            'schoolLeader' => Role::SchoolLeader,
            'tenantAdmin' => Role::TenantAdmin,
            'trustSendLead' => Role::TrustSendLead,
            'trustExecutive' => Role::TrustExecutive,
        ];

        foreach ($cases as $state => $role) {
            $user = User::factory()->forTenant($tenant)->{$state}()->create();
            $this->assertSame($role, $user->role);
        }

        $operator = User::factory()->platformOperator()->create();
        $this->assertSame(Role::PlatformOperator, $operator->role);
        $this->assertNull($operator->tenant_id);
    }

    public function test_school_scope_pivot_can_attach_users_to_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $user = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($user);

        $user->schools()->attach($school->id);

        $this->assertTrue($user->schools()->whereKey($school->id)->exists());
        $this->assertTrue($school->users()->whereKey($user->id)->exists());
        $this->assertDatabaseHas('school_user', [
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_trust_roles_are_inactive_staff_when_trust_dashboard_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $resolver = app(FeatureFlagResolver::class);
        School::factory()->forTenant($tenant)->create(['name' => 'Trust Scoped']);

        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();
        $executive = User::factory()->forTenant($tenant)->trustExecutive()->create();

        $this->assertTrue($lead->role->isTrustRole());
        $this->assertTrue($executive->role->isTrustRole());
        $this->assertFalse($lead->isActiveTrustStaff($resolver));
        $this->assertFalse($executive->isActiveTrustStaff($resolver));
        $this->assertFalse($lead->isActiveTenantStaff($resolver));
        $this->assertFalse($executive->isActiveTenantStaff($resolver));

        $this->assertForbiddenMutation(
            $this->actingAs($lead)->getJson('/api/v1/schools')
        );
        $this->assertForbiddenMutation(
            $this->actingAs($lead)->getJson('/api/v1/tenant')
        );
        $this->assertForbiddenMutation(
            $this->actingAs($executive)->getJson('/api/v1/schools')
        );
        $this->assertForbiddenMutation(
            $this->actingAs($executive)->getJson('/api/v1/tenant')
        );

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $lead = $lead->fresh();
        $executive = $executive->fresh();

        $this->assertTrue($lead->isActiveTrustStaff($resolver));
        $this->assertTrue($executive->isActiveTrustStaff($resolver));
        $this->assertTrue($lead->isActiveTenantStaff($resolver));
        $this->assertTrue($executive->isActiveTenantStaff($resolver));

        $this->actingAs($lead)->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Trust Scoped');
        $this->actingAs($lead)->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id);

        $this->actingAs($executive)->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Trust Scoped');
        $this->actingAs($executive)->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id);
    }

    public function test_platform_operator_is_not_active_tenant_staff(): void
    {
        $operator = User::factory()->platformOperator()->create();

        $this->assertFalse($operator->isActiveTenantStaff());
    }

    public function test_future_addendum_gates_deny_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin);

        $this->assertFalse(Gate::allows('capture-evidence'));
        $this->assertFalse(Gate::allows('view-evidence'));
        $this->assertFalse(Gate::allows('run-determinations'));
        $this->assertFalse(Gate::allows('documentation-output'));
    }

    public function test_override_determination_gate_allows_senco_and_school_leader(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($senco);
        $this->assertTrue(Gate::allows('override-determination'));

        $this->actingAs($leader);
        $this->assertTrue(Gate::allows('override-determination'));

        $this->actingAs($teacher);
        $this->assertFalse(Gate::allows('override-determination'));

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('override-determination'));
    }

    public function test_documentation_output_gate_allows_senco_and_school_leader(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($senco);
        $this->assertTrue(Gate::allows('documentation-output'));

        $this->actingAs($leader);
        $this->assertTrue(Gate::allows('documentation-output'));

        $this->actingAs($teacher);
        $this->assertFalse(Gate::allows('documentation-output'));

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('documentation-output'));
    }

    public function test_platform_operator_is_not_a_tenant_assignable_role(): void
    {
        $this->assertFalse(Role::PlatformOperator->isTenantAssignable());
        $this->assertTrue(Role::TenantAdmin->isTenantAssignable());
        $this->assertTrue(Role::TrustSendLead->isTenantAssignable());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'support_staff' => ['supportStaff'],
            'senco' => ['senco'],
            'school_leader' => ['schoolLeader'],
            'trust_send_lead' => ['trustSendLead'],
            'trust_executive' => ['trustExecutive'],
        ];
    }

    private function assertForbiddenMutation($response): void
    {
        $response->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }
}
