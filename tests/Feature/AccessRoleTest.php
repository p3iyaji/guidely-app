<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\AccessPermission;
use App\Domain\Identity\AccessRole;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\AccessRoleController;
use App\Models\User;
use Database\Seeders\AccessCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogueSeeder::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_system_and_custom_roles(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $custom = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/roles');

        $response->assertOk();

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains(Role::Teacher->value, $keys);
        $this->assertContains(Role::TenantAdmin->value, $keys);
        $this->assertContains('year_lead', $keys);

        $customPayload = collect($response->json('data'))->firstWhere('id', $custom->id);
        $this->assertNotNull($customPayload);
        $this->assertFalse($customPayload['is_system']);
        $this->assertSame($tenant->id, $customPayload['tenant_id']);
    }

    public function test_teacher_index_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $this->actingAs($teacher)->getJson('/api/v1/roles')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $this->actingAs($admin)->postJson('/api/v1/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'label']);
    }

    public function test_reserved_system_role_key_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $this->actingAs($admin)->postJson('/api/v1/roles', [
            'key' => Role::Teacher->value,
            'label' => 'Classroom Teacher',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->assertDatabaseMissing('access_roles', [
            'key' => Role::Teacher->value,
            'is_system' => false,
        ]);
    }

    public function test_tenant_admin_creates_a_custom_role_with_permissions(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $permission = AccessPermission::query()->where('key', 'manage_pupils')->firstOrFail();

        $response = $this->actingAs($admin)->postJson('/api/v1/roles', [
            'key' => '  Year_Lead  ',
            'label' => '  Year Lead  ',
            'description' => 'Coordinates a year group.',
            'permission_ids' => [$permission->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'year_lead')
            ->assertJsonPath('data.label', 'Year Lead')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.permission_ids.0', $permission->id);

        $this->assertDatabaseHas('access_roles', [
            'key' => 'year_lead',
            'tenant_id' => $tenant->id,
            'is_system' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessRoleCreated->value,
            'resource_type' => 'access_role',
        ]);
    }

    public function test_tenant_admin_updates_a_custom_role(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $role = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        $permission = AccessPermission::query()->where('key', 'view_school_report')->firstOrFail();

        $response = $this->actingAs($admin)->patchJson('/api/v1/roles/'.$role->id, [
            'label' => 'Year Group Lead',
            'permission_ids' => [$permission->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Year Group Lead')
            ->assertJsonPath('data.permission_ids.0', $permission->id);

        $this->assertDatabaseHas('access_roles', [
            'id' => $role->id,
            'label' => 'Year Group Lead',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessRoleUpdated->value,
            'resource_id' => $role->id,
        ]);
    }

    public function test_tenant_admin_updates_a_system_role_label_and_permissions(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessRole::query()->where('key', Role::Teacher->value)->firstOrFail();
        $permission = AccessPermission::query()->where('key', 'view_school_report')->firstOrFail();

        $response = $this->actingAs($admin)->patchJson('/api/v1/roles/'.$system->id, [
            'label' => 'Class Teacher',
            'description' => 'Updated Teacher description.',
            'permission_ids' => [$permission->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', Role::Teacher->value)
            ->assertJsonPath('data.label', 'Class Teacher')
            ->assertJsonPath('data.is_system', true)
            ->assertJsonPath('data.permission_ids.0', $permission->id);

        $this->assertDatabaseHas('access_roles', [
            'id' => $system->id,
            'key' => Role::Teacher->value,
            'label' => 'Class Teacher',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessRoleUpdated->value,
            'resource_id' => $system->id,
        ]);
    }

    public function test_changing_a_system_role_key_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessRole::query()->where('key', Role::Teacher->value)->firstOrFail();

        $this->actingAs($admin)->patchJson('/api/v1/roles/'.$system->id, [
            'key' => 'classroom_teacher',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->assertDatabaseHas('access_roles', [
            'id' => $system->id,
            'key' => Role::Teacher->value,
        ]);
    }

    public function test_tenant_admin_deletes_an_unused_custom_role(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $role = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/roles/'.$role->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('access_roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessRoleDeleted->value,
            'resource_id' => $role->id,
        ]);
    }

    public function test_tenant_admin_deletes_an_unused_system_role(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessRole::factory()->system()->create([
            'key' => 'legacy_observer',
            'label' => 'Legacy Observer',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/roles/'.$system->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('access_roles', ['id' => $system->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessRoleDeleted->value,
            'resource_id' => $system->id,
        ]);
    }

    public function test_deleting_a_role_assigned_to_users_returns_409(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        User::factory()->forTenant($tenant)->teacher()->create();
        $system = AccessRole::query()->where('key', Role::Teacher->value)->firstOrFail();

        $response = $this->actingAs($admin)->deleteJson('/api/v1/roles/'.$system->id);

        $response->assertConflict()
            ->assertJsonPath('code', AccessRoleController::IN_USE_CODE);

        $this->assertDatabaseHas('access_roles', ['id' => $system->id]);
    }

    public function test_cross_tenant_custom_role_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $role = AccessRole::factory()->forTenant($tenantA)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        [, $adminB] = $this->tenantAndAdmin();

        $this->actingAs($adminB)->getJson('/api/v1/roles/'.$role->id)
            ->assertNotFound();

        $this->actingAs($adminB)->patchJson('/api/v1/roles/'.$role->id, [
            'label' => 'Stolen',
        ])->assertNotFound();

        $this->actingAs($adminB)->deleteJson('/api/v1/roles/'.$role->id)
            ->assertNotFound();

        $this->assertDatabaseHas('access_roles', [
            'id' => $role->id,
            'label' => 'Year Lead',
        ]);
    }

    public function test_foreign_permission_id_returns_422(): void
    {
        [$tenantA] = $this->tenantAndAdmin();
        $foreignPermission = AccessPermission::factory()->forTenant($tenantA)->create([
            'key' => 'secret_perm',
            'label' => 'Secret',
        ]);
        [, $adminB] = $this->tenantAndAdmin();

        $this->actingAs($adminB)->postJson('/api/v1/roles', [
            'key' => 'year_lead',
            'label' => 'Year Lead',
            'permission_ids' => [$foreignPermission->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission_ids.0']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantAndAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        return [$tenant, $admin];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndTeacher(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        return [$tenant, $school, $teacher];
    }
}
