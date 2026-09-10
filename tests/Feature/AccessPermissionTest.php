<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\AccessPermission;
use App\Domain\Identity\AccessRole;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\AccessPermissionController;
use App\Models\User;
use Database\Seeders\AccessCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogueSeeder::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/permissions')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_system_and_custom_permissions(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/permissions');

        $response->assertOk();

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains('manage_users', $keys);
        $this->assertContains('approve_referrals', $keys);
    }

    public function test_teacher_index_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $this->actingAs($teacher)->getJson('/api/v1/permissions')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $this->actingAs($admin)->postJson('/api/v1/permissions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'label']);
    }

    public function test_reserved_system_permission_key_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $this->actingAs($admin)->postJson('/api/v1/permissions', [
            'key' => 'manage_users',
            'label' => 'Users',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_tenant_admin_creates_a_custom_permission(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/permissions', [
            'key' => '  Approve_Referrals  ',
            'label' => '  Approve referrals  ',
            'description' => 'Sign off internal referrals.',
            'group' => 'Custom',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'approve_referrals')
            ->assertJsonPath('data.label', 'Approve referrals')
            ->assertJsonPath('data.group', 'Custom')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.tenant_id', $tenant->id);

        $this->assertDatabaseHas('access_permissions', [
            'key' => 'approve_referrals',
            'tenant_id' => $tenant->id,
            'is_system' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessPermissionCreated->value,
            'resource_type' => 'access_permission',
        ]);
    }

    public function test_tenant_admin_updates_a_custom_permission(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $permission = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/permissions/'.$permission->id, [
            'label' => 'Approve SEND referrals',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Approve SEND referrals');

        $this->assertDatabaseHas('access_permissions', [
            'id' => $permission->id,
            'label' => 'Approve SEND referrals',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessPermissionUpdated->value,
            'resource_id' => $permission->id,
        ]);
    }

    public function test_tenant_admin_updates_a_system_permission_label(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessPermission::query()->where('key', 'manage_users')->firstOrFail();

        $response = $this->actingAs($admin)->patchJson('/api/v1/permissions/'.$system->id, [
            'label' => 'Administer Users',
            'group' => 'Admin',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', 'manage_users')
            ->assertJsonPath('data.label', 'Administer Users')
            ->assertJsonPath('data.group', 'Admin')
            ->assertJsonPath('data.is_system', true);

        $this->assertDatabaseHas('access_permissions', [
            'id' => $system->id,
            'key' => 'manage_users',
            'label' => 'Administer Users',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessPermissionUpdated->value,
            'resource_id' => $system->id,
        ]);
    }

    public function test_changing_a_system_permission_key_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessPermission::query()->where('key', 'manage_users')->firstOrFail();

        $this->actingAs($admin)->patchJson('/api/v1/permissions/'.$system->id, [
            'key' => 'admin_users',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->assertDatabaseHas('access_permissions', [
            'id' => $system->id,
            'key' => 'manage_users',
        ]);
    }

    public function test_tenant_admin_deletes_an_unused_custom_permission(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $permission = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/permissions/'.$permission->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('access_permissions', ['id' => $permission->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessPermissionDeleted->value,
            'resource_id' => $permission->id,
        ]);
    }

    public function test_tenant_admin_deletes_an_unused_system_permission(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $system = AccessPermission::factory()->system()->create([
            'key' => 'legacy_export',
            'label' => 'Legacy export',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/permissions/'.$system->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('access_permissions', ['id' => $system->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::AccessPermissionDeleted->value,
            'resource_id' => $system->id,
        ]);
    }

    public function test_deleting_a_permission_assigned_to_a_role_returns_409(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $permission = AccessPermission::factory()->forTenant($tenant)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);
        $role = AccessRole::factory()->forTenant($tenant)->create([
            'key' => 'year_lead',
            'label' => 'Year Lead',
        ]);
        $role->permissions()->attach($permission->id);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/permissions/'.$permission->id);

        $response->assertConflict()
            ->assertJsonPath('code', AccessPermissionController::IN_USE_CODE)
            ->assertJsonPath(
                'message',
                'This Permission is linked to a role catalogue entry and cannot be deleted.',
            );

        $this->assertDatabaseHas('access_permissions', ['id' => $permission->id]);
    }

    public function test_cross_tenant_custom_permission_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $permission = AccessPermission::factory()->forTenant($tenantA)->create([
            'key' => 'approve_referrals',
            'label' => 'Approve referrals',
        ]);
        [, $adminB] = $this->tenantAndAdmin();

        $this->actingAs($adminB)->getJson('/api/v1/permissions/'.$permission->id)
            ->assertNotFound();

        $this->actingAs($adminB)->deleteJson('/api/v1/permissions/'.$permission->id)
            ->assertNotFound();

        $this->assertDatabaseHas('access_permissions', ['id' => $permission->id]);
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
