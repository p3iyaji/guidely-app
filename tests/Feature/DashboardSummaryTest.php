<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_receives_school_scoped_open_due_and_uncapped_counts(): void
    {
        $this->travelTo('2026-09-09 12:00:00');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $visiblePupil = Pupil::factory()->forSchool($school)->create();
        $secondVisiblePupil = Pupil::factory()->forSchool($school)->create();
        $hiddenPupil = Pupil::factory()->forSchool($otherSchool)->create();

        Gap::factory()->forPupil($visiblePupil)->count(2)->open()->create();
        Gap::factory()->forPupil($visiblePupil)->closed()->create();
        Gap::factory()->forPupil($hiddenPupil)->open()->create();

        ReviewCycle::factory()->forPupil($visiblePupil)->open()->dueOn('2026-09-01')->create();
        ReviewCycle::factory()->forPupil($visiblePupil)->open()->dueOn('2026-10-09')->create();
        ReviewCycle::factory()->forPupil($visiblePupil)->open()->dueOn('2026-10-10')->create();
        ReviewCycle::factory()->forPupil($visiblePupil)->closed()->dueOn('2026-09-20')->create();
        ReviewCycle::factory()->forPupil($hiddenPupil)->open()->dueOn('2026-09-20')->create();

        EvidenceRecord::factory()
            ->count(101)
            ->forPupil($visiblePupil)
            ->authoredBy($teacher)
            ->draft()
            ->create(['setting_term_id' => null]);
        EvidenceRecord::factory()
            ->forPupil($hiddenPupil)
            ->authoredBy($teacher)
            ->draft()
            ->create(['setting_term_id' => null]);
        EvidenceRecord::factory()
            ->forPupil($visiblePupil)
            ->authoredBy($teacher)
            ->create(['setting_term_id' => null]);

        $response = $this->actingAs($senco)->getJson('/api/v1/dashboard-summary');

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 2)
            ->assertJsonPath('data.open_gaps', 2)
            ->assertJsonPath('data.review_cycles_due', 2)
            ->assertJsonPath('data.drafts', 101)
            ->assertJsonPath('data.window_days', 30);

        $this->assertSame([
            'pupils_in_scope',
            'open_gaps',
            'review_cycles_due',
            'drafts',
            'window_days',
        ], array_keys($response->json('data')));
        $this->assertStringNotContainsString($visiblePupil->id, $response->getContent());
        $this->assertStringNotContainsString($secondVisiblePupil->id, $response->getContent());
        $this->assertStringNotContainsString($hiddenPupil->id, $response->getContent());
    }

    public function test_cross_tenant_records_do_not_affect_any_count(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $otherTeacher = User::factory()->forTenant($otherTenant)->teacher()->create();
        Gap::factory()->forPupil($otherPupil)->open()->create();
        ReviewCycle::factory()->forPupil($otherPupil)->open()->create();
        EvidenceRecord::factory()
            ->forPupil($otherPupil)
            ->authoredBy($otherTeacher)
            ->draft()
            ->create(['setting_term_id' => null]);

        $this->actingAs($senco)
            ->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 0)
            ->assertJsonPath('data.open_gaps', 0)
            ->assertJsonPath('data.review_cycles_due', 0)
            ->assertJsonPath('data.drafts', 0);
    }

    public function test_teacher_counts_only_assigned_pupils_and_own_drafts(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $otherTeacher = User::factory()->forTenant($tenant)->teacher()->create();
        $assigned = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $unassigned = Pupil::factory()->forSchool($school)->create();

        EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($teacher)
            ->draft()
            ->create(['setting_term_id' => null]);
        EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($otherTeacher)
            ->draft()
            ->create(['setting_term_id' => null]);
        EvidenceRecord::factory()
            ->forPupil($unassigned)
            ->authoredBy($otherTeacher)
            ->draft()
            ->create(['setting_term_id' => null]);

        $this->actingAs($teacher)
            ->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 1)
            ->assertJsonPath('data.open_gaps', null)
            ->assertJsonPath('data.review_cycles_due', null)
            ->assertJsonPath('data.drafts', 1);
    }

    public function test_tenant_admin_counts_all_tenant_pupils_and_no_protected_metrics(): void
    {
        $tenant = Tenant::factory()->create();
        $firstSchool = School::factory()->forTenant($tenant)->create();
        $secondSchool = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        Pupil::factory()->forSchool($firstSchool)->create();
        Pupil::factory()->forSchool($secondSchool)->create();

        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        Pupil::factory()->forSchool($otherSchool)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 2)
            ->assertJsonPath('data.open_gaps', null)
            ->assertJsonPath('data.review_cycles_due', null)
            ->assertJsonPath('data.drafts', null);
    }

    #[DataProvider('trustRoleCases')]
    public function test_trust_roles_receive_the_intentionally_empty_pupil_cohort(string $factoryState): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        Pupil::factory()->forSchool($school)->count(2)->create();
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::TrustDashboard->value)
            ->update(['enabled' => true]);
        $user = User::factory()->forTenant($tenant)->{$factoryState}()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 0)
            ->assertJsonPath('data.open_gaps', null)
            ->assertJsonPath('data.review_cycles_due', null)
            ->assertJsonPath('data.drafts', null);
    }

    #[DataProvider('roleVisibilityCases')]
    public function test_returns_zero_for_available_empty_metrics_and_null_for_unavailable_ones(
        string $factoryState,
        ?int $pupils,
        ?int $gaps,
        ?int $cycles,
        ?int $drafts,
    ): void {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();

        if (in_array($factoryState, ['trustSendLead', 'trustExecutive'], true)) {
            TenantFeatureFlag::query()
                ->where('tenant_id', $tenant->id)
                ->where('key', FeatureFlagKey::TrustDashboard->value)
                ->update(['enabled' => true]);
        }

        $user = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $user->schools()->attach($school->id);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.pupils_in_scope', $pupils)
            ->assertJsonPath('data.open_gaps', $gaps)
            ->assertJsonPath('data.review_cycles_due', $cycles)
            ->assertJsonPath('data.drafts', $drafts);
    }

    public function test_custom_window_uses_london_horizon_and_invalid_window_returns_422(): void
    {
        $this->travelTo('2026-09-09 12:00:00');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-16')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-17')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-12-08')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-12-09')->create();

        $this->actingAs($leader)
            ->getJson('/api/v1/dashboard-summary?window=7')
            ->assertOk()
            ->assertJsonPath('data.review_cycles_due', 1)
            ->assertJsonPath('data.window_days', 7);

        $this->actingAs($leader)
            ->getJson('/api/v1/dashboard-summary?window=90')
            ->assertOk()
            ->assertJsonPath('data.review_cycles_due', 3)
            ->assertJsonPath('data.window_days', 90);

        $this->actingAs($leader)
            ->getJson('/api/v1/dashboard-summary?window=14')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['window'])
            ->assertJsonPath('errors.window.0', 'Window must be 7, 30, or 90 days.');
    }

    public function test_guest_receives_401_and_platform_operator_receives_403(): void
    {
        $this->getJson('/api/v1/dashboard-summary')->assertUnauthorized();

        $operator = User::factory()->platformOperator()->create();

        $this->actingAs($operator)
            ->getJson('/api/v1/dashboard-summary')
            ->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    /**
     * @return array<string, array{0: string, 1: ?int, 2: ?int, 3: ?int, 4: ?int}>
     */
    public static function roleVisibilityCases(): array
    {
        return [
            'teacher' => ['teacher', 0, null, null, 0],
            'support staff' => ['supportStaff', 0, null, null, 0],
            'SENCO' => ['senco', 0, 0, 0, 0],
            'school leader' => ['schoolLeader', 0, null, 0, null],
            'tenant admin' => ['tenantAdmin', 0, null, null, null],
            'trust SEND lead' => ['trustSendLead', 0, null, null, null],
            'trust executive' => ['trustExecutive', 0, null, null, null],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function trustRoleCases(): array
    {
        return [
            'trust SEND lead' => ['trustSendLead'],
            'trust executive' => ['trustExecutive'],
        ];
    }
}
