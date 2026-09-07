<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleStatus;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReviewCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_creates_annual_review_visible_on_due_list_and_pupil_and_enqueues_job(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);

        $this->travelTo('2026-09-07 12:00:00');

        $response = $this->actingAs($senco)->postJson('/api/v1/review-cycles', [
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-09-20',
            'ehcp_linked' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.type', ReviewCycleType::AnnualReview->value)
            ->assertJsonPath('data.type_label', 'Annual Review')
            ->assertJsonPath('data.due_on', '2026-09-20')
            ->assertJsonPath('data.ehcp_linked', true)
            ->assertJsonPath('data.status', ReviewCycleStatus::Open->value);

        $this->assertStringNotContainsString('confidence', strtolower($response->getContent()));
        $this->assertStringNotContainsString('diagnos', strtolower($response->getContent()));
        $this->assertStringNotContainsString('auto-approved', strtolower($response->getContent()));

        $cycleId = $response->json('data.id');
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
            ->assertJsonPath('data.0.id', $cycleId);

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.next_review_at', '2026-09-20');

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::ReviewCycleCreated->value)
            ->where('resource_id', $cycleId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame(ReviewCycleType::AnnualReview->value, $audit->metadata['type'] ?? null);
        $this->assertSame('2026-09-20', $audit->metadata['due_on'] ?? null);
        $this->assertTrue($audit->metadata['ehcp_link'] ?? false);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'review_cycle_created';
        });
    }

    public function test_senco_creates_review_cycle_nested_under_pupil(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/pupils/'.$pupil->id.'/review-cycles', [
                'type' => ReviewCycleType::Interim->value,
                'due_on' => '2026-11-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', ReviewCycleType::Interim->value)
            ->assertJsonPath('data.ehcp_linked', false)
            ->assertJsonPath('data.pupil_id', $pupil->id);

        Bus::assertDispatched(SreReevaluatePupil::class);
    }

    public function test_school_leader_lists_school_scoped_open_cycles_and_cannot_create(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-20')->create();

        $this->actingAs($leader)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cycle->id);

        $this->assertForbidden($this->actingAs($leader)->postJson('/api/v1/review-cycles', [
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-10-01',
        ]));
        $this->assertForbidden($this->actingAs($leader)->postJson('/api/v1/review-cycles/'.$cycle->id.'/close'));
    }

    public function test_senco_close_drops_cycle_from_due_list_advances_next_review_and_enqueues_job(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $earlier = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-10')->create();
        $later = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-10-01')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.next_review_at', '2026-09-10');

        $response = $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles/'.$earlier->id.'/close');

        $response->assertOk()
            ->assertJsonPath('data.status', ReviewCycleStatus::Closed->value)
            ->assertJsonPath('data.closed_by', $senco->id);

        $this->assertNotNull($response->json('data.closed_at'));
        $this->assertDatabaseHas('review_cycles', [
            'id' => $earlier->id,
            'status' => ReviewCycleStatus::Closed->value,
            'closed_by' => $senco->id,
        ]);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $later->id);

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.next_review_at', '2026-10-01');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::ReviewCycleClosed->value,
            'resource_type' => 'review_cycle',
            'resource_id' => $earlier->id,
        ]);

        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'review_cycle_closed';
        });
    }

    public function test_closing_last_open_cycle_clears_next_review_at(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)->postJson('/api/v1/review-cycles/'.$cycle->id.'/close')->assertOk();

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.next_review_at', null);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_returns_403_when_teacher_creates_a_review_cycle(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->assertForbidden($this->actingAs($teacher)->postJson('/api/v1/review-cycles', [
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-10-01',
        ]));

        $this->assertDatabaseCount('review_cycles', 0);
        Bus::assertNothingDispatched();
    }

    public function test_returns_403_when_teacher_or_support_lists_review_cycles(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->assertForbidden($this->actingAs($teacher)->getJson('/api/v1/review-cycles'));
        $this->assertForbidden($this->actingAs($support)->getJson('/api/v1/review-cycles'));
        $this->assertForbidden($this->actingAs($teacher)->postJson('/api/v1/review-cycles/'.$cycle->id.'/close'));
    }

    public function test_senco_lists_open_and_closed_cycles_for_a_pupil(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $other = Pupil::factory()->forSchool($school)->create();
        $closed = ReviewCycle::factory()->forPupil($pupil)->closed()->dueOn('2026-08-01')->create();
        $open = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-20')->create();
        ReviewCycle::factory()->forPupil($other)->open()->dueOn('2026-09-20')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/pupils/'.$pupil->id.'/review-cycles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $open->id)
            ->assertJsonPath('data.1.id', $closed->id)
            ->assertJsonPath('data.1.status', ReviewCycleStatus::Closed->value);

        $dueListIds = array_column(
            $this->actingAs($senco)
                ->getJson('/api/v1/review-cycles')
                ->assertOk()
                ->json('data'),
            'id',
        );

        $this->assertContains($open->id, $dueListIds);
        $this->assertNotContains($closed->id, $dueListIds);
    }

    public function test_returns_403_when_teacher_lists_pupil_review_cycles(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        ReviewCycle::factory()->forPupil($pupil)->closed()->create();

        $this->assertForbidden($this->actingAs($teacher)->getJson('/api/v1/pupils/'.$pupil->id.'/review-cycles'));
    }

    public function test_returns_403_when_other_school_senco_creates_or_closes(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($otherSchool->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->assertForbidden($this->actingAs($senco)->postJson('/api/v1/review-cycles', [
            'pupil_id' => $pupil->id,
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-10-01',
        ]));
        $this->assertForbidden($this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/review-cycles', [
            'type' => ReviewCycleType::AnnualReview->value,
            'due_on' => '2026-10-01',
        ]));
        $this->assertForbidden($this->actingAs($senco)->postJson('/api/v1/review-cycles/'.$cycle->id.'/close'));
        $this->assertForbidden($this->actingAs($senco)->getJson('/api/v1/pupils/'.$pupil->id.'/review-cycles'));

        $this->assertDatabaseCount('review_cycles', 1);
        $cycle->refresh();
        $this->assertSame(ReviewCycleStatus::Open, $cycle->status);
        Bus::assertNothingDispatched();
    }

    public function test_returns_422_when_due_date_is_missing_or_type_is_invalid(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles', [
                'pupil_id' => $pupil->id,
                'type' => ReviewCycleType::AnnualReview->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_on'])
            ->assertJsonPath('errors.due_on.0', 'A due date is required.');

        $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles', [
                'pupil_id' => $pupil->id,
                'type' => 'annual',
                'due_on' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type'])
            ->assertJsonPath('errors.type.0', 'Type must be Annual Review, Interim, or Other.');

        $this->assertDatabaseCount('review_cycles', 0);
    }

    public function test_returns_422_when_due_date_format_is_ambiguous(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles', [
                'pupil_id' => $pupil->id,
                'type' => ReviewCycleType::AnnualReview->value,
                'due_on' => '01/10/2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_on']);

        $this->assertDatabaseCount('review_cycles', 0);
    }

    public function test_window_7_includes_past_due_opens_and_excludes_later_dues(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pastDue = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-01')->create();
        $within = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-14')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-15')->create();
        ReviewCycle::factory()->forPupil($pupil)->closed()->dueOn('2026-09-02')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?window=7')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pastDue->id)
            ->assertJsonPath('data.1.id', $within->id);
    }

    public function test_window_30_and_90_include_horizon_and_exclude_later_dues(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $within30 = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-10-07')->create();
        $beyond30 = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-10-08')->create();
        $within90 = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-12-06')->create();
        ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-12-07')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?window=30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $within30->id);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?window=90')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $within30->id)
            ->assertJsonPath('data.1.id', $beyond30->id)
            ->assertJsonPath('data.2.id', $within90->id);
    }

    public function test_omits_sibling_school_cycles_from_the_due_list(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $visible = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-10')->create();
        ReviewCycle::factory()->forPupil($otherPupil)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_filters_due_list_by_pupil_name_query(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $maya = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);
        $jordan = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Jordan',
            'family_name' => 'Lee',
        ]);
        $match = ReviewCycle::factory()->forPupil($maya)->open()->dueOn('2026-09-10')->create();
        ReviewCycle::factory()->forPupil($jordan)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?q=Maya')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?q=okonkwo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?q=Maya Okonkwo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_name_query_wildcards_do_not_match_every_pupil(): void
    {
        $this->travelTo('2026-09-07 12:00:00');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $maya = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);
        ReviewCycle::factory()->forPupil($maya)->open()->dueOn('2026-09-10')->create();
        $jordan = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Jordan',
            'family_name' => 'Lee',
        ]);
        ReviewCycle::factory()->forPupil($jordan)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)
            ->getJson('/api/v1/review-cycles?q=%')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_closing_an_already_closed_cycle_returns_422_and_does_not_enqueue(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->dueOn('2026-09-10')->create();

        $this->actingAs($senco)->postJson('/api/v1/review-cycles/'.$cycle->id.'/close')->assertOk();
        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);

        $closedAt = $cycle->fresh()->closed_at;
        $closedBy = $cycle->fresh()->closed_by;

        $this->actingAs($senco)
            ->postJson('/api/v1/review-cycles/'.$cycle->id.'/close')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $cycle->refresh();
        $this->assertSame(ReviewCycleStatus::Closed, $cycle->status);
        $this->assertEquals($closedAt?->toIso8601String(), $cycle->closed_at?->toIso8601String());
        $this->assertSame($closedBy, $cycle->closed_by);
        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
    }

    public function test_guest_cannot_list_or_create_review_cycles(): void
    {
        $this->getJson('/api/v1/review-cycles')->assertUnauthorized();
        $this->postJson('/api/v1/review-cycles', [])->assertUnauthorized();
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
}
