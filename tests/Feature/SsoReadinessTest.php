<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\ExternalIdConflictException;
use App\Domain\Identity\LinkExternalIdByEmail;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SsoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_and_clear_user_external_id(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $create = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Linked Teacher',
            'email' => 'linked@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'external_id' => 'idp-subject-1',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.external_id', 'idp-subject-1');

        $userId = $create->json('data.id');

        $clear = $this->actingAs($admin)->patchJson('/api/v1/users/'.$userId, [
            'external_id' => null,
        ]);

        $clear->assertOk()
            ->assertJsonPath('data.external_id', null);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'external_id' => null,
        ]);
    }

    public function test_duplicate_external_id_in_same_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        User::factory()->forTenant($tenant)->teacher()->create([
            'external_id' => 'shared-subject',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Duplicate',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'external_id' => 'shared-subject',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id']);
    }

    public function test_patch_duplicate_external_id_in_same_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        User::factory()->forTenant($tenant)->teacher()->create([
            'external_id' => 'shared-subject',
        ]);
        $target = User::factory()->forTenant($tenant)->teacher()->create([
            'external_id' => null,
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/users/'.$target->id, [
            'external_id' => 'shared-subject',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id']);

        $this->assertNull($target->fresh()->external_id);
    }

    public function test_tenant_admin_can_patch_user_external_id(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create([
            'external_id' => null,
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/users/'.$teacher->id, [
            'external_id' => 'idp-patched',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.external_id', 'idp-patched');

        $this->assertSame('idp-patched', $teacher->fresh()->external_id);
    }

    public function test_same_external_id_may_exist_in_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminB = User::factory()->forTenant($tenantB)->tenantAdmin()->create();

        User::factory()->forTenant($tenantA)->teacher()->create([
            'external_id' => 'shared-across-tenants',
        ]);

        $response = $this->actingAs($adminB)->postJson('/api/v1/users', [
            'name' => 'Other Tenant User',
            'email' => 'other-tenant@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'external_id' => 'shared-across-tenants',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.external_id', 'shared-across-tenants');
    }

    public function test_tenant_admin_can_read_sso_stub_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/tenant/sso');

        $response->assertOk()
            ->assertJsonPath('data.sso_enabled', false)
            ->assertJsonPath('data.sso_provider', null)
            ->assertJsonPath('data.sso_entity_id', null)
            ->assertJsonPath('data.sso_client_id', null);
    }

    public function test_tenant_admin_can_update_sso_stub_without_idp_call(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->patchJson('/api/v1/tenant/sso', [
            'sso_enabled' => true,
            'sso_provider' => 'oidc-placeholder',
            'sso_entity_id' => 'https://idp.example/entity',
            'sso_client_id' => 'guidely-client',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sso_enabled', true)
            ->assertJsonPath('data.sso_provider', 'oidc-placeholder')
            ->assertJsonPath('data.sso_entity_id', 'https://idp.example/entity')
            ->assertJsonPath('data.sso_client_id', 'guidely-client');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'sso_enabled' => true,
            'sso_provider' => 'oidc-placeholder',
            'sso_entity_id' => 'https://idp.example/entity',
            'sso_client_id' => 'guidely-client',
        ]);
    }

    public function test_non_admin_cannot_read_or_update_sso_stub(): void
    {
        $tenant = Tenant::factory()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->assertForbidden(
            $this->actingAs($teacher)->getJson('/api/v1/tenant/sso')
        );

        $this->assertForbidden(
            $this->actingAs($teacher)->patchJson('/api/v1/tenant/sso', [
                'sso_enabled' => true,
            ])
        );

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'sso_enabled' => false,
        ]);
    }

    public function test_invalid_sso_stub_payload_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->patchJson('/api/v1/tenant/sso', [
            'sso_enabled' => 'not-a-boolean',
            'sso_provider' => str_repeat('x', 300),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sso_enabled', 'sso_provider']);
    }

    public function test_email_match_link_attaches_external_id_without_duplicate_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'Staff@Example.com',
            'external_id' => null,
        ]);

        $beforeCount = User::query()->count();

        $linked = app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'staff@example.com',
            'idp-abc',
        );

        $this->assertNotNull($linked);
        $this->assertTrue($linked->is($user));
        $this->assertSame('idp-abc', $linked->external_id);
        $this->assertSame($beforeCount, User::query()->count());
    }

    public function test_email_match_link_returns_null_for_unknown_email_without_creating(): void
    {
        $tenant = Tenant::factory()->create();
        $beforeCount = User::query()->count();

        $result = app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'new.staff@example.com',
            'idp-new',
        );

        $this->assertNull($result);
        $this->assertSame($beforeCount, User::query()->count());
        $this->assertDatabaseMissing('users', [
            'email' => 'new.staff@example.com',
        ]);
    }

    public function test_email_match_link_rejects_external_id_already_on_another_user(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'owner@example.com',
            'external_id' => 'taken-subject',
        ]);
        User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'other@example.com',
            'external_id' => null,
        ]);

        $this->expectException(ExternalIdConflictException::class);

        app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'other@example.com',
            'taken-subject',
        );
    }

    public function test_email_match_link_is_idempotent_when_same_external_id_already_linked(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'linked@example.com',
            'external_id' => 'idp-same',
        ]);

        $linked = app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'Linked@Example.com',
            'idp-same',
        );

        $this->assertNotNull($linked);
        $this->assertTrue($linked->is($user));
        $this->assertSame('idp-same', $linked->external_id);
    }

    public function test_email_match_link_conflicts_when_unknown_email_but_external_id_taken(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'owner@example.com',
            'external_id' => 'taken-subject',
        ]);

        $this->expectException(ExternalIdConflictException::class);

        app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'unknown@example.com',
            'taken-subject',
        );
    }

    public function test_email_match_link_returns_null_for_deactivated_user(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->teacher()->deactivated()->create([
            'email' => 'gone@example.com',
            'external_id' => null,
        ]);

        $result = app(LinkExternalIdByEmail::class)->handle(
            $tenant->id,
            'gone@example.com',
            'idp-deactivated',
        );

        $this->assertNull($result);
        $this->assertDatabaseHas('users', [
            'email' => 'gone@example.com',
            'external_id' => null,
        ]);
    }

    public function test_email_match_link_does_not_overwrite_different_existing_external_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'locked@example.com',
            'external_id' => 'original-subject',
        ]);

        try {
            app(LinkExternalIdByEmail::class)->handle(
                $tenant->id,
                'locked@example.com',
                'replacement-subject',
            );
            $this->fail('Expected ExternalIdConflictException was not thrown.');
        } catch (ExternalIdConflictException) {
            $this->assertSame('original-subject', $user->fresh()->external_id);
        }
    }

    public function test_password_login_and_token_still_work(): void
    {
        $tenant = Tenant::factory()->create([
            'sso_enabled' => false,
        ]);
        $user = User::factory()->forTenant($tenant)->create([
            'email' => 'password.user@example.com',
            'password' => Hash::make('password123'),
            'external_id' => null,
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => 'Password.User@Example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('message', 'Authenticated.');

        $token = $this->postJson('/api/v1/token', [
            'email' => 'password.user@example.com',
            'password' => 'password123',
            'device_name' => 'hybrid-test',
        ]);

        $token->assertOk()
            ->assertJsonStructure(['token', 'token_type']);

        $this->assertNotNull($user->fresh());
        $this->assertFalse($tenant->fresh()->sso_enabled);
    }

    public function test_readme_documents_saml_vs_oidc_open_question(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('SSO readiness', $readme);
        $this->assertStringContainsString('SAML vs OIDC', $readme);
        $this->assertStringContainsString('open question', strtolower($readme));
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }
}
