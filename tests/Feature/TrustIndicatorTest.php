<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reporting\TrustIndicator;
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

class TrustIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_trust_send_lead_gets_tenant_totals_and_per_school_rows(): void
    {
        $this->travelTo('2026-09-08 12:00:00');

        [$tenant, $oak, $ridge] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);
        $maya = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Jordan', 'Lee');
        $this->pupilWithStatus($ridge, DocumentationStatus::Uncovered, 'Sam', 'Patel');
        $this->pupilWithStatus($ridge, DocumentationStatus::NotStarted, 'Alex', 'Wright');
        ReviewCycle::factory()->forPupil($maya)->open()->dueOn('2026-09-07')->create();
        ReviewCycle::factory()->forPupil($maya)->open()->dueOn('2026-09-20')->create();

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 4)
            ->assertJsonPath('data.gaps', 1)
            ->assertJsonPath('data.gap_density', 0.25)
            ->assertJsonPath('data.open_cycles', 2)
            ->assertJsonPath('data.overdue_open_cycles', 1)
            ->assertJsonPath('data.lateness_rate', 0.5)
            ->assertJsonPath('data.by_status.ready', 1)
            ->assertJsonPath('data.by_status.gaps', 1)
            ->assertJsonPath('data.by_status.uncovered', 1)
            ->assertJsonPath('data.by_status.not-started', 1)
            ->assertJsonPath('data.by_status.evaluating', 0)
            ->assertJsonPath('data.schools.0.school_id', $oak->id)
            ->assertJsonPath('data.schools.0.name', 'Oak Academy')
            ->assertJsonPath('data.schools.0.pupils_in_scope', 2)
            ->assertJsonPath('data.schools.0.gaps', 1)
            ->assertJsonPath('data.schools.0.gap_density', 0.5)
            ->assertJsonPath('data.schools.0.open_cycles', 2)
            ->assertJsonPath('data.schools.0.overdue_open_cycles', 1)
            ->assertJsonPath('data.schools.0.lateness_rate', 0.5)
            ->assertJsonPath('data.schools.1.school_id', $ridge->id)
            ->assertJsonPath('data.schools.1.name', 'Ridge Academy')
            ->assertJsonPath('data.schools.1.pupils_in_scope', 2)
            ->assertJsonPath('data.schools.1.gaps', 0)
            ->assertJsonPath('data.schools.1.gap_density', 0)
            ->assertJsonPath('data.schools.1.open_cycles', 0)
            ->assertJsonPath('data.schools.1.lateness_rate', 0);

        $this->assertCount(2, $response->json('data.schools'));
        $this->assertSame(4, array_sum($response->json('data.by_status')));
        $this->assertArrayNotHasKey('pupils', $response->json('data'));
        $this->assertArrayNotHasKey('placeholder', $response->json());
        $this->assertArrayNotHasKey('placeholder', $response->json('data'));
        $this->assertStringNotContainsString('Maya', $response->getContent());
        $this->assertStringNotContainsString('Okonkwo', $response->getContent());
        $this->assertForbiddenFields($response);
        $this->assertTrue($lead->can('view', TrustIndicator::class));
    }

    public function test_trust_executive_gets_tenant_totals_without_schools_key(): void
    {
        $this->travelTo('2026-09-08 12:00:00');

        [$tenant, $oak] = $this->twoActiveSchools();
        $executive = $this->trustExecutive($tenant);
        $maya = $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        ReviewCycle::factory()->forPupil($maya)->open()->dueOn('2026-09-01')->create();

        $response = $this->actingAs($executive)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.gaps', 1)
            ->assertJsonPath('data.gap_density', 1)
            ->assertJsonPath('data.open_cycles', 1)
            ->assertJsonPath('data.overdue_open_cycles', 1)
            ->assertJsonPath('data.lateness_rate', 1);

        $this->assertArrayNotHasKey('schools', $response->json('data'));
        $this->assertStringNotContainsString('Maya', $response->getContent());
        $this->assertTrue($executive->can('view', TrustIndicator::class));
    }

    public function test_omits_inactive_school_from_totals_and_school_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $active = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create(['name' => 'Closed Academy']);
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($active, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $hidden = $this->pupilWithStatus($inactive, DocumentationStatus::Gaps, 'Hidden', 'Pupil');
        ReviewCycle::factory()->forPupil($hidden)->open()->dueOn('2020-01-01')->create();

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonPath('data.open_cycles', 0)
            ->assertJsonCount(1, 'data.schools')
            ->assertJsonPath('data.schools.0.school_id', $active->id)
            ->assertJsonMissing(['school_id' => $inactive->id]);

        $this->assertStringNotContainsString('Closed Academy', $response->getContent());
        $this->assertStringNotContainsString('Hidden', $response->getContent());
    }

    public function test_returns_403_feature_not_available_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $lead = User::factory()->forTenant($tenant)->trustSendLead()->create();

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'trust_dashboard',
            ]);
    }

    #[DataProvider('forbiddenDashboardRoles')]
    public function test_returns_403_forbidden_for_roles_that_cannot_view(string $roleFactory): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $user = User::factory()->forTenant($tenant)->{$roleFactory}()->create();

        $this->assertForbidden($this->actingAs($user)->getJson('/api/v1/trust-dashboard'));
        $this->assertFalse($user->can('view', TrustIndicator::class));
    }

    public function test_empty_trust_returns_zero_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $lead = $this->trustSendLead($tenant);

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 0)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonPath('data.gap_density', 0)
            ->assertJsonPath('data.open_cycles', 0)
            ->assertJsonPath('data.overdue_open_cycles', 0)
            ->assertJsonPath('data.lateness_rate', 0)
            ->assertJsonPath('data.by_status.ready', 0)
            ->assertJsonPath('data.by_status.gaps', 0)
            ->assertJsonPath('data.by_status.uncovered', 0)
            ->assertJsonPath('data.by_status.not-started', 0)
            ->assertJsonPath('data.by_status.evaluating', 0)
            ->assertJsonPath('data.schools', []);
    }

    public function test_guest_receives_401(): void
    {
        $this->getJson('/api/v1/trust-dashboard')->assertUnauthorized();
    }

    public function test_due_today_is_not_overdue(): void
    {
        $this->travelTo('2026-09-08 12:00:00');

        [$tenant, $oak] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);
        $pupil = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-08')->create();

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_cycles', 1)
            ->assertJsonPath('data.overdue_open_cycles', 0)
            ->assertJsonPath('data.lateness_rate', 0);
    }

    public function test_closed_overdue_cycle_is_omitted_from_lateness(): void
    {
        $this->travelTo('2026-09-08 12:00:00');

        [$tenant, $oak] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);
        $pupil = $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        ReviewCycle::factory()->forPupil($pupil)->closed()->dueOn('2026-09-01')->create();

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_cycles', 0)
            ->assertJsonPath('data.overdue_open_cycles', 0)
            ->assertJsonPath('data.lateness_rate', 0);
    }

    public function test_evaluating_pupil_counts_in_flight_not_as_ready(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($oak, DocumentationStatus::Evaluating, 'Riley', 'Chen');
        $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-dashboard')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 2)
            ->assertJsonPath('data.by_status.evaluating', 1)
            ->assertJsonPath('data.by_status.ready', 1);
    }

    public function test_trust_send_lead_does_not_see_other_tenant_schools(): void
    {
        [$tenantA, $oak] = $this->twoActiveSchools();
        $tenantB = Tenant::factory()->create();
        $this->enableTrustDashboard($tenantB);
        $foreign = School::factory()->forTenant($tenantB)->create(['name' => 'Foreign Academy']);
        $leadA = $this->trustSendLead($tenantA);
        $this->pupilWithStatus($oak, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($foreign, DocumentationStatus::Gaps, 'Other', 'Tenant');

        $response = $this->actingAs($leadA)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.gaps', 0)
            ->assertJsonMissing(['school_id' => $foreign->id]);

        $this->assertStringNotContainsString('Foreign Academy', $response->getContent());
        $schoolIds = array_column($response->json('data.schools'), 'school_id');
        $this->assertNotContains($foreign->id, $schoolIds);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenDashboardRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'support staff' => ['supportStaff'],
            'senco' => ['senco'],
            'school leader' => ['schoolLeader'],
            'tenant admin' => ['tenantAdmin'],
        ];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: School}
     */
    private function twoActiveSchools(): array
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $oak = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $ridge = School::factory()->forTenant($tenant)->create(['name' => 'Ridge Academy']);

        return [$tenant, $oak, $ridge];
    }

    private function trustSendLead(Tenant $tenant): User
    {
        return User::factory()->forTenant($tenant)->trustSendLead()->create();
    }

    private function trustExecutive(Tenant $tenant): User
    {
        return User::factory()->forTenant($tenant)->trustExecutive()->create();
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

    private function assertForbiddenFields(TestResponse $response): void
    {
        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach (['attendance', 'budget', 'curriculum', 'pupils', 'placeholder', 'available'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }

        $this->assertStringNotContainsString('attendance', strtolower($response->getContent()));
        $this->assertStringNotContainsString('budget', strtolower($response->getContent()));
        $this->assertStringNotContainsString('curriculum', strtolower($response->getContent()));
        $this->assertStringNotContainsString('ai found', strtolower($response->getContent()));
    }

    private function assertForbidden(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }
}
