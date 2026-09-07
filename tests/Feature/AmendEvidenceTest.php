<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceRecordVersion;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AmendEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingOntologySeeder::class);
        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_author_can_amend_submitted_observation_with_history_audit_and_sre(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $classroom = $this->settingTerm('CLASSROOM');
        $playground = $this->settingTerm('PLAYGROUND');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($classroom)
            ->create([
                'body' => 'Original observation body',
                'occurred_at' => now()->subDay()->utc(),
            ]);

        $response = $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHours(2)->utc()->toIso8601String(),
            'setting_term_id' => $playground->id,
            'body' => 'Corrected observation body',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.body', 'Corrected observation body')
            ->assertJsonPath('data.setting.id', $playground->id)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.type', EvidenceType::Observation->value)
            ->assertJsonPath('data.pupil_id', $pupil->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $record->id,
            'body' => 'Corrected observation body',
            'setting_term_id' => $playground->id,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'type' => EvidenceType::Observation->value,
            'pupil_id' => $pupil->id,
            'author_id' => $teacher->id,
        ]);

        $version = EvidenceRecordVersion::query()
            ->where('evidence_record_id', $record->id)
            ->first();

        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version);
        $this->assertSame('Original observation body', $version->snapshot['body'] ?? null);
        $this->assertSame($classroom->id, $version->snapshot['setting_term_id'] ?? null);
        $this->assertSame((string) $teacher->id, (string) ($version->snapshot['author_id'] ?? ''));
        $this->assertSame(EvidenceType::Observation->value, $version->snapshot['type'] ?? null);
        $this->assertSame((string) $teacher->id, (string) $version->superseded_by);

        $versions = $this->actingAs($teacher)->getJson("/api/v1/evidence/{$record->id}/versions");
        $versions->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals(1, $versions->json('data.0.version'));
        $this->assertSame('Original observation body', $versions->json('data.0.snapshot.body'));

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationUpdated->value)
            ->where('resource_id', $record->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('evidence_amended', $audit->metadata['reason'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($pupil): bool {
            return $job->tenantId === $pupil->tenant_id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_amended';
        });
    }

    public function test_senco_can_amend_teacher_authored_submitted_evidence(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Teacher wrote this']);

        $response = $this->actingAs($senco)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'SENCO corrected this',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.body', 'SENCO corrected this');
        $this->assertSame((string) $teacher->id, (string) $response->json('data.author_id'));

        $this->assertDatabaseHas('evidence_record_versions', [
            'evidence_record_id' => $record->id,
            'superseded_by' => $senco->id,
        ]);
        $this->assertEquals(1, EvidenceRecordVersion::query()->where('evidence_record_id', $record->id)->value('version'));

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($pupil): bool {
            return $job->reason === 'evidence_amended' && $job->pupilId === $pupil->id;
        });
    }

    public function test_school_leader_cannot_amend_submitted_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Visible to leader']);

        $this->assertForbidden(
            $this->actingAs($leader)->patchJson("/api/v1/evidence/{$record->id}", [
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Leader must not amend',
            ])
        );
    }

    public function test_unassigned_or_non_author_teacher_cannot_amend(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $author = User::factory()->forTenant($tenant)->teacher()->create();
        $author->schools()->attach($school->id);
        $other = User::factory()->forTenant($tenant)->teacher()->create();
        $other->schools()->attach($school->id);
        $assigned = Pupil::factory()->forSchool($school)->assignedTo($author)->create();
        $assigned->assignedUsers()->attach($other->id);
        $unassigned = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $authoredAssigned = EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($author)
            ->withSetting($setting)
            ->create(['body' => 'Author row']);

        $this->assertForbidden(
            $this->actingAs($other)->patchJson("/api/v1/evidence/{$authoredAssigned->id}", [
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Non-author must not amend',
            ])
        );

        $authoredUnassigned = EvidenceRecord::factory()
            ->forPupil($unassigned)
            ->authoredBy($author)
            ->withSetting($setting)
            ->create(['body' => 'Unassigned pupil row']);

        $this->assertForbidden(
            $this->actingAs($author)->patchJson("/api/v1/evidence/{$authoredUnassigned->id}", [
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Unassigned author must not amend',
            ])
        );
    }

    public function test_invalid_ontology_term_returns_422_on_amend(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Valid body']);

        $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => '01INVALIDTERM000000000000',
            'body' => 'Still valid notes',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['setting_term_id']);
    }

    public function test_type_or_pupil_change_is_prohibited_on_amend(): void
    {
        [, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $otherPupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Stable identity']);

        $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'type' => 'intervention',
            'pupil_id' => $otherPupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Attempted identity change',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'pupil_id']);
    }

    public function test_draft_cannot_be_amended_via_evidence_path(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->draft()
            ->create(['body' => 'Still a draft']);

        $this->assertForbidden(
            $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$draft->id}", [
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Must use draft APIs',
            ])
        );
    }

    public function test_versions_list_newest_first_after_multiple_amends(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $classroom = $this->settingTerm('CLASSROOM');
        $playground = $this->settingTerm('PLAYGROUND');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($classroom)
            ->create(['body' => 'Version zero body']);

        $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHours(3)->utc()->toIso8601String(),
            'setting_term_id' => $classroom->id,
            'body' => 'After first amend',
        ])->assertOk();

        $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHours(2)->utc()->toIso8601String(),
            'setting_term_id' => $playground->id,
            'body' => 'After second amend',
        ])->assertOk();

        $versions = $this->actingAs($teacher)->getJson("/api/v1/evidence/{$record->id}/versions");
        $versions->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([2, 1], collect($versions->json('data'))->pluck('version')->all());
        $this->assertSame('After first amend', $versions->json('data.0.snapshot.body'));
        $this->assertSame('Version zero body', $versions->json('data.1.snapshot.body'));
        $this->assertSame('After second amend', $record->fresh()->body);
    }

    public function test_tenant_admin_cannot_amend(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Admin cannot amend']);

        $this->assertForbidden(
            $this->actingAs($admin)->patchJson("/api/v1/evidence/{$record->id}", [
                'occurred_at' => now()->subHour()->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Nope',
            ])
        );
    }

    public function test_author_can_amend_submitted_intervention_with_history_audit_and_sre(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $universal = $this->provisionTerm('UNIVERSAL');
        $targeted = $this->provisionTerm('TARGETED_GROUP');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withProvision($universal)
            ->create([
                'body' => 'Original intervention notes',
                'occurred_at' => now()->subDay()->utc(),
            ]);

        $response = $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHours(2)->utc()->toIso8601String(),
            'provision_term_id' => $targeted->id,
            'body' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.provision.id', $targeted->id)
            ->assertJsonPath('data.type', EvidenceType::Intervention->value);

        $version = EvidenceRecordVersion::query()
            ->where('evidence_record_id', $record->id)
            ->first();

        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version);
        $this->assertSame('Original intervention notes', $version->snapshot['body'] ?? null);
        $this->assertSame($universal->id, $version->snapshot['provision_term_id'] ?? null);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceInterventionUpdated->value)
            ->where('resource_id', $record->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('evidence_amended', $audit->metadata['reason'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($pupil): bool {
            return $job->tenantId === $pupil->tenant_id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_amended';
        });
    }

    public function test_author_can_amend_submitted_response_with_history_and_audit(): void
    {
        Queue::fake();

        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $intervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withProvision($provision)
            ->create(['body' => 'Linked intervention']);

        $otherIntervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withProvision($provision)
            ->create(['body' => 'Other intervention']);

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->response($intervention)
            ->create(['body' => 'Original response body']);

        $response = $this->actingAs($teacher)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'related_intervention_id' => $otherIntervention->id,
            'body' => 'Corrected response body',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.body', 'Corrected response body')
            ->assertJsonPath('data.related_intervention_id', $otherIntervention->id)
            ->assertJsonPath('data.type', EvidenceType::Response->value);

        $version = EvidenceRecordVersion::query()
            ->where('evidence_record_id', $record->id)
            ->first();

        $this->assertNotNull($version);
        $this->assertSame('Original response body', $version->snapshot['body'] ?? null);
        $this->assertSame($intervention->id, $version->snapshot['related_intervention_id'] ?? null);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceResponseUpdated->value)
            ->where('resource_id', $record->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('evidence_amended', $audit->metadata['reason'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($pupil): bool {
            return $job->reason === 'evidence_amended' && $job->pupilId === $pupil->id;
        });
    }

    public function test_support_staff_author_assigned_can_amend_observation(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($support)->create();
        $classroom = $this->settingTerm('CLASSROOM');
        $playground = $this->settingTerm('PLAYGROUND');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($support)
            ->withSetting($classroom)
            ->create(['body' => 'Support authored observation']);

        $this->actingAs($support)->patchJson("/api/v1/evidence/{$record->id}", [
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $playground->id,
            'body' => 'Support corrected observation',
        ])->assertOk()
            ->assertJsonPath('data.body', 'Support corrected observation')
            ->assertJsonPath('data.setting.id', $playground->id);

        Queue::assertPushed(SreReevaluatePupil::class);
    }

    public function test_versions_list_forbidden_for_user_who_cannot_view(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $unassigned = User::factory()->forTenant($tenant)->teacher()->create();
        $unassigned->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $record = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Visible only to scoped viewers']);

        $this->assertForbidden(
            $this->actingAs($admin)->getJson("/api/v1/evidence/{$record->id}/versions")
        );

        $this->assertForbidden(
            $this->actingAs($unassigned)->getJson("/api/v1/evidence/{$record->id}/versions")
        );
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
