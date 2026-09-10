<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditEventAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/audit-events')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_only_current_tenant_events_newest_first(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $older = $this->seedAuditEvent($tenant, $admin, [
            'event_type' => AuditEventType::LoginSuccess,
            'created_at' => now()->subMinute(),
        ]);
        $newer = $this->seedAuditEvent($tenant, $admin, [
            'event_type' => AuditEventType::UserUpdated,
            'resource_type' => 'user',
            'resource_id' => (string) $admin->id,
            'metadata' => ['field' => 'name'],
            'created_at' => now(),
        ]);
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->forTenant($foreignTenant)->create();
        $this->seedAuditEvent($foreignTenant, $foreignUser);

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-events');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.event_type', AuditEventType::UserUpdated->value)
            ->assertJsonPath('data.0.user.name', $admin->name)
            ->assertJsonPath('data.0.metadata.field', 'name')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.per_page', 50);

        $this->assertSame(
            [$newer->id, $older->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_tenant_admin_can_search_events(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $matching = $this->seedAuditEvent($tenant, $admin, [
            'event_type' => AuditEventType::SchoolUpdated,
            'resource_type' => 'school',
            'resource_id' => 'school_123',
        ]);
        $this->seedAuditEvent($tenant, $admin, [
            'event_type' => AuditEventType::LoginSuccess,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/audit-events?q=school_123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_teacher_cannot_list_audit_events(): void
    {
        $tenant = Tenant::factory()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($teacher)
            ->getJson('/api/v1/audit-events')
            ->assertForbidden();
    }

    public function test_tenant_admin_can_view_an_event_in_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $event = $this->seedAuditEvent($tenant, $admin, [
            'event_type' => AuditEventType::UserCreated,
            'resource_type' => 'user',
            'resource_id' => (string) $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/audit-events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.user.email', $admin->email);
    }

    public function test_cross_tenant_event_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->forTenant($tenantA)->create();
        $event = $this->seedAuditEvent($tenantA, $userA);
        $tenantB = Tenant::factory()->create();
        $adminB = User::factory()->forTenant($tenantB)->tenantAdmin()->create();

        $this->actingAs($adminB)
            ->getJson('/api/v1/audit-events/'.$event->id)
            ->assertNotFound();
    }

    public function test_audit_event_administration_has_no_mutation_endpoints(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $event = $this->seedAuditEvent($tenant, $admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/audit-events', [])
            ->assertMethodNotAllowed();
        $this->actingAs($admin)
            ->patchJson('/api/v1/audit-events/'.$event->id, [])
            ->assertMethodNotAllowed();
        $this->actingAs($admin)
            ->deleteJson('/api/v1/audit-events/'.$event->id)
            ->assertMethodNotAllowed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedAuditEvent(Tenant $tenant, User $user, array $overrides = []): AuditEvent
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $event = new AuditEvent([
            'event_type' => AuditEventType::LoginSuccess,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            ...$overrides,
        ]);
        $event->forceFill(['created_at' => $createdAt])->save();

        return $event;
    }
}
