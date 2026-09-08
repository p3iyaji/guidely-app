<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reporting\SchoolReport;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_gets_status_counts_matching_in_scope_pupils(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $ready = $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $gaps = $this->pupilWithStatus($school, DocumentationStatus::Gaps, 'Jordan', 'Lee');
        $uncovered = $this->pupilWithStatus($school, DocumentationStatus::Uncovered, 'Sam', 'Patel');
        $notStarted = $this->pupilWithStatus($school, DocumentationStatus::NotStarted, 'Alex', 'Wright');

        $response = $this->actingAs($senco)->getJson('/api/v1/school-report');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 4)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.gaps', 1)
            ->assertJsonPath('data.review_cycles_due', 0)
            ->assertJsonPath('data.window_days', 30)
            ->assertJsonPath('data.by_status.ready', 1)
            ->assertJsonPath('data.by_status.gaps', 1)
            ->assertJsonPath('data.by_status.uncovered', 1)
            ->assertJsonPath('data.by_status.not-started', 1)
            ->assertJsonPath('data.by_status.evaluating', 0);

        $this->assertSame(4, array_sum($response->json('data.by_status')));
        $this->assertSame(
            [$gaps->id, $ready->id, $uncovered->id, $notStarted->id],
            array_column($response->json('data.pupils'), 'id'),
        );
        $this->assertForbiddenKpiFields($response);
    }

    public function test_school_leader_gets_the_same_report_and_drill_lists(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = $this->pupilWithStatus($school, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-20')->create();

        $response = $this->actingAs($leader)->getJson('/api/v1/school-report');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.gaps', 1)
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonPath('data.pupils.0.id', $pupil->id)
            ->assertJsonPath('data.pupils.0.given_name', 'Maya')
            ->assertJsonPath('data.pupils.0.family_name', 'Okonkwo')
            ->assertJsonPath('data.pupils.0.documentation_status', DocumentationStatus::Gaps->value)
            ->assertJsonPath('data.cycles_due.0.id', $cycle->id)
            ->assertJsonPath('data.cycles_due.0.given_name', 'Maya')
            ->assertJsonPath('data.cycles_due.0.family_name', 'Okonkwo')
            ->assertJsonPath('data.cycles_due.0.due_on', '2026-09-20')
            ->assertJsonPath('data.cycles_due.0.type_label', 'Annual Review');

        $this->assertArrayNotHasKey('evidence', $response->json('data'));
        $this->assertForbiddenKpiFields($response);
    }

    public function test_evaluating_pupil_is_counted_in_flight_not_as_ready(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $this->pupilWithStatus($school, DocumentationStatus::Evaluating, 'Riley', 'Chen');
        $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');

        $response = $this->actingAs($senco)->getJson('/api/v1/school-report');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 2)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.by_status.evaluating', 1)
            ->assertJsonPath('data.by_status.ready', 1);

        $this->assertSame(2, array_sum($response->json('data.by_status')));
    }

    #[DataProvider('forbiddenReportRoles')]
    public function test_returns_403_forbidden_for_roles_that_cannot_view(string $roleFactory): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $user = User::factory()->forTenant($tenant)->{$roleFactory}()->create();
        $user->schools()->attach($school->id);
        $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');

        $this->assertForbidden($this->actingAs($user)->getJson('/api/v1/school-report'));
        $this->assertFalse($user->can('view', SchoolReport::class));
    }

    public function test_senco_and_school_leader_can_view_school_report(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();

        $this->assertTrue($senco->can('view', SchoolReport::class));
        $this->assertTrue($leader->can('view', SchoolReport::class));
    }

    public function test_trust_send_lead_gets_one_enabled_school_when_school_id_is_set(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $oak = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $ridge = School::factory()->forTenant($tenant)->create(['name' => 'Ridge Academy']);
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();
        $visible = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $ridgePupil = $this->pupilWithStatus($ridge, DocumentationStatus::Gaps, 'Jordan', 'Lee');
        $oakCycle = ReviewCycle::factory()->forPupil($visible)->open()->dueOn('2026-09-20')->create();
        ReviewCycle::factory()->forPupil($ridgePupil)->open()->dueOn('2026-09-20')->create();

        $response = $this->actingAs($lead)->getJson('/api/v1/school-report?school_id='.$oak->id);

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonPath('data.pupils.0.id', $visible->id)
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonPath('data.cycles_due.0.id', $oakCycle->id);

        $this->assertCount(1, $response->json('data.cycles_due'));
        $this->assertTrue($lead->can('view', SchoolReport::class));
    }

    public function test_trust_send_lead_array_school_id_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $oak = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $ridge = School::factory()->forTenant($tenant)->create(['name' => 'Ridge Academy']);
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();
        $oakPupil = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $ridgePupil = $this->pupilWithStatus($ridge, DocumentationStatus::Gaps, 'Jordan', 'Lee');

        $response = $this->actingAs($lead)->getJson(
            '/api/v1/school-report?'.http_build_query(['school_id' => [$oak->id]]),
        );

        $response->assertNotFound();
        $this->assertStringNotContainsString($oakPupil->id, $response->getContent());
        $this->assertStringNotContainsString($ridgePupil->id, $response->getContent());
        $this->assertStringNotContainsString('Maya', $response->getContent());
        $this->assertStringNotContainsString('Jordan', $response->getContent());
    }

    public function test_trust_send_lead_without_school_id_receives_403(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();

        $this->assertForbidden($this->actingAs($lead)->getJson('/api/v1/school-report'));
        $this->assertTrue($lead->can('view', SchoolReport::class));
    }

    public function test_trust_send_lead_other_tenant_school_id_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $foreignSchool = School::factory()->forTenant($other)->create();
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();

        $this->actingAs($lead)
            ->getJson('/api/v1/school-report?school_id='.$foreignSchool->id)
            ->assertNotFound();
    }

    public function test_trust_send_lead_inactive_school_id_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create();
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();
        $this->pupilWithStatus($inactive, DocumentationStatus::Gaps, 'Hidden', 'Pupil');

        $this->actingAs($lead)
            ->getJson('/api/v1/school-report?school_id='.$inactive->id)
            ->assertNotFound();
    }

    public function test_trust_executive_cannot_view_school_report_when_flag_is_on(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $school = School::factory()->forTenant($tenant)->create();
        $executive = User::factory()->forTenant($tenant)->trustExecutive()->create();

        $this->assertForbidden(
            $this->actingAs($executive)->getJson('/api/v1/school-report?school_id='.$school->id),
        );
        $this->assertFalse($executive->can('view', SchoolReport::class));
    }

    public function test_omits_other_school_pupils_from_counts(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $visible = $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($otherSchool, DocumentationStatus::Gaps, 'Other', 'School');

        $response = $this->actingAs($senco)->getJson('/api/v1/school-report');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonPath('data.pupils.0.id', $visible->id);
    }

    public function test_combines_pupils_across_accessible_schools(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $secondSchool = School::factory()->forTenant($tenant)->create();
        $senco->schools()->attach($secondSchool->id);
        $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($secondSchool, DocumentationStatus::Gaps, 'Jordan', 'Lee');

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 2)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.gaps', 1);
    }

    public function test_empty_school_returns_zero_counts_and_empty_lists(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $response = $this->actingAs($senco)->getJson('/api/v1/school-report');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 0)
            ->assertJsonPath('data.ready', 0)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonPath('data.review_cycles_due', 0)
            ->assertJsonPath('data.by_status.ready', 0)
            ->assertJsonPath('data.by_status.gaps', 0)
            ->assertJsonPath('data.by_status.uncovered', 0)
            ->assertJsonPath('data.by_status.not-started', 0)
            ->assertJsonPath('data.by_status.evaluating', 0)
            ->assertJsonPath('data.pupils', [])
            ->assertJsonPath('data.cycles_due', []);
    }

    public function test_window_7_excludes_cycle_due_in_ten_days_and_window_30_includes(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-17')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report?window=7')
            ->assertOk()
            ->assertJsonPath('data.window_days', 7)
            ->assertJsonPath('data.review_cycles_due', 0)
            ->assertJsonPath('data.cycles_due', []);

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report?window=30')
            ->assertOk()
            ->assertJsonPath('data.window_days', 30)
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonPath('data.cycles_due.0.id', $cycle->id);
    }

    public function test_omits_sibling_school_cycles_from_cycles_due(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $pupil = $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $otherPupil = $this->pupilWithStatus($otherSchool, DocumentationStatus::Gaps, 'Other', 'School');
        $visible = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-10')->create();
        ReviewCycle::factory()->forPupil($otherPupil)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report')
            ->assertOk()
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonCount(1, 'data.cycles_due')
            ->assertJsonPath('data.cycles_due.0.id', $visible->id);
    }

    public function test_closed_cycles_and_later_dues_are_omitted(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = $this->pupilWithStatus($school, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $open = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-14')->create();
        ReviewCycle::factory()->forPupil($pupil)->closed()->dueOn('2026-09-10')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-10-08')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report?window=30')
            ->assertOk()
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonPath('data.cycles_due.0.id', $open->id);
    }

    public function test_returns_422_when_window_is_invalid(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $this->actingAs($senco)
            ->getJson('/api/v1/school-report?window=14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['window'])
            ->assertJsonPath('errors.window.0', 'Window must be 7, 30, or 90 days.');
    }

    public function test_guest_receives_401(): void
    {
        $this->getJson('/api/v1/school-report')->assertUnauthorized();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenReportRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'support staff' => ['supportStaff'],
            'tenant admin' => ['tenantAdmin'],
            'trust send lead' => ['trustSendLead'],
            'trust executive' => ['trustExecutive'],
        ];
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

    private function assertForbiddenKpiFields(TestResponse $response): void
    {
        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach (['attendance', 'budget', 'curriculum', 'output_count', 'outputs', 'documentation_outputs'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }

        $this->assertStringNotContainsString('attendance', strtolower($response->getContent()));
        $this->assertStringNotContainsString('budget', strtolower($response->getContent()));
        $this->assertStringNotContainsString('curriculum', strtolower($response->getContent()));
    }

    private function assertForbidden(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndSenco(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }

    private function enableTrustDashboard(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);
    }
}
