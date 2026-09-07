<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleStatus;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewCycleAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_403_feature_not_available_when_flag_is_off(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $admin] = $this->tenantSchoolAndAdmin();
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'review_cycle_automation',
            ]);

        $this->assertDatabaseCount('review_cycles', 1);
        Bus::assertNothingDispatched();
        $this->assertSame(0, AuditEvent::query()->where('event_type', AuditEventType::ReviewCycleCreated->value)->count());
    }

    public function test_admin_run_creates_anniversary_cycle_on_due_list_and_enqueues_job(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin, $senco] = $this->tenantSchoolAdminAndSenco();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()
            ->forPupil($pupil)
            ->annualReview()
            ->closed()
            ->dueOn('2025-09-07')
            ->create(['ehcp_linked' => true]);

        $response = $this->actingAs($admin)->postJson('/api/v1/review-cycles/automation/run');

        $response->assertCreated()
            ->assertJsonPath('data.0.pupil_id', $pupil->id)
            ->assertJsonPath('data.0.type', ReviewCycleType::AnnualReview->value)
            ->assertJsonPath('data.0.due_on', '2026-09-07')
            ->assertJsonPath('data.0.ehcp_linked', true)
            ->assertJsonPath('data.0.status', ReviewCycleStatus::Open->value);

        $cycleId = $response->json('data.0.id');
        $this->assertNotNull($cycleId);
        $this->assertDatabaseHas('review_cycles', [
            'id' => $cycleId,
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'status' => ReviewCycleStatus::Open->value,
        ]);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cycleId)
            ->assertJsonPath('data.0.due_on', '2026-09-07');

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.next_review_at', '2026-09-07');

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::ReviewCycleCreated->value)
            ->where('resource_id', $cycleId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->user_id);
        $this->assertSame('system', $audit->metadata['actor'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame(ReviewCycleType::AnnualReview->value, $audit->metadata['type'] ?? null);
        $this->assertSame('2026-09-07', $audit->metadata['due_on'] ?? null);
        $this->assertTrue($audit->metadata['ehcp_link'] ?? false);
        $this->assertArrayNotHasKey('source', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'review_cycle_created';
        });
    }

    public function test_does_not_create_when_an_open_annual_review_exists(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->open()->dueOn('2026-06-01')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertDatabaseCount('review_cycles', 2);
        Bus::assertNothingDispatched();
    }

    public function test_rerun_is_idempotent_and_does_not_create_extra_rows(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->actingAs($admin)->postJson('/api/v1/review-cycles/automation/run')->assertCreated();
        $this->assertDatabaseCount('review_cycles', 2);
        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertDatabaseCount('review_cycles', 2);
        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
    }

    public function test_artisan_skips_tenants_when_flag_is_off(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [, $school] = $this->tenantSchoolAndAdmin();
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $exit = Artisan::call('guidely:roll-forward-review-cycles');

        $this->assertSame(0, $exit);
        $this->assertDatabaseCount('review_cycles', 1);
        Bus::assertNothingDispatched();
    }

    public function test_artisan_does_not_create_cycles_for_other_tenants(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenantA, $schoolA] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenantA);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        ReviewCycle::factory()->forPupil($pupilA)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $tenantB = Tenant::factory()->create();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create();
        ReviewCycle::factory()->forPupil($pupilB)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $exit = Artisan::call('guidely:roll-forward-review-cycles');

        $this->assertSame(0, $exit);
        $this->assertSame(2, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(1, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantB->id)->count());

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::ReviewCycleCreated->value)
            ->where('tenant_id', $tenantA->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->user_id);
        $this->assertSame('system', $audit->metadata['actor'] ?? null);
        $this->assertSame('guidely:roll-forward-review-cycles', $audit->metadata['source'] ?? null);

        $this->assertSame(
            0,
            AuditEvent::query()
                ->where('event_type', AuditEventType::ReviewCycleCreated->value)
                ->where('tenant_id', $tenantB->id)
                ->count(),
        );
    }

    #[DataProvider('nonAdminRoles')]
    public function test_returns_403_forbidden_when_non_admin_posts_run(string $roleFactory): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $user = User::factory()->forTenant($tenant)->{$roleFactory}()->create();
        $user->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->assertForbidden($this->actingAs($user)->postJson('/api/v1/review-cycles/automation/run'));

        $this->assertDatabaseCount('review_cycles', 1);
        Bus::assertNothingDispatched();
    }

    public function test_does_not_create_when_anniversary_is_in_the_future(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-08')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertDatabaseCount('review_cycles', 1);
        Bus::assertNothingDispatched();
    }

    public function test_skips_pupils_with_no_annual_review_and_soft_deleted_pupils(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);

        $noAnnual = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($noAnnual)->dueOn('2025-09-07')->create([
            'type' => ReviewCycleType::Interim,
            'status' => ReviewCycleStatus::Closed,
            'closed_at' => now(),
        ]);

        $deleted = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($deleted)->annualReview()->closed()->dueOn('2025-09-07')->create();
        $deleted->delete();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertSame(2, ReviewCycle::withoutGlobalScope('tenant')->count());
        Bus::assertNothingDispatched();
    }

    public function test_does_not_auto_close_open_cycles_when_flag_is_on(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $open = ReviewCycle::factory()->forPupil($pupil)->annualReview()->open()->dueOn('2025-09-01')->create();

        $this->actingAs($admin)->postJson('/api/v1/review-cycles/automation/run')->assertOk();

        $open->refresh();
        $this->assertSame(ReviewCycleStatus::Open, $open->status);
        $this->assertNull($open->closed_at);
        $this->assertDatabaseCount('review_cycles', 1);
    }

    public function test_senco_manual_create_and_close_still_work_when_flag_is_off(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, , $senco] = $this->tenantSchoolAdminAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $created = $this->actingAs($senco)->postJson('/api/v1/review-cycles', [
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-09-20',
        ]);

        $created->assertCreated();
        $cycleId = $created->json('data.id');

        $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles/'.$cycleId.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', ReviewCycleStatus::Closed->value);
    }

    public function test_guest_cannot_run_automation(): void
    {
        $this->postJson('/api/v1/review-cycles/automation/run')->assertUnauthorized();
    }

    public function test_roll_forward_is_scheduled_daily_without_overlapping(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('guidely:roll-forward-review-cycles', Artisan::output());

        $event = collect(app(Schedule::class)->events())->first(
            fn ($scheduled): bool => str_contains((string) $scheduled->command, 'guidely:roll-forward-review-cycles'),
        );

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Europe/London', $event->timezone);
    }

    public function test_uses_the_latest_closed_annual_review_when_older_history_exists(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2023-09-07')->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertCreated()
            ->assertJsonPath('data.0.due_on', '2026-09-07')
            ->assertJsonPath('data.0.pupil.id', $pupil->id);

        $this->assertDatabaseCount('review_cycles', 3);
    }

    public function test_creates_a_cycle_for_every_due_pupil_in_one_run(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $admin, $senco] = $this->tenantSchoolAdminAndSenco();
        $this->enableAutomation($tenant);
        $first = Pupil::factory()->forSchool($school)->create();
        $second = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($first)->annualReview()->closed()->dueOn('2025-09-07')->create();
        ReviewCycle::factory()->forPupil($second)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_run_does_not_create_cycles_for_another_flagged_tenant(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenantA, $schoolA, $adminA] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenantA);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        ReviewCycle::factory()->forPupil($pupilA)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $tenantB = Tenant::factory()->create();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $this->enableAutomation($tenantB);
        $pupilB = Pupil::factory()->forSchool($schoolB)->create();
        ReviewCycle::factory()->forPupil($pupilB)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->actingAs($adminA)->postJson('/api/v1/review-cycles/automation/run')->assertCreated();

        $this->assertSame(2, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(1, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantB->id)->count());
    }

    public function test_artisan_tenant_option_limits_the_run_and_fails_for_unknown_id(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenantA, $schoolA] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenantA);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        ReviewCycle::factory()->forPupil($pupilA)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $tenantB = Tenant::factory()->create();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $this->enableAutomation($tenantB);
        $pupilB = Pupil::factory()->forSchool($schoolB)->create();
        ReviewCycle::factory()->forPupil($pupilB)->annualReview()->closed()->dueOn('2025-09-07')->create();

        $this->assertSame(0, Artisan::call('guidely:roll-forward-review-cycles', ['--tenant' => $tenantA->id]));
        $this->assertSame(2, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(1, ReviewCycle::withoutGlobalScope('tenant')->where('tenant_id', $tenantB->id)->count());

        $unknown = Artisan::call('guidely:roll-forward-review-cycles', ['--tenant' => '01hzzzzzzzzzzzzzzzzzzzzzzz']);
        $this->assertSame(1, $unknown);
        $this->assertStringContainsString('was not found', Artisan::output());
    }

    public function test_leap_day_anniversary_does_not_overflow_into_march(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2025-02-28 12:00:00');

        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $this->enableAutomation($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->annualReview()->closed()->dueOn('2024-02-29')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/review-cycles/automation/run')
            ->assertCreated()
            ->assertJsonPath('data.0.due_on', '2025-02-28');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'senco' => ['senco'],
            'teacher' => ['teacher'],
            'school_leader' => ['schoolLeader'],
        ];
    }

    /**
     * @param  TestResponse  $response
     */
    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    private function enableAutomation(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::ReviewCycleAutomation->value)
            ->update(['enabled' => true]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $admin->schools()->attach($school->id);

        return [$tenant, $school, $admin];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User, 3: User}
     */
    private function tenantSchoolAdminAndSenco(): array
    {
        [$tenant, $school, $admin] = $this->tenantSchoolAndAdmin();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $admin, $senco];
    }
}
