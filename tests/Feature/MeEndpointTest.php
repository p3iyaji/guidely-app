<?php

namespace Tests\Feature;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user_payload_with_school_ids(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $user = User::factory()->forTenant($tenant)->create([
            'name' => 'Shell User',
            'email' => 'shell@example.com',
            'password' => Hash::make('password'),
            'role' => Role::Senco,
        ]);
        $user->schools()->sync([$schoolA->id, $schoolB->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Shell User')
            ->assertJsonPath('data.email', 'shell@example.com')
            ->assertJsonPath('data.role', Role::Senco->value)
            ->assertJsonPath('data.tenant_id', $user->tenant_id)
            ->assertJsonPath('data.school_ids', [$schoolA->id, $schoolB->id]);
    }

    public function test_me_returns_empty_school_ids_when_unassigned(): void
    {
        $user = $this->provisionedUser([
            'role' => Role::Teacher,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.school_ids', []);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_me_works_with_sanctum_bearer_token(): void
    {
        $user = $this->provisionedUser([
            'role' => Role::Teacher,
        ]);

        $token = $user->createToken('hybrid')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', Role::Teacher->value)
            ->assertJsonPath('data.tenant_id', $user->tenant_id)
            ->assertJsonPath('data.school_ids', []);
    }

    public function test_authenticated_user_can_update_own_name_and_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->teacher()->create([
            'name' => 'Shell User',
            'email' => 'shell@example.com',
            'role' => Role::Teacher,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', [
                'name' => 'Ada Lovelace',
                'email' => 'Ada.Lovelace@Example.com',
                'role' => Role::TenantAdmin->value,
                'password' => 'hijack-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada.lovelace@example.com')
            ->assertJsonPath('data.role', Role::Teacher->value)
            ->assertJsonMissingPath('data.password');

        $fresh = $user->fresh();

        $this->assertSame('Ada Lovelace', $fresh->name);
        $this->assertSame('ada.lovelace@example.com', $fresh->email);
        $this->assertSame(Role::Teacher, $fresh->role);
        $this->assertTrue(Hash::check('password', $fresh->password));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.updated',
            'user_id' => $user->id,
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);
    }

    public function test_duplicate_profile_email_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->create([
            'email' => 'taken@example.com',
        ]);
        $user = User::factory()->forTenant($tenant)->teacher()->create([
            'email' => 'mine@example.com',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', [
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame('mine@example.com', $user->fresh()->email);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->patchJson('/api/v1/me', [
            'name' => 'Nobody',
        ])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_authenticated_user_can_change_own_password(): void
    {
        $user = $this->provisionedUser([
            'role' => Role::Teacher,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertFalse(Hash::check('password', $user->fresh()->password));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.password_reset',
            'user_id' => $user->id,
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->provisionedUser([
            'role' => Role::Teacher,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'not-the-password',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_rejects_unconfirmed_or_weak_password(): void
    {
        $user = $this->provisionedUser([
            'role' => Role::Teacher,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_requires_authentication(): void
    {
        $this->patchJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_platform_operator_can_update_profile(): void
    {
        $operator = User::factory()->platformOperator()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
        ]);

        $this->actingAs($operator)
            ->patchJson('/api/v1/me', [
                'name' => 'Platform Operator',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Platform Operator')
            ->assertJsonPath('data.email', 'operator@example.com');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function provisionedUser(array $overrides = []): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->forTenant($tenant)->create(array_merge([
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
        ], $overrides));
    }
}
