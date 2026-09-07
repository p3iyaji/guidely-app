<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReviewNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_can_create_submitted_review_note_for_in_school_pupil(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'SENCO professional judgement: documentation is proportionate for current needs.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::ReviewNote->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.source', EvidenceSource::Capture->value)
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.author_id', $senco->id)
            ->assertJsonPath('data.setting', null)
            ->assertJsonPath('data.provision', null)
            ->assertJsonPath('data.body', 'SENCO professional judgement: documentation is proportionate for current needs.');

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'author_id' => $senco->id,
            'type' => EvidenceType::ReviewNote->value,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'source' => EvidenceSource::Capture->value,
            'setting_term_id' => null,
            'provision_term_id' => null,
            'related_intervention_id' => null,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceReviewNoteCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('web', $audit->metadata['client_type'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame(EvidenceType::ReviewNote->value, $audit->metadata['type'] ?? null);
        $this->assertSame(EvidenceLifecycle::Submitted->value, $audit->metadata['lifecycle'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_submitted';
        });
    }

    public function test_teacher_cannot_create_review_note(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Teacher should not author review notes.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_school_leader_cannot_create_review_note(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($leader)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Leader should not author review notes.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_support_staff_tenant_admin_and_trust_cannot_create_review_note(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $pupil->assignedUsers()->attach($support->id);

        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $trust = Tenant::factory()->trust()->create();
        $trustLead = User::factory()->forTenant($trust)->trustSendLead()->create();

        foreach ([$support, $admin, $trustLead] as $user) {
            $response = $this->actingAs($user)->postJson('/api/v1/review-notes', [
                'pupil_id' => $pupil->id,
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'body' => 'Should not be allowed.',
            ]);

            $this->assertForbidden($response);
        }

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_blank_or_missing_body_is_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $blank = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => '   ',
        ]);

        $blank->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $missing = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
        ]);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_ontology_fields_and_lifecycle_are_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Valid commentary',
            'setting_term_id' => '01hsettingterm000000000000',
            'provision_term_id' => '01hprovisionterm000000000',
            'related_intervention_id' => '01hintervention00000000000',
            'lifecycle' => 'draft',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'setting_term_id',
                'provision_term_id',
                'related_intervention_id',
                'lifecycle',
            ]);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_future_occurred_at_is_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->addDay()->utc()->toIso8601String(),
            'body' => 'Future-dated commentary should fail.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurred_at']);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_hybrid_client_type_is_recorded_in_audit_metadata(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($senco)
            ->withHeader('X-Client-Type', 'hybrid')
            ->postJson('/api/v1/review-notes', [
                'pupil_id' => $pupil->id,
                'occurred_at' => now()->subMinutes(15)->utc()->toIso8601String(),
                'body' => 'Hybrid client review commentary.',
            ]);

        $response->assertCreated();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceReviewNoteCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('hybrid', $audit->metadata['client_type'] ?? null);
    }

    public function test_senco_cannot_amend_submitted_review_note(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $reviewNote = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->reviewNote()
            ->create();

        $response = $this->actingAs($senco)->patchJson("/api/v1/evidence/{$reviewNote->id}", [
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Attempted amendment of review note.',
        ]);

        $this->assertForbidden($response);
        $this->assertDatabaseHas('evidence_records', [
            'id' => $reviewNote->id,
            'body' => $reviewNote->body,
        ]);
    }

    public function test_senco_without_school_access_cannot_create_review_note(): void
    {
        $tenant = Tenant::factory()->create();
        $accessible = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($accessible->id);
        $pupil = Pupil::factory()->forSchool($otherSchool)->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Should not be allowed for this School.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_created_review_note_appears_in_evidence_base_and_filter(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $create = $this->actingAs($senco)->postJson('/api/v1/review-notes', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(20)->utc()->toIso8601String(),
            'body' => 'Visible review commentary.',
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');

        $list = $this->actingAs($senco)->getJson("/api/v1/pupils/{$pupil->id}/evidence");
        $list->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.type', EvidenceType::ReviewNote->value);

        $filtered = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=review_note");
        $filtered->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonCount(1, 'data');
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

    /**
     * @return array{0: Tenant, 1: School, 2: User, 3: Pupil}
     */
    private function tenantSchoolTeacherWithAssignedPupil(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        return [$tenant, $school, $teacher, $pupil];
    }
}
