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
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InterventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_teacher_can_submit_intervention_for_assigned_pupil(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Used visual timetable before the transition.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', EvidenceType::Intervention->value)
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.source', EvidenceSource::Capture->value)
            ->assertJsonPath('data.external_id', null)
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.author_id', $teacher->id)
            ->assertJsonPath('data.provision.id', $provision->id)
            ->assertJsonPath('data.provision.code', 'UNIVERSAL')
            ->assertJsonPath('data.setting', null)
            ->assertJsonPath('data.body', 'Used visual timetable before the transition.');

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'author_id' => $teacher->id,
            'type' => EvidenceType::Intervention->value,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'provision_term_id' => $provision->id,
            'setting_term_id' => null,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceInterventionCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('web', $audit->metadata['client_type'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($provision->id, $audit->metadata['provision_term_id'] ?? null);
        $this->assertSame('UNIVERSAL', $audit->metadata['provision_term_code'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
    }

    public function test_teacher_cannot_submit_intervention_for_unassigned_pupil(): void
    {
        [, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $otherPupil = Pupil::factory()->forSchool($school)->create();
        $provision = $this->provisionTerm('UNIVERSAL');

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $otherPupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_missing_or_free_text_provision_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $missing = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'body' => 'Used visual timetable.',
        ]);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);

        $freeText = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision' => 'Universal classroom strategies',
            'body' => 'Used visual timetable.',
        ]);

        $freeText->assertStatus(422)
            ->assertJsonValidationErrors(['provision']);
    }

    public function test_setting_term_id_is_rejected_on_intervention_create(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'setting_term_id' => '01hsettingterm00000000000',
            'body' => 'Used visual timetable.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);
    }

    public function test_related_intervention_id_is_rejected_on_intervention_create(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'related_intervention_id' => '01hintervention00000000000',
            'body' => 'Used visual timetable.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['related_intervention_id']);
    }

    public function test_inactive_or_non_stub_provision_term_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $inactive = $this->provisionTerm('UNIVERSAL');
        $inactive->forceFill(['is_active' => false])->save();

        $inactiveResponse = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $inactive->id,
            'body' => 'Used visual timetable.',
        ]);

        $inactiveResponse->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);

        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-provision-stub',
        ]);
        $nonStub = ProvisionTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'OTHER',
            'label' => 'Other provision',
        ]);

        $nonStubResponse = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $nonStub->id,
            'body' => 'Used visual timetable.',
        ]);

        $nonStubResponse->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);
    }

    public function test_future_occurred_at_is_rejected(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('UNIVERSAL');

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->addDay()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Used visual timetable.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_tenant_admin_cannot_capture_intervention(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $provision = $this->provisionTerm('UNIVERSAL');

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('capture-evidence'));

        $response = $this->actingAs($admin)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Should not be allowed.',
        ]);

        $this->assertForbidden($response);
    }

    public function test_senco_can_submit_intervention_for_in_school_pupil(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $provision = $this->provisionTerm('TARGETED_GROUP');

        $response = $this->actingAs($senco)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subMinutes(30)->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Small-group phonics session completed.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lifecycle', EvidenceLifecycle::Submitted->value)
            ->assertJsonPath('data.author_id', $senco->id);

        $this->assertDatabaseHas('evidence_records', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'type' => EvidenceType::Intervention->value,
        ]);
    }

    public function test_hybrid_client_type_is_recorded_in_audit_metadata(): void
    {
        [, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $provision = $this->provisionTerm('SENSORY');

        $response = $this->actingAs($teacher)
            ->withHeader('X-Client-Type', 'hybrid')
            ->postJson('/api/v1/interventions', [
                'pupil_id' => $pupil->id,
                'occurred_at' => now()->subMinutes(15)->utc()->toIso8601String(),
                'provision_term_id' => $provision->id,
                'body' => 'Offered quiet corner before assembly.',
            ]);

        $response->assertCreated();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::EvidenceInterventionCreated->value)
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
        $provision = $this->provisionTerm('ONE_TO_ONE');

        $response = $this->actingAs($support)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'client_type' => 'web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.author_id', $support->id)
            ->assertJsonPath('data.body', null);
    }

    public function test_capture_roles_can_list_provision_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/provision-terms');

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'UNIVERSAL');

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_provision_terms_index_omits_inactive_and_non_stub_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $inactive = $this->provisionTerm('SEMH_SUPPORT');
        $inactive->forceFill(['is_active' => false])->save();

        $draftVersion = OntologyVersion::factory()->create([
            'code' => 'draft-provision',
            'status' => OntologyVersionStatus::Draft,
        ]);
        ProvisionTerm::factory()->forVersion($draftVersion)->create([
            'code' => 'DRAFT_ONLY',
            'label' => 'Draft only',
        ]);

        $otherPublished = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-provision',
        ]);
        ProvisionTerm::factory()->forVersion($otherPublished)->create([
            'code' => 'FOREIGN',
            'label' => 'Foreign term',
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/provision-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('UNIVERSAL', $codes);
        $this->assertNotContains('SEMH_SUPPORT', $codes);
        $this->assertNotContains('DRAFT_ONLY', $codes);
        $this->assertNotContains('FOREIGN', $codes);
    }

    public function test_tenant_admin_cannot_list_provision_terms(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/provision-terms');

        $this->assertForbidden($response);
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    private function provisionTerm(string $code): ProvisionTerm
    {
        $term = ProvisionTerm::query()->fromPublishedStub()->where('code', $code)->first();
        $this->assertNotNull($term, "Expected seeded Provision term [{$code}]");

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
