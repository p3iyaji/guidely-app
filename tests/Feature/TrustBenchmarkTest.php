<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reporting\TrustIndicator;
use App\Domain\Reporting\TrustIndicatorSnapshot;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_trust_benchmark_route_is_not_available_when_flag_is_off(): void
    {
        [$tenant] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-benchmark')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'portfolio_benchmarking',
            ]);
    }

    public function test_dashboard_omits_benchmark_when_flag_is_off(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.gaps', 1);
        $this->assertArrayNotHasKey('benchmark', $response->json('data'));
    }

    public function test_send_lead_ranks_oak_above_ridge_when_gap_density_is_higher(): void
    {
        [$tenant, $oak, $ridge] = $this->twoActiveSchools();
        $this->enableBenchmarking($tenant);
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($ridge, DocumentationStatus::Ready, 'Sam', 'Patel');

        $dashboard = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');
        $benchmark = $this->actingAs($lead)->getJson('/api/v1/trust-benchmark');

        $dashboard->assertOk()
            ->assertJsonPath('data.benchmark.ranked_by', 'gap_density')
            ->assertJsonPath('data.benchmark.schools.0.school_id', $oak->id)
            ->assertJsonPath('data.benchmark.schools.0.rank', 1)
            ->assertJsonPath('data.benchmark.schools.0.gap_density', 1)
            ->assertJsonPath('data.benchmark.schools.1.school_id', $ridge->id)
            ->assertJsonPath('data.benchmark.schools.1.rank', 2)
            ->assertJsonPath('data.benchmark.trends', []);

        $benchmark->assertOk()
            ->assertJsonPath('data.schools.0.school_id', $oak->id)
            ->assertJsonPath('data.schools.0.rank', 1)
            ->assertJsonPath('data.schools.1.school_id', $ridge->id)
            ->assertJsonPath('data.trends', []);

        $this->assertStringNotContainsString('Maya', $dashboard->getContent());
        $this->assertStringNotContainsString('Okonkwo', $benchmark->getContent());
    }

    public function test_executive_sees_trends_without_school_ranks(): void
    {
        [$tenant, $oak] = $this->twoActiveSchools();
        $this->enableBenchmarking($tenant);
        $executive = $this->trustExecutive($tenant);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');
        $this->trustSnapshot($tenant, '2026-07', 0.4, 0.2, 4, 1);
        $this->trustSnapshot($tenant, '2026-08', 0.5, 0.25, 4, 1);

        $dashboard = $this->actingAs($executive)->getJson('/api/v1/trust-dashboard');
        $benchmark = $this->actingAs($executive)->getJson('/api/v1/trust-benchmark');

        $dashboard->assertOk()
            ->assertJsonPath('data.benchmark.trends.0.month', '2026-07')
            ->assertJsonPath('data.benchmark.trends.1.month', '2026-08')
            ->assertJsonPath('data.benchmark.trends.1.lateness_rate', 0.5)
            ->assertJsonPath('data.benchmark.trends.1.gap_density', 0.25)
            ->assertJsonPath('data.benchmark.trends.1.lateness_rate_delta', 0.1)
            ->assertJsonPath('data.benchmark.trends.1.gap_density_delta', 0.05);

        $this->assertArrayNotHasKey('schools', $dashboard->json('data'));
        $this->assertArrayNotHasKey('schools', $dashboard->json('data.benchmark'));
        $this->assertArrayNotHasKey('schools', $benchmark->json('data'));
        $this->assertStringNotContainsString('Maya', $dashboard->getContent());
    }

    public function test_inactive_school_is_omitted_from_live_ranks(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableTrustDashboard($tenant);
        $this->enableBenchmarking($tenant);
        $active = School::factory()->forTenant($tenant)->create(['name' => 'Oak Academy']);
        $inactive = School::factory()->forTenant($tenant)->inactive()->create(['name' => 'Closed Academy']);
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($active, DocumentationStatus::Ready, 'Maya', 'Okonkwo');
        $this->pupilWithStatus($inactive, DocumentationStatus::Gaps, 'Hidden', 'Pupil');
        TrustIndicatorSnapshot::factory()->forTenant($tenant)->forSchool($inactive)->forMonth('2026-08')->create([
            'gap_density' => 1,
            'lateness_rate' => 1,
            'pupils_in_scope' => 1,
            'gaps' => 1,
        ]);

        $response = $this->actingAs($lead)->getJson('/api/v1/trust-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.benchmark.schools.0.school_id', $active->id)
            ->assertJsonCount(1, 'data.benchmark.schools');

        $this->assertStringNotContainsString('Closed Academy', $response->getContent());
        $this->assertStringNotContainsString('Hidden', $response->getContent());
    }

    public function test_teacher_is_forbidden_and_guest_is_unauthorized_when_flag_is_on(): void
    {
        [$tenant] = $this->twoActiveSchools();
        $this->enableBenchmarking($tenant);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->actingAs($teacher)
            ->getJson('/api/v1/trust-benchmark')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertFalse($teacher->can('view', TrustIndicator::class));
    }

    public function test_guest_receives_401_on_trust_benchmark(): void
    {
        $this->getJson('/api/v1/trust-benchmark')->assertUnauthorized();
    }

    public function test_snapshot_command_writes_trust_totals_that_appear_in_trends(): void
    {
        $this->travelTo('2026-08-15 12:00:00');

        [$tenant, $oak] = $this->twoActiveSchools();
        $this->enableBenchmarking($tenant);
        $lead = $this->trustSendLead($tenant);
        $this->pupilWithStatus($oak, DocumentationStatus::Gaps, 'Maya', 'Okonkwo');

        $this->artisan('guidely:snapshot-trust-indicators', [
            '--tenant' => $tenant->id,
            '--month' => '2026-08',
        ])->assertSuccessful();

        $this->assertDatabaseHas('trust_indicator_snapshots', [
            'tenant_id' => $tenant->id,
            'month' => '2026-08',
            'snapshot_key' => '2026-08:trust',
            'gaps' => 1,
            'pupils_in_scope' => 1,
        ]);

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-dashboard')
            ->assertOk()
            ->assertJsonPath('data.benchmark.trends.0.month', '2026-08')
            ->assertJsonPath('data.benchmark.trends.0.gaps', 1)
            ->assertJsonPath('data.benchmark.trends.0.pupils_in_scope', 1);
    }

    public function test_dashboard_off_blocks_benchmark_route_before_nested_flag(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableBenchmarking($tenant);
        $lead = $this->trustSendLead($tenant);

        $this->actingAs($lead)
            ->getJson('/api/v1/trust-benchmark')
            ->assertForbidden()
            ->assertJsonPath('feature', 'trust_dashboard');
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

    private function enableBenchmarking(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::PortfolioBenchmarking->value)
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

    private function trustSnapshot(
        Tenant $tenant,
        string $month,
        float $latenessRate,
        float $gapDensity,
        int $pupilsInScope,
        int $gaps,
    ): TrustIndicatorSnapshot {
        return TrustIndicatorSnapshot::factory()->forTenant($tenant)->forMonth($month)->create([
            'school_id' => null,
            'lateness_rate' => $latenessRate,
            'gap_density' => $gapDensity,
            'pupils_in_scope' => $pupilsInScope,
            'gaps' => $gaps,
        ]);
    }
}
