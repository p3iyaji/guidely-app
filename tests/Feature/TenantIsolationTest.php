<?php

namespace Tests\Feature;

use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_schools_returns_only_own_tenant_schools(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $schoolA = School::factory()->forTenant($tenantA)->create(['name' => 'Alpha School']);
        School::factory()->forTenant($tenantB)->create(['name' => 'Bravo School']);

        $userA = User::factory()->forTenant($tenantA)->create();
        $userA->schools()->attach($schoolA->id);

        $response = $this->actingAs($userA)->getJson('/api/v1/schools');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $schoolA->id)
            ->assertJsonPath('data.0.tenant_id', $tenantA->id)
            ->assertJsonMissing(['name' => 'Bravo School']);
    }

    public function test_cross_tenant_show_by_id_returns_404_without_leakage(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $schoolB = School::factory()->forTenant($tenantB)->create(['name' => 'Secret School']);
        $userA = User::factory()->forTenant($tenantA)->create();

        $response = $this->actingAs($userA)->getJson('/api/v1/schools/'.$schoolB->id);

        $response->assertNotFound()
            ->assertJsonMissing(['name' => 'Secret School'])
            ->assertJsonMissing(['id' => $schoolB->id]);

        $this->assertStringNotContainsString('Secret School', $response->getContent());
        $this->assertStringNotContainsString($tenantB->id, $response->getContent());
    }

    public function test_cross_tenant_patch_returns_404_and_leaves_row_unchanged(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $schoolB = School::factory()->forTenant($tenantB)->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);
        $userA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $response = $this->actingAs($userA)->patchJson('/api/v1/schools/'.$schoolB->id, [
            'name' => 'Hijacked Name',
            'is_active' => false,
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Original Name', $response->getContent());
        $this->assertStringNotContainsString('Hijacked Name', $response->getContent());

        $this->assertDatabaseHas('schools', [
            'id' => $schoolB->id,
            'tenant_id' => $tenantB->id,
            'name' => 'Original Name',
            'is_active' => true,
        ]);
    }

    public function test_cross_tenant_delete_returns_404_and_leaves_row_unchanged(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $schoolB = School::factory()->forTenant($tenantB)->create();
        $userA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $response = $this->actingAs($userA)->deleteJson('/api/v1/schools/'.$schoolB->id);

        $response->assertNotFound();
        $this->assertDatabaseHas('schools', [
            'id' => $schoolB->id,
            'tenant_id' => $tenantB->id,
        ]);
    }

    public function test_trust_tenant_can_create_and_list_multiple_schools_sharing_tenant_id(): void
    {
        $trust = Tenant::factory()->trust()->create(['name' => 'Acme Trust']);
        $user = User::factory()->forTenant($trust)->tenantAdmin()->create();

        $createOne = $this->actingAs($user)->postJson('/api/v1/schools', [
            'name' => 'North Academy',
        ]);
        $createTwo = $this->actingAs($user)->postJson('/api/v1/schools', [
            'name' => 'South Academy',
        ]);

        $createOne->assertCreated()->assertJsonPath('data.tenant_id', $trust->id);
        $createTwo->assertCreated()->assertJsonPath('data.tenant_id', $trust->id);

        $list = $this->actingAs($user)->getJson('/api/v1/schools');

        $list->assertOk()->assertJsonCount(2, 'data');

        $tenantIds = collect($list->json('data'))->pluck('tenant_id')->unique()->all();
        $this->assertSame([$trust->id], $tenantIds);
    }

    public function test_create_school_with_invalid_payload_returns_422(): void
    {
        $tenant = Tenant::factory()->trust()->create();
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/schools', [
            'name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_deactivate_school_omits_it_from_active_list_but_show_still_works(): void
    {
        $tenant = Tenant::factory()->create();
        $active = School::factory()->forTenant($tenant)->create(['name' => 'Active School']);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create(['name' => 'Inactive School']);
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $activeList = $this->actingAs($user)->getJson('/api/v1/schools?active=1');

        $activeList->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['id' => $inactive->id]);

        $show = $this->actingAs($user)->getJson('/api/v1/schools/'.$inactive->id);

        $show->assertOk()
            ->assertJsonPath('data.id', $inactive->id)
            ->assertJsonPath('data.is_active', false);

        $deactivate = $this->actingAs($user)->patchJson('/api/v1/schools/'.$active->id, [
            'is_active' => false,
        ]);

        $deactivate->assertOk()->assertJsonPath('data.is_active', false);

        $after = $this->actingAs($user)->getJson('/api/v1/schools?active=1');
        $after->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_tenant_cohort_flag_persists_and_is_readable(): void
    {
        $tenant = Tenant::factory()->create([
            'cohort_enabled' => false,
            'cohort_label' => null,
        ]);
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $update = $this->actingAs($user)->patchJson('/api/v1/tenant', [
            'cohort_enabled' => true,
            'cohort_label' => 'Year 7 Pilot',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.cohort_enabled', true)
            ->assertJsonPath('data.cohort_label', 'Year 7 Pilot');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'cohort_enabled' => true,
            'cohort_label' => 'Year 7 Pilot',
        ]);

        $show = $this->actingAs($user)->getJson('/api/v1/tenant');

        $show->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.cohort_enabled', true)
            ->assertJsonPath('data.cohort_label', 'Year 7 Pilot');
    }

    public function test_invalid_cohort_payload_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/tenant', [
            'cohort_enabled' => 'not-a-boolean',
            'cohort_label' => str_repeat('x', 300),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cohort_enabled', 'cohort_label']);
    }

    public function test_unauthenticated_school_list_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/schools');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertTrue(
            str_contains($response->headers->get('content-type', ''), 'application/json'),
            'Unauthenticated API responses must be JSON, not the Blade SPA shell'
        );
        $this->assertStringNotContainsString('<div id="app">', $response->getContent());
    }

    public function test_store_ignores_injected_tenant_id_and_uses_current_tenant(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $userA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $response = $this->actingAs($userA)->postJson('/api/v1/schools', [
            'name' => 'Owned By A',
            'tenant_id' => $tenantB->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenantA->id)
            ->assertJsonMissing(['tenant_id' => $tenantB->id]);

        $this->assertDatabaseHas('schools', [
            'name' => 'Owned By A',
            'tenant_id' => $tenantA->id,
        ]);
        $this->assertDatabaseMissing('schools', [
            'name' => 'Owned By A',
            'tenant_id' => $tenantB->id,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenants(): array
    {
        return [
            Tenant::factory()->school()->create(['name' => 'Tenant A']),
            Tenant::factory()->school()->create(['name' => 'Tenant B']),
        ];
    }
}
