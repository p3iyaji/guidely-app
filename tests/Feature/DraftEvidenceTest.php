<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DraftEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingOntologySeeder::class);
        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_teacher_can_save_partial_observation_draft_without_sre(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'lifecycle' => 'draft',
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => null,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Observation->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.setting', null)
            ->assertJsonPath('data.body', null);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'lifecycle' => EvidenceLifecycle::Draft->value,
            'setting_term_id' => null,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('draft', $audit->metadata['lifecycle'] ?? null);
        $this->assertSame('web', $audit->metadata['client_type'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);

        Queue::assertNothingPushed();
    }

    public function test_teacher_can_save_partial_intervention_draft_without_sre(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'lifecycle' => 'draft',
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Intervention->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.provision', null);

        Queue::assertNothingPushed();
    }

    public function test_teacher_can_save_partial_response_draft_without_sre(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $response = $this->actingAs($teacher)->postJson('/api/v1/responses', [
            'lifecycle' => 'draft',
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Response->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.body', null);

        Queue::assertNothingPushed();
    }

    public function test_author_can_submit_complete_draft_with_same_validations(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => null,
            ]);

        $incomplete = $this->actingAs($teacher)->postJson("/api/v1/drafts/{$draft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Still missing Setting.',
        ]);

        $incomplete->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);

        $this->assertSame(EvidenceLifecycle::Draft, $draft->fresh()->lifecycle);

        $complete = $this->actingAs($teacher)->postJson("/api/v1/drafts/{$draft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Settled after the visual timetable was shown.',
        ]);

        $complete->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.setting.id', $setting->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $draft->id,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'setting_term_id' => $setting->id,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationSubmitted->value)
            ->where('resource_id', $draft->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('submitted', $audit->metadata['lifecycle'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($pupil): bool {
            return $job->tenantId === $pupil->tenant_id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_submitted';
        });
    }

    public function test_author_can_update_and_submit_intervention_draft(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->intervention()
            ->draft()
            ->create([
                'provision_term_id' => null,
                'body' => null,
            ]);

        $update = $this->actingAs($teacher)->patchJson("/api/v1/drafts/{$draft->id}", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Draft notes',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.provision.id', $provision->id);

        $incomplete = $this->actingAs($teacher)->postJson("/api/v1/drafts/{$draft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Still missing Provision.',
        ]);

        $incomplete->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);

        $submit = $this->actingAs($teacher)->postJson("/api/v1/drafts/{$draft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Delivered visual timetable support.',
        ]);

        $submit->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value);

        Queue::assertPushed(SreReevaluatePupil::class);
    }

    public function test_author_can_update_and_submit_response_draft(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $intervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->intervention()
            ->withProvision($provision)
            ->create();

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->response()
            ->draft()
            ->create([
                'body' => null,
                'related_intervention_id' => null,
            ]);

        $update = $this->actingAs($teacher)->patchJson("/api/v1/drafts/{$draft->id}", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $intervention->id,
            'body' => 'Responded calmly',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.related_intervention_id', $intervention->id);

        $submit = $this->actingAs($teacher)->postJson("/api/v1/drafts/{$draft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $intervention->id,
            'body' => 'Responded calmly after the Intervention.',
        ]);

        $submit->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value);

        Queue::assertPushed(SreReevaluatePupil::class);
    }

    public function test_patch_does_not_null_out_omitted_fields(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => $setting->id,
                'body' => 'Keep these notes',
            ]);

        $response = $this->actingAs($teacher)->patchJson("/api/v1/drafts/{$draft->id}", [
            'occurred_at' => now()->subMinutes(10)->utc()->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.body', 'Keep these notes')
            ->assertJsonPath('data.setting.id', $setting->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $draft->id,
            'body' => 'Keep these notes',
            'setting_term_id' => $setting->id,
        ]);
    }

    public function test_drafts_index_includes_pupil_given_name(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => 'Listed',
            ]);

        $list = $this->actingAs($teacher)->getJson('/api/v1/drafts');

        $list->assertOk()
            ->assertJsonPath('data.0.pupil.given_name', $pupil->given_name)
            ->assertJsonPath('data.0.pupil.family_name', $pupil->family_name)
            ->assertJsonPath('data.0.author.name', $teacher->name);
    }

    public function test_senco_lists_school_drafts_but_cannot_update_other_authors(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $otherSchool = School::factory()->forTenant($tenant)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $otherTeacher = User::factory()->forTenant($tenant)->teacher()->create();
        $otherTeacher->schools()->attach($otherSchool->id);

        $schoolDraft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => 'In-school draft',
            ]);

        EvidenceRecord::factory()
            ->forPupil($otherPupil)
            ->authoredBy($otherTeacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => 'Other school draft',
            ]);

        $list = $this->actingAs($senco)->getJson('/api/v1/drafts');

        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($schoolDraft->id, $ids);
        $this->assertCount(1, $ids);
        $this->assertSame($teacher->name, $list->json('data.0.author.name'));

        $show = $this->actingAs($senco)->getJson("/api/v1/drafts/{$schoolDraft->id}");
        $show->assertOk()
            ->assertJsonPath('data.id', $schoolDraft->id);

        $update = $this->actingAs($senco)->patchJson("/api/v1/drafts/{$schoolDraft->id}", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'SENCO should not edit',
        ]);

        $this->assertForbidden($update);

        $submit = $this->actingAs($senco)->postJson("/api/v1/drafts/{$schoolDraft->id}/submit", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $this->settingTerm('CLASSROOM')->id,
            'body' => 'SENCO should not submit',
        ]);

        $this->assertForbidden($submit);
    }

    public function test_teacher_cannot_see_or_update_another_authors_draft(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $otherTeacher = User::factory()->forTenant($tenant)->teacher()->create();
        $otherTeacher->schools()->attach($school->id);
        $pupil->assignTo($otherTeacher);

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => 'Author only',
            ]);

        $list = $this->actingAs($otherTeacher)->getJson('/api/v1/drafts');
        $list->assertOk();
        $this->assertSame([], $list->json('data'));

        $show = $this->actingAs($otherTeacher)->getJson("/api/v1/drafts/{$draft->id}");
        $this->assertForbidden($show);

        $update = $this->actingAs($otherTeacher)->patchJson("/api/v1/drafts/{$draft->id}", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Not allowed',
        ]);
        $this->assertForbidden($update);
    }

    public function test_tenant_admin_is_forbidden_on_draft_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => null,
            ]);

        $this->assertForbidden($this->actingAs($admin)->getJson('/api/v1/drafts'));
        $this->assertForbidden($this->actingAs($admin)->getJson("/api/v1/drafts/{$draft->id}"));
        $this->assertForbidden($this->actingAs($admin)->postJson('/api/v1/observations', [
            'lifecycle' => 'draft',
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
        ]));
    }

    public function test_author_can_update_observation_draft(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => null,
            ]);

        $response = $this->actingAs($teacher)->patchJson("/api/v1/drafts/{$draft->id}", [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(30)->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Partial notes so far',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Draft->value)
            ->assertJsonPath('data.body', 'Partial notes so far')
            ->assertJsonPath('data.setting.id', $setting->id);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationUpdated->value)
            ->where('resource_id', $draft->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);
    }

    public function test_submitted_create_paths_remain_unchanged_default(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Direct submit still works.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value);

        Queue::assertPushed(SreReevaluatePupil::class);
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    private function settingTerm(string $code): SettingTerm
    {
        $term = SettingTerm::query()->forTenant()->where('code', $code)->first();
        $this->assertNotNull($term, "Expected seeded Setting term [{$code}]");

        return $term;
    }

    private function provisionTerm(string $code): ProvisionTerm
    {
        $term = ProvisionTerm::query()->forTenant()->where('code', $code)->first();
        $this->assertNotNull($term, "Expected seeded Provision term [{$code}]");

        return $term;
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
