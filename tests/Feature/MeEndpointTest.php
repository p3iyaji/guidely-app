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
