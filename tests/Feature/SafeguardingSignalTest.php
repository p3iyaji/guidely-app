<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SafeguardingSeverity;
use App\Domain\Pupils\SafeguardingSignal;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeguardingSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_cannot_upsert_when_flag_is_off(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $senco = $this->sencoFor($oak);
        $pupil = Pupil::factory()->forSchool($oak)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Medium->value,
            ])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'safeguarding_ingest',
            ]);

        $this->assertDatabaseCount('safeguarding_signals', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::SafeguardingSignalUpserted->value,
        ]);
    }

    public function test_senco_upserts_present_medium_then_lists_and_can_raise_severity(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $this->enableIngest($tenant);
        $senco = $this->sencoFor($oak);
        $pupil = Pupil::factory()->forSchool($oak)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);

        $first = $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Medium->value,
            ]);

        $first->assertOk()
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.present', true)
            ->assertJsonPath('data.severity', 'medium')
            ->assertJsonPath('data.given_name', 'Maya');
        $this->assertArrayNotHasKey('notes', $first->json('data'));
        $this->assertArrayNotHasKey('case_note', $first->json('data'));

        $this->assertDatabaseCount('safeguarding_signals', 1);
        $this->assertSame(
            1,
            AuditEvent::query()->where('event_type', AuditEventType::SafeguardingSignalUpserted)->count(),
        );

        $this->actingAs($senco)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.severity', 'medium')
            ->assertJsonPath('data.0.school_id', $oak->id);

        $second = $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::High->value,
            ]);

        $second->assertOk()->assertJsonPath('data.severity', 'high');
        $this->assertDatabaseCount('safeguarding_signals', 1);

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.present', false)
            ->assertJsonPath('data.severity', null);

        $this->assertNull(SafeguardingSignal::withoutGlobalScope('tenant')->first()?->severity);

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => false,
                'severity' => SafeguardingSeverity::Low->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['severity']);
        $this->assertDatabaseCount('safeguarding_signals', 1);
        $this->assertNull(SafeguardingSignal::withoutGlobalScope('tenant')->first()?->severity);
        $this->assertSame(
            3,
            AuditEvent::query()->where('event_type', AuditEventType::SafeguardingSignalUpserted)->count(),
        );
    }

    public function test_notes_payload_is_rejected_and_does_not_change_row(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $this->enableIngest($tenant);
        $senco = $this->sencoFor($oak);
        $pupil = Pupil::factory()->forSchool($oak)->create();

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Low->value,
                'notes' => 'Free-text case note',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);

        $this->assertDatabaseCount('safeguarding_signals', 0);
    }

    public function test_present_true_requires_severity(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $this->enableIngest($tenant);
        $senco = $this->sencoFor($oak);
        $pupil = Pupil::factory()->forSchool($oak)->create();

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['severity']);
    }

    public function test_school_leader_can_upsert_in_scope_and_teacher_cannot(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $this->enableIngest($tenant);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($oak->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($oak->id);
        $pupil = Pupil::factory()->forSchool($oak)->create();

        $this->actingAs($leader)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Low->value,
            ])
            ->assertOk();

        $this->actingAs($leader)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($teacher)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->actingAs($teacher)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Low->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_trust_send_lead_cannot_list_signals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableIngest($tenant);
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();

        $this->actingAs($lead)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_senco_cannot_upsert_or_list_other_school_pupil(): void
    {
        [$tenant, $oak] = $this->tenantWithOak();
        $this->enableIngest($tenant);
        $ridge = School::factory()->forTenant($tenant)->create(['name' => 'Ridge Academy']);
        $senco = $this->sencoFor($oak);
        $other = Pupil::factory()->forSchool($ridge)->create([
            'given_name' => 'Hidden',
            'family_name' => 'Pupil',
        ]);
        $this->actingAs($this->sencoFor($ridge))
            ->putJson("/api/v1/pupils/{$other->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::High->value,
            ])
            ->assertOk();

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$other->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Low->value,
            ])
            ->assertForbidden();

        $this->actingAs($senco)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_school_pupil_is_omitted_and_put_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableIngest($tenant);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create(['name' => 'Closed Academy']);
        $senco = $this->sencoFor($inactive);
        $pupil = Pupil::factory()->forSchool($inactive)->create();

        $this->actingAs($senco)
            ->putJson("/api/v1/pupils/{$pupil->id}/safeguarding-signal", [
                'present' => true,
                'severity' => SafeguardingSeverity::Medium->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('safeguarding_signals', 0);
        $this->actingAs($senco)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_guest_cannot_list_signals(): void
    {
        $this->getJson('/api/v1/safeguarding-signals')->assertUnauthorized();
    }

    public function test_tenant_admin_cannot_list_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/safeguarding-signals')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'safeguarding_ingest',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School}
     */
    private function tenantWithOak(): array
    {
        $tenant = Tenant::factory()->create();
        $oak = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);

        return [$tenant, $oak];
    }

    private function sencoFor(School $school): User
    {
        $tenant = Tenant::query()->findOrFail($school->tenant_id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return $senco;
    }

    private function enableIngest(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::SafeguardingIngest->value)
            ->update(['enabled' => true]);
    }
}
