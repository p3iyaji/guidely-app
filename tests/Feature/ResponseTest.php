<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_teacher_can_submit_response_with_intervention_link(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $intervention = $this->interventionFor($pupil, $teacher);

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $intervention->id,
            'body' => 'Engaged calmly after the visual timetable.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Response->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.author_id', $teacher->id)
            ->assertJsonPath('data.related_intervention_id', $intervention->id)
            ->assertJsonPath('data.related_intervention.id', $intervention->id)
            ->assertJsonPath('data.related_intervention.type', EvidenceType::Intervention->value)
            ->assertJsonPath('data.setting', null)
            ->assertJsonPath('data.provision', null)
            ->assertJsonPath('data.body', 'Engaged calmly after the visual timetable.');

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'author_id' => $teacher->id,
            'type' => EvidenceType::Response->value,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'related_intervention_id' => $intervention->id,
            'setting_term_id' => null,
            'provision_term_id' => null,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceResponseCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('web', $audit->metadata['client_type'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($intervention->id, $audit->metadata['related_intervention_id'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
    }

    public function test_teacher_can_submit_response_with_dated_context_only(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(45)->utc()->toIso8601String(),
            'body' => 'Responded well during the afternoon session.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Response->value)
            ->assertJsonPath('data.related_intervention_id', null)
            ->assertJsonPath('data.related_intervention', null)
            ->assertJsonPath('data.body', 'Responded well during the afternoon session.');
    }

    public function test_invalid_intervention_link_is_rejected(): void
    {
        [, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $otherPupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $crossPupilIntervention = $this->interventionFor($otherPupil, $teacher);
        $observation = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->create([
                'type' => EvidenceType::Observation,
                'provision_term_id' => null,
            ]);

        $wrongPupil = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $crossPupilIntervention->id,
            'body' => 'Should not link across Pupils.',
        ]);

        $wrongPupil->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);

        $wrongType = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $observation->id,
            'body' => 'Should not link an Observation.',
        ]);

        $wrongType->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);

        $unknown = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => '01hunknownintervention00',
            'body' => 'Should not link an unknown id.',
        ]);

        $unknown->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);
    }

    public function test_draft_intervention_link_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $draftIntervention = $this->interventionFor($pupil, $teacher);
        $draftIntervention->forceFill([
            'lifecycle' => EvidenceLifecycle::Draft,
        ])->save();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $draftIntervention->id,
            'body' => 'Should not link a draft Intervention.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);
    }

    public function test_blank_body_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $empty = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => '',
        ]);

        $empty->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $whitespace = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => '   ',
        ]);

        $whitespace->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_teacher_cannot_submit_response_for_unassigned_pupil(): void
    {
        [, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $otherPupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $otherPupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->where('type', EvidenceType::Response)->count());
    }

    public function test_tenant_admin_cannot_capture_response(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('capture-evidence'));

        $response = $this->actingAs($admin)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
    }

    public function test_senco_can_submit_response_for_in_school_pupil(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(30)->utc()->toIso8601String(),
            'body' => 'Settled after small-group support.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.author_id', $senco->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'type' => EvidenceType::Response->value,
        ]);
    }

    public function test_hybrid_client_type_is_recorded_in_audit_metadata(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)
            ->withHeader('X-Client-Type', 'hybrid')
            ->postJson('/api/v1/responses', [
                'pupil_id' => $pupil->id,
                'occurred_at' => now()->subMinutes(15)->utc()->toIso8601String(),
                'body' => 'Calmer after quiet corner.',
            ]);

        $response->assertCreated();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceResponseCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('hybrid', $audit->metadata['client_type'] ?? null);
    }

    public function test_setting_and_provision_fields_are_rejected_on_response_create(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => '01hsettingterm00000000000',
            'provision_term_id' => '01hprovisionterm000000000',
            'body' => 'Should reject taxonomy fields.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id', 'provision_term_id']);
    }

    public function test_teacher_can_list_interventions_for_assigned_pupil(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $submitted = $this->interventionFor($pupil, $teacher);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->create([
                'type' => EvidenceType::Observation,
                'provision_term_id' => null,
            ]);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->response()
            ->create();

        $draftIntervention = $this->interventionFor($pupil, $teacher);
        $draftIntervention->forceFill([
            'lifecycle' => EvidenceLifecycle::Draft,
            'occurred_at' => now()->subHour()->utc(),
        ])->save();

        $response = $this->actingAs($teacher)->getJson("/api/v1/pupils/{$pupil->id}/interventions");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id)
            ->assertJsonPath('data.0.type', EvidenceType::Intervention->value)
            ->assertJsonPath('data.0.provision.code', 'UNIVERSAL');
    }

    public function test_teacher_cannot_list_interventions_for_unassigned_pupil(): void
    {
        [, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $otherPupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($teacher)->getJson("/api/v1/pupils/{$otherPupil->id}/interventions");

        $this->assertForbidden($response);
    }

    public function test_future_occurred_at_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->addDay()->utc()->toIso8601String(),
            'body' => 'Future session should be rejected.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_soft_deleted_or_unknown_pupil_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $pupil->delete();

        $left = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Left Pupil should be rejected.',
        ]);

        $left->assertStatus(422)
            ->assertJsonValidationErrors(['pupil_id']);

        $unknown = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => '01hunknownpupil000000000000',
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Unknown Pupil should be rejected.',
        ]);

        $unknown->assertStatus(422)
            ->assertJsonValidationErrors(['pupil_id']);
    }

    public function test_cross_tenant_intervention_link_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        $otherAuthor = User::factory()->forTenant($otherTenant)->teacher()->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $foreignIntervention = $this->interventionFor($otherPupil, $otherAuthor);

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $foreignIntervention->id,
            'body' => 'Should not link a cross-Tenant Intervention.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    private function interventionFor(Pupil $pupil, User $author): EvidenceRecord
    {
        $provision = ProvisionTerm::query()->forTenant()->where('code', 'UNIVERSAL')->first();
        $this->assertNotNull($provision, 'Expected seeded Provision term [UNIVERSAL]');

        return EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($author)
            ->intervention($provision)
            ->create([
                'occurred_at' => now()->subHours(2)->utc(),
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndTeacher(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        return [$tenant, $school, $teacher];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User, 3: Pupil}
     */
    private function tenantSchoolTeacherWithAssignedPupil(): array
    {
        [$tenant, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        return [$tenant, $school, $teacher, $pupil];
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
