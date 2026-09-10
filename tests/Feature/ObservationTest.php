<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ObservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingOntologySeeder::class);
    }

    public function test_teacher_can_submit_observation_for_assigned_pupil(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Settled after the visual timetable was shown.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Observation->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.source', EvidenceSource::Capture->value)
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.author_id', $teacher->id)
            ->assertJsonPath('data.setting.id', $setting->id)
            ->assertJsonPath('data.setting.code', 'CLASSROOM')
            ->assertJsonPath('data.body', 'Settled after the visual timetable was shown.');

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'author_id' => $teacher->id,
            'type' => EvidenceType::Observation->value,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'setting_term_id' => $setting->id,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('web', $audit->metadata['client_type'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($setting->id, $audit->metadata['setting_term_id'] ?? null);
        $this->assertSame('CLASSROOM', $audit->metadata['setting_term_code'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
    }

    public function test_teacher_cannot_submit_observation_for_unassigned_pupil(): void
    {
        [, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $otherPupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $otherPupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Observed during literacy.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_missing_or_free_text_setting_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $missing = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Observed during literacy.',
        ]);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);

        $freeText = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting' => 'Classroom',
            'body' => 'Observed during literacy.',
        ]);

        $freeText->assertStatus(422)
            ->assertJsonValidationErrors(['setting']);
    }

    public function test_provision_term_id_is_rejected_on_observation_create(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'provision_term_id' => '01hprovisionterm000000000',
            'body' => 'Observed during literacy.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);
    }

    public function test_related_intervention_id_is_rejected_on_observation_create(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'related_intervention_id' => '01hintervention00000000000',
            'body' => 'Observed during literacy.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);
    }

    public function test_inactive_or_non_stub_setting_term_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $inactive = $this->settingTerm('CLASSROOM');
        $inactive->forceFill(['is_active' => false])->save();

        $inactiveResponse = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $inactive->id,
            'body' => 'Observed during literacy.',
        ]);

        $inactiveResponse->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);

        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-setting-stub',
        ]);
        $nonStub = SettingTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'OTHER',
            'label' => 'Other setting',
        ]);

        $nonStubResponse = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $nonStub->id,
            'body' => 'Observed during literacy.',
        ]);

        $nonStubResponse->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);
    }

    public function test_blank_body_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('PLAYGROUND');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => '   ',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_future_occurred_at_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->addDay()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Observed during literacy.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_soft_deleted_or_unknown_pupil_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('CLASSROOM');

        $pupil->delete();

        $left = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Observed during literacy.',
        ]);

        $left->assertStatus(422)
            ->assertJsonValidationErrors(['pupil_id']);

        $unknown = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => '01hunknown0000000000000000',
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Observed during literacy.',
        ]);

        $unknown->assertStatus(422)
            ->assertJsonValidationErrors(['pupil_id']);
    }

    public function test_tenant_admin_cannot_capture_observation(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('capture-evidence'));

        $response = $this->actingAs($admin)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
    }

    public function test_senco_can_submit_observation_for_in_school_pupil(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('SMALL_GROUP');

        $response = $this->actingAs($senco)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(30)->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Engaged well in small-group reading.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.author_id', $senco->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
        ]);
    }

    public function test_senco_without_school_access_cannot_capture_for_pupil(): void
    {
        $tenant = Tenant::factory()->create();
        $accessible = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($accessible->id);
        $pupil = Pupil::factory()->forSchool($otherSchool)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($senco)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_hybrid_client_type_is_recorded_in_audit_metadata(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $setting = $this->settingTerm('TRANSITION');

        $response = $this->actingAs($teacher)
            ->withHeader('X-Client-Type', 'hybrid')
            ->postJson('/api/v1/observations', [
                'pupil_id' => $pupil->id,
                'occurred_at' => now()->subMinutes(15)->utc()->toIso8601String(),
                'setting_term_id' => $setting->id,
                'body' => 'Needed prompt at the classroom door.',
            ]);

        $response->assertCreated();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceObservationCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('hybrid', $audit->metadata['client_type'] ?? null);
    }

    public function test_support_staff_can_submit_for_assigned_pupil(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($support)->create();
        $setting = $this->settingTerm('ONE_TO_ONE');

        $response = $this->actingAs($support)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Used sensory break protocol calmly.',
            'client_type' => 'web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.author_id', $support->id);
    }

    public function test_school_leader_cannot_capture_observation(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $response = $this->actingAs($leader)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
    }

    public function test_capture_roles_can_list_setting_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'CLASSROOM');

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_setting_terms_index_omits_inactive_and_non_stub_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $inactive = $this->settingTerm('LUNCH');
        $inactive->forceFill(['is_active' => false])->save();

        $draftVersion = OntologyVersion::factory()->create([
            'code' => 'draft-setting',
            'status' => OntologyVersionStatus::Draft,
        ]);
        SettingTerm::factory()->forVersion($draftVersion)->create([
            'code' => 'DRAFT_ONLY',
            'label' => 'Draft only',
        ]);

        $otherPublished = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-setting',
        ]);
        SettingTerm::factory()->forVersion($otherPublished)->create([
            'code' => 'FOREIGN',
            'label' => 'Foreign term',
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('CLASSROOM', $codes);
        $this->assertNotContains('LUNCH', $codes);
        $this->assertNotContains('DRAFT_ONLY', $codes);
        $this->assertNotContains('FOREIGN', $codes);
    }

    public function test_tenant_admin_can_list_setting_terms_for_the_read_only_catalogue(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk();
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
