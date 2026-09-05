<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventImmutableException;
use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_update_is_blocked_and_leaves_row_unchanged(): void
    {
        $event = $this->seedAuditEvent([
            'ip' => '203.0.113.10',
        ]);

        try {
            $event->update(['ip' => '198.51.100.20']);
            $this->fail('Expected AuditEventImmutableException was not thrown.');
        } catch (AuditEventImmutableException $exception) {
            $this->assertSame(
                'Audit events are append-only and cannot be updated.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'ip' => '203.0.113.10',
        ]);
    }

    public function test_model_delete_is_blocked_and_leaves_row_unchanged(): void
    {
        $event = $this->seedAuditEvent();

        try {
            $event->delete();
            $this->fail('Expected AuditEventImmutableException was not thrown.');
        } catch (AuditEventImmutableException $exception) {
            $this->assertSame(
                'Audit events are append-only and cannot be deleted.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
        ]);
    }

    public function test_product_api_has_no_audit_update_or_delete_routes(): void
    {
        $this->assertFalse(Route::has('api.v1.audit.update'));
        $this->assertFalse(Route::has('api.v1.audit.destroy'));

        $this->actingAs($this->tenantAdmin())
            ->patchJson('/api/v1/audit/'.$this->seedAuditEvent()->id, [
                'event_type' => 'purged',
            ])
            ->assertNotFound();

        $this->actingAs($this->tenantAdmin())
            ->deleteJson('/api/v1/audit/'.$this->seedAuditEvent()->id)
            ->assertNotFound();

        $this->actingAs($this->tenantAdmin())
            ->patchJson('/api/v1/audit-events/'.$this->seedAuditEvent()->id, [
                'event_type' => 'purged',
            ])
            ->assertNotFound();

        $this->actingAs($this->tenantAdmin())
            ->deleteJson('/api/v1/audit-events/'.$this->seedAuditEvent()->id)
            ->assertNotFound();
    }

    public function test_user_create_update_deactivate_and_password_reset_emit_audit_events(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $peer = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create();

        $create = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Audited Teacher',
            'email' => 'audited-teacher@example.com',
            'password' => 'password123',
            'role' => Role::Teacher->value,
            'school_ids' => [$school->id],
        ]);

        $create->assertCreated();
        $userId = (string) $create->json('data.id');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::UserCreated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'user',
            'resource_id' => $userId,
        ]);

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$userId, [
            'role' => Role::Senco->value,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::UserUpdated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'user',
            'resource_id' => $userId,
        ]);

        $this->actingAs($admin)->patchJson('/api/v1/users/'.$userId.'/password', [
            'password' => 'password456',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::UserPasswordReset->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'user',
            'resource_id' => $userId,
        ]);

        $this->actingAs($admin)->postJson('/api/v1/users/'.$peer->id.'/deactivate')
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::UserDeactivated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'user',
            'resource_id' => (string) $peer->id,
        ]);
    }

    public function test_school_create_update_and_delete_emit_audit_events(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $create = $this->actingAs($admin)->postJson('/api/v1/schools', [
            'name' => 'Audited School',
        ]);

        $create->assertCreated();
        $schoolId = $create->json('data.id');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'school',
            'resource_id' => $schoolId,
        ]);

        $this->actingAs($admin)->patchJson('/api/v1/schools/'.$schoolId, [
            'name' => 'Renamed School',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SchoolUpdated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'school',
            'resource_id' => $schoolId,
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/schools/'.$schoolId)
            ->assertNoContent();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SchoolDeleted->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'school',
            'resource_id' => $schoolId,
        ]);
    }

    public function test_tenant_cohort_feature_flag_and_sso_mutations_emit_audit_events(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->patchJson('/api/v1/tenant', [
            'cohort_enabled' => true,
            'cohort_label' => 'Pilot Cohort',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::TenantCohortUpdated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'tenant',
            'resource_id' => $tenant->id,
        ]);

        $this->actingAs($admin)->patchJson('/api/v1/tenant/feature-flags', [
            'key' => FeatureFlagKey::TrustDashboard->value,
            'enabled' => true,
        ])->assertOk();

        $flagAudit = AuditEvent::query()
            ->where('event_type', AuditEventType::FeatureFlagUpdated)
            ->where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($flagAudit);
        $this->assertSame($admin->id, $flagAudit->user_id);
        $this->assertSame('tenant', $flagAudit->resource_type);
        $this->assertSame($tenant->id, $flagAudit->resource_id);
        $this->assertSame([
            'flag_key' => FeatureFlagKey::TrustDashboard->value,
            'enabled' => true,
        ], $flagAudit->metadata);

        $this->actingAs($admin)->patchJson('/api/v1/tenant/sso', [
            'sso_enabled' => true,
            'sso_provider' => 'oidc-placeholder',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::TenantSsoUpdated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'tenant',
            'resource_id' => $tenant->id,
        ]);
    }

    public function test_cross_tenant_mutation_audit_uses_actor_tenant_only(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $this->actingAs($adminA)->postJson('/api/v1/schools', [
            'name' => 'Tenant A School',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'tenant_id' => $tenantA->id,
            'user_id' => $adminA->id,
        ]);

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'tenant_id' => $tenantB->id,
        ]);
    }

    public function test_audit_writer_rejects_evidence_payload_and_nested_bodies(): void
    {
        $admin = $this->tenantAdmin();
        $request = Request::create('/api/v1/schools', 'POST');
        $request->setUserResolver(fn () => $admin);

        $writer = app(AuditWriter::class);

        try {
            $writer->record(
                AuditEventType::SchoolCreated,
                $request,
                $admin,
                metadata: [
                    'evidence' => ['body' => 'full pupil narrative must never be logged'],
                ],
            );
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Evidence', $exception->getMessage());
        }

        try {
            $writer->record(
                AuditEventType::SchoolCreated,
                $request,
                $admin,
                metadata: [
                    'payload' => ['nested' => true],
                ],
            );
            $this->fail('Expected InvalidArgumentException was not thrown for nested metadata.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('scalars', $exception->getMessage());
        }

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'user_id' => $admin->id,
        ]);

        $event = $writer->record(
            AuditEventType::SchoolCreated,
            $request,
            $admin,
            resourceType: 'school',
            resourceId: '01h00000000000000000000000',
            metadata: [
                'note' => 'scalar-only',
            ],
        );

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'event_type' => AuditEventType::SchoolCreated->value,
            'tenant_id' => $admin->tenant_id,
            'user_id' => $admin->id,
            'resource_type' => 'school',
            'resource_id' => '01h00000000000000000000000',
        ]);

        $this->assertSame(['note' => 'scalar-only'], $event->metadata);
        $this->assertArrayNotHasKey('evidence', $event->getAttributes());
    }

    public function test_audit_writer_rejects_reserved_metadata_keys(): void
    {
        $admin = $this->tenantAdmin();
        $request = Request::create('/api/v1/schools', 'POST');
        $writer = app(AuditWriter::class);

        try {
            $writer->record(
                AuditEventType::SchoolCreated,
                $request,
                $admin,
                metadata: [
                    'event_type' => 'forged.type',
                    'note' => 'should-not-persist',
                ],
            );
            $this->fail('Expected InvalidArgumentException was not thrown for reserved metadata keys.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('reserved key', $exception->getMessage());
        }

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_writer_rejects_tenant_mismatch_with_user(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->forTenant($tenantA)->create();
        $request = Request::create('/api/v1/schools', 'POST');
        $writer = app(AuditWriter::class);

        try {
            $writer->record(
                AuditEventType::SchoolCreated,
                $request,
                $userA,
                tenantId: $tenantB->id,
            );
            $this->fail('Expected InvalidArgumentException was not thrown for tenant mismatch.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tenant_id does not match', $exception->getMessage());
        }

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'user_id' => $userA->id,
        ]);
    }

    public function test_created_at_is_not_mass_assignable_outside_writer(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();
        $forgedCreatedAt = now()->subYear()->startOfSecond();

        $ignoredOnConstruct = new AuditEvent([
            'event_type' => AuditEventType::LoginSuccess,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => $forgedCreatedAt,
        ]);

        $this->assertNull($ignoredOnConstruct->created_at);

        $massAssigned = AuditEvent::query()->create([
            'event_type' => AuditEventType::Logout,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => $forgedCreatedAt->toDateTimeString(),
        ]);

        $this->assertNotNull($massAssigned->fresh()->created_at);
        $this->assertTrue(
            $massAssigned->fresh()->created_at->greaterThan($forgedCreatedAt->copy()->addMonths(6)),
            'Mass-assigned forged created_at must not persist; DB default / writer forceFill owns the timestamp.'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedAuditEvent(array $overrides = []): AuditEvent
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $event = new AuditEvent([
            'event_type' => AuditEventType::LoginSuccess,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'resource_type' => null,
            'resource_id' => null,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            ...$overrides,
        ]);
        $event->forceFill([
            'created_at' => $createdAt,
        ])->save();

        return $event;
    }

    private function tenantAdmin(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->forTenant($tenant)->tenantAdmin()->create();
    }
}
