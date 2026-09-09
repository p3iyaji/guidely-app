<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reporting\ComplianceAlert;
use App\Domain\Reporting\ComplianceAlertMetric;
use App\Domain\Reporting\ComplianceAlertScope;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ComplianceAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_cannot_list_alerts_when_flag_is_off(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $senco = $this->sencoFor($oak);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->actingAs($senco)
            ->getJson('/api/v1/compliance-alerts')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'compliance_alerts',
            ]);

        $this->assertDatabaseCount('compliance_alerts', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::ComplianceAlertCreated->value,
        ]);
    }

    public function test_evaluate_compliance_alerts_is_scheduled_hourly_in_london(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('guidely:evaluate-compliance-alerts', Artisan::output());

        $event = collect(app(Schedule::class)->events())->first(
            fn ($scheduled): bool => str_contains((string) $scheduled->command, 'guidely:evaluate-compliance-alerts'),
        );

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertSame('Europe/London', $event->timezone);
    }

    public function test_snapshot_trust_indicators_is_scheduled_monthly_in_london(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('guidely:snapshot-trust-indicators', Artisan::output());

        $event = collect(app(Schedule::class)->events())->first(
            fn ($scheduled): bool => str_contains((string) $scheduled->command, 'guidely:snapshot-trust-indicators'),
        );

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 1 1 * *', $event->expression);
        $this->assertSame('Europe/London', $event->timezone);
    }

    public function test_evaluate_opens_school_gap_density_alert_and_create_audit_once(): void
    {
        [$tenant, $oak, $ridge] = $this->twoActiveSchools();
        $this->enableComplianceAlerts($tenant);
        $senco = $this->sencoFor($oak);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($ridge, DocumentationStatus::Ready, 'Sam', 'Patel');
        $this->pupilWithStatus($ridge, DocumentationStatus::Ready, 'Alex', 'Ng');

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('compliance_alerts', 1);
        $alert = ComplianceAlert::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($alert);
        $this->assertSame(ComplianceAlertScope::School, $alert->scope);
        $this->assertSame($oak->id, $alert->school_id);
        $this->assertSame(ComplianceAlertMetric::GapDensity, $alert->metric);
        $this->assertTrue($alert->is_open);
        $this->assertEqualsWithDelta(0.5, $alert->threshold_value, 0.0001);
        $this->assertEqualsWithDelta(1.0, $alert->observed_value, 0.0001);

        $this->assertSame(
            1,
            AuditEvent::query()->where('event_type', AuditEventType::ComplianceAlertCreated)->count(),
        );

        $list = $this->actingAs($senco)->getJson('/api/v1/compliance-alerts');
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.school_id', $oak->id)
            ->assertJsonPath('data.0.metric', 'gap_density')
            ->assertJsonPath('data.0.citation', 'Gap density Indicator: observed 100% meets configured threshold 50%.');
        $this->assertStringNotContainsString('Maya', $list->getContent());
        $this->assertStringNotContainsString('Okonkwo', $list->getContent());
        $this->assertStringNotContainsString('AI found', $list->getContent());

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('compliance_alerts', 1);
        $this->assertSame(
            1,
            AuditEvent::query()->where('event_type', AuditEventType::ComplianceAlertCreated)->count(),
        );
    }

    public function test_evaluate_resolves_alert_when_gap_density_falls_below_threshold(): void
    {
        [$tenant, $oak, $ridge] = $this->twoActiveSchools();
        $this->enableComplianceAlerts($tenant);
        $senco = $this->sencoFor($oak);
        $pupil = $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($ridge, DocumentationStatus::Ready, 'Sam', 'Patel');
        $this->pupilWithStatus($ridge, DocumentationStatus::Ready, 'Alex', 'Ng');

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $pupil->forceFill(['documentation_status' => DocumentationStatus::Ready])->save();

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('compliance_alerts', [
            'school_id' => $oak->id,
            'metric' => ComplianceAlertMetric::GapDensity->value,
            'is_open' => false,
        ]);
        $this->actingAs($senco)
            ->getJson('/api/v1/compliance-alerts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertSame(
            1,
            AuditEvent::query()->where('event_type', AuditEventType::ComplianceAlertResolved)->count(),
        );
    }

    public function test_trust_lateness_alert_is_visible_to_executive_without_school_id(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $this->enableTrustDashboard($tenant);
        $this->enableComplianceAlerts($tenant);
        $executive = User::factory()->forTenant($tenant)->trustExecutive()->create();
        $pupil = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn(
            now('Europe/London')->subDay()->toDateString(),
        )->create();

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $response = $this->actingAs($executive)->getJson('/api/v1/compliance-alerts');
        $response->assertOk()
            ->assertJsonPath('data.0.scope', 'trust')
            ->assertJsonPath('data.0.metric', 'lateness_rate')
            ->assertJsonPath('data.0.school_id', null);
        $this->assertStringNotContainsString('Maya', $response->getContent());
        $this->assertSame(
            0,
            collect($response->json('data'))->whereNotNull('school_id')->count(),
        );
    }

    public function test_send_lead_sees_school_and_trust_alerts_executive_sees_trust_only(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $this->enableTrustDashboard($tenant);
        $this->enableComplianceAlerts($tenant);
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();
        $executive = User::factory()->forTenant($tenant)->trustExecutive()->create();
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $pupil = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Sam', 'Patel');
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn(
            now('Europe/London')->subDay()->toDateString(),
        )->create();

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $leadList = $this->actingAs($lead)->getJson('/api/v1/compliance-alerts');
        $leadList->assertOk();
        $leadScopes = collect($leadList->json('data'))->pluck('scope')->unique()->sort()->values()->all();
        $this->assertSame(['school', 'trust'], $leadScopes);
        $this->assertGreaterThan(
            0,
            collect($leadList->json('data'))->where('school_id', $oak->id)->count(),
        );

        $execList = $this->actingAs($executive)->getJson('/api/v1/compliance-alerts');
        $execList->assertOk();
        $this->assertSame(
            ['trust'],
            collect($execList->json('data'))->pluck('scope')->unique()->values()->all(),
        );
        $this->assertSame(
            0,
            collect($execList->json('data'))->whereNotNull('school_id')->count(),
        );
    }

    public function test_senco_does_not_see_alerts_for_unassigned_school(): void
    {
        [$tenant, $oak, $ridge] = $this->twoActiveSchools();
        $this->enableComplianceAlerts($tenant);
        $senco = $this->sencoFor($oak);
        $this->pupilWithStatus($ridge, DocumentationStatus::Gaps, 'Hidden', 'Pupil');

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->actingAs($senco)
            ->getJson('/api/v1/compliance-alerts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_school_over_threshold_does_not_open_school_alert(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableComplianceAlerts($tenant);
        $active = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create(['name' => 'Closed Academy']);
        $senco = $this->sencoFor($active);
        $this->pupilWithStatus($inactive, DocumentationStatus::Gaps, 'Hidden', 'Pupil');

        $this->artisan('guidely:evaluate-compliance-alerts', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseMissing('compliance_alerts', [
            'school_id' => $inactive->id,
        ]);
        $this->actingAs($senco)
            ->getJson('/api/v1/compliance-alerts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_teacher_is_forbidden_and_guest_is_unauthorized(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $this->enableComplianceAlerts($tenant);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($oak->id);

        $this->actingAs($teacher)
            ->getJson('/api/v1/compliance-alerts')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    public function test_guest_cannot_list_alerts(): void
    {
        $this->getJson('/api/v1/compliance-alerts')->assertUnauthorized();
    }

    public function test_tenant_admin_cannot_patch_threshold_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/compliance-alert-thresholds', [
                'school_id' => null,
                'metric' => ComplianceAlertMetric::GapDensity->value,
                'threshold' => 0.4,
            ])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'compliance_alerts',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: School}
     */
    private function twoActiveSchools(): array
    {
        $tenant = Tenant::factory()->create();
        $oak = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $ridge = School::factory()->forTenant($tenant)->create(['name' => 'Ridge Academy']);

        return [$tenant, $oak, $ridge];
    }

    private function sencoFor(School $school): User
    {
        $tenant = Tenant::query()->findOrFail($school->tenant_id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return $senco;
    }

    private function enableComplianceAlerts(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::ComplianceAlerts->value)
            ->update(['enabled' => true]);
    }

    private function enableTrustDashboard(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);
    }

    private function pupilWithStatus(
        School $school,
        DocumentationStatus $status,
        string $givenName,
        string $familyName,
    ): Pupil {
        return Pupil::factory()->forSchool($school)->create([
            'given_name' => $givenName,
            'family_name' => $familyName,
            'documentation_status' => $status,
        ]);
    }
}
