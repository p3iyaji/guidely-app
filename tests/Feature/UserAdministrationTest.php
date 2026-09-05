<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_a_user_with_role_and_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'New Teacher',
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'school_ids' => [$school->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Teacher')
            ->assertJsonPath('data.email', 'teacher@example.com')
            ->assertJsonPath('data.role', Role::Teacher->value)
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.school_ids.0', $school->id)
            ->assertJsonMissingPath('data.password');

        $created = User::query()->where('email', 'teacher@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame($tenant->id, $created->tenant_id);
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertTrue($created->schools()->whereKey($school->id)->exists());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_mutate_or_read_users(string $factoryState): void
    {
        $tenant = Tenant::factory()->trust()->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $target = User::factory()->forTenant($tenant)->teacher()->create();

        $this->assertForbidden(
            $this->actingAs($actor)->postJson('/api/v1/users', [
                'name' => 'Blocked',
                'email' => 'blocked@example.com',
                'password' => 'password123',
                'role' => Role::Teacher->value,
            ])
        );

        $this->assertForbidden(
            $this->actingAs($actor)->getJson('/api/v1/users')
        );

        $this->assertForbidden(
            $this->actingAs($actor)->getJson('/api/v1/users/'.$target->id)
        );

        $this->assertForbidden(
            $this->actingAs($actor)->patchJson('/api/v1/users/'.$target->id, [
                'role' => Role::Senco->value,
            ])
        );

        $this->assertForbidden(
            $this->actingAs($actor)->patchJson('/api/v1/users/'.$target->id.'/password', [
                'password' => 'password123',
            ])
        );

        $this->assertForbidden(
            $this->actingAs($actor)->postJson('/api/v1/users/'.$target->id.'/deactivate')
        );

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
        $this->assertSame(Role::Teacher, $target->fresh()->role);
        $this->assertFalse($target->fresh()->isDeactivated());
    }

    public function test_tenant_admin_list_returns_only_same_tenant_users(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $peer = User::factory()->forTenant($tenantA)->teacher()->create(['name' => 'Peer Teacher']);
        User::factory()->forTenant($tenantB)->teacher()->create(['name' => 'Other Tenant']);

        $response = $this->actingAs($admin)->getJson('/api/v1/users');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($admin->id, $ids);
        $this->assertContains($peer->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_tenant_admin_can_update_role_and_school_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $teacher->schools()->attach($schoolA->id);

        $response = $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'role' => Role::Senco->value,
            'school_ids' => [$schoolB->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', Role::Senco->value)
            ->assertJsonPath('data.school_ids.0', $schoolB->id);

        $this->assertSame(Role::Senco, $teacher->fresh()->role);
        $this->assertFalse($teacher->schools()->whereKey($schoolA->id)->exists());
        $this->assertTrue($teacher->schools()->whereKey($schoolB->id)->exists());
    }

    public function test_cross_tenant_school_ids_are_rejected_on_create(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $foreignSchool = School::factory()->forTenant($tenantB)->create();

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Cross Tenant',
            'email' => 'cross@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'school_ids' => [$foreignSchool->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_ids.0']);

        $this->assertDatabaseMissing('users', [
            'email' => 'cross@example.com',
        ]);
    }

    public function test_cross_tenant_school_ids_are_rejected_on_update(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenantA)->teacher()->create();
        $foreignSchool = School::factory()->forTenant($tenantB)->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'school_ids' => [$foreignSchool->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_ids.0']);

        $this->assertFalse($teacher->schools()->whereKey($foreignSchool->id)->exists());
    }

    public function test_platform_operator_and_trust_roles_without_flag_are_not_assignable_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Operator Attempt',
            'email' => 'operator@example.com',
            'password' => 'password123',
            'role' => Role::PlatformOperator->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Trust Attempt',
            'email' => 'trust@example.com',
            'password' => 'password123',
            'role' => Role::TrustSendLead->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_platform_operator_and_trust_roles_without_flag_are_rejected_on_update(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'role' => Role::PlatformOperator->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'role' => Role::TrustSendLead->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertSame(Role::Teacher, $teacher->fresh()->role);
    }

    public function test_trust_roles_are_assignable_when_trust_dashboard_is_enabled(): void
    {
        $tenant = Tenant::factory()->trust()->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Trust Lead',
            'email' => 'lead@example.com',
            'password' => 'password123',
            'role' => Role::TrustSendLead->value,
        ])->assertCreated()
            ->assertJsonPath('data.role', Role::TrustSendLead->value);
    }

    public function test_trust_role_update_succeeds_when_trust_dashboard_is_enabled(): void
    {
        $tenant = Tenant::factory()->trust()->create();

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);

        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'role' => Role::TrustExecutive->value,
        ])->assertOk()
            ->assertJsonPath('data.role', Role::TrustExecutive->value);

        $this->assertSame(Role::TrustExecutive, $teacher->fresh()->role);
    }

    public function test_password_reset_updates_hash_and_revokes_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($teacher);
        $teacher->createToken('hybrid');
        $this->assertSame(1, $teacher->tokens()->count());

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id.'/password', [
            'password' => 'new-password-99',
        ])->assertOk()
            ->assertJsonMissingPath('data.password');

        $teacher->refresh();
        $this->assertTrue(Hash::check('new-password-99', $teacher->password));
        $this->assertFalse(Hash::check('old-password', $teacher->password));
        $this->assertSame(0, $teacher->tokens()->count());
    }

    public function test_weak_password_reset_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id.'/password', [
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_deactivate_uses_deactivate_helper_and_blocks_api(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $secondAdmin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->createToken('hybrid');

        $this->actingAs($admin)->postJson('/api/v1/users/'.$teacher->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.deactivated_at', fn ($value) => is_string($value) && $value !== '');

        $teacher->refresh();
        $this->assertTrue($teacher->isDeactivated());
        $this->assertSame(0, $teacher->tokens()->count());

        $this->actingAs($teacher)->getJson('/api/v1/users')
            ->assertUnauthorized();

        $this->assertNotNull($secondAdmin->fresh());
    }

    public function test_cannot_deactivate_last_active_tenant_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/users/'.$admin->id.'/deactivate')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Cannot deactivate the last active Tenant Admin for this Tenant.',
                'code' => UserController::LAST_TENANT_ADMIN_CODE,
            ]);

        $this->assertFalse($admin->fresh()->isDeactivated());
    }

    public function test_cannot_demote_last_active_tenant_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$admin->id, [
            'role' => Role::Teacher->value,
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Cannot demote the last active Tenant Admin for this Tenant.',
                'code' => UserController::LAST_TENANT_ADMIN_CODE,
            ]);

        $this->assertSame(Role::TenantAdmin, $admin->fresh()->role);
    }

    public function test_demote_tenant_admin_succeeds_when_another_active_admin_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $peerAdmin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$peerAdmin->id, [
            'role' => Role::Teacher->value,
        ])->assertOk()
            ->assertJsonPath('data.role', Role::Teacher->value);

        $this->assertSame(Role::Teacher, $peerAdmin->fresh()->role);
        $this->assertSame(Role::TenantAdmin, $admin->fresh()->role);
        $this->assertSame(1, User::activeTenantAdminCount($tenant->id));
    }

    public function test_extra_admin_may_self_deactivate(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $peerAdmin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/users/'.$admin->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.deactivated_at', fn ($value) => is_string($value) && $value !== '');

        $this->assertTrue($admin->fresh()->isDeactivated());
        $this->assertFalse($peerAdmin->fresh()->isDeactivated());
        $this->assertSame(1, User::activeTenantAdminCount($tenant->id));
    }

    public function test_update_and_password_reset_reject_deactivated_users(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $deactivated = User::factory()->forTenant($tenant)->teacher()->deactivated()->create();
        $originalDeactivatedAt = $deactivated->deactivated_at?->toIso8601String();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$deactivated->id, [
            'name' => 'Should Fail',
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Cannot update a deactivated User.',
                'code' => UserController::USER_DEACTIVATED_CODE,
            ]);

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$deactivated->id.'/password', [
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Cannot reset password for a deactivated User.',
                'code' => UserController::USER_DEACTIVATED_CODE,
            ]);

        $deactivated->refresh();
        $this->assertNotSame('Should Fail', $deactivated->name);
        $this->assertSame($originalDeactivatedAt, $deactivated->deactivated_at?->toIso8601String());
    }

    public function test_deactivate_rejects_already_deactivated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $deactivated = User::factory()->forTenant($tenant)->teacher()->deactivated()->create();
        $originalDeactivatedAt = $deactivated->deactivated_at?->toIso8601String();

        $this->actingAs($admin)->postJson('/api/v1/users/'.$deactivated->id.'/deactivate')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'User is already deactivated.',
                'code' => UserController::USER_DEACTIVATED_CODE,
            ]);

        $this->assertSame(
            $originalDeactivatedAt,
            $deactivated->fresh()->deactivated_at?->toIso8601String()
        );
    }

    public function test_cross_tenant_user_mutations_return_not_found(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $foreign = User::factory()->forTenant($tenantB)->teacher()->create();

        $this->actingAs($admin)->getJson('/api/v1/users/'.$foreign->id)
            ->assertNotFound();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$foreign->id, [
            'name' => 'Hijack',
        ])->assertNotFound();

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$foreign->id.'/password', [
            'password' => 'password123',
        ])->assertNotFound();

        $this->actingAs($admin)->postJson('/api/v1/users/'.$foreign->id.'/deactivate')
            ->assertNotFound();

        $this->assertSame(Role::Teacher, $foreign->fresh()->role);
        $this->assertFalse($foreign->fresh()->isDeactivated());
        $this->assertNotSame('Hijack', $foreign->fresh()->name);
    }

    public function test_create_validation_errors_return_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
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

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }
}
