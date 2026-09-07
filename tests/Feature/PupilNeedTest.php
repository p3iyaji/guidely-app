<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NeedOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PupilNeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NeedOntologySeeder::class);
    }

    public function test_senco_can_set_primary_and_secondary_need_from_seeded_terms(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $primary = $this->needTerm('CI');
        $secondary = $this->needTerm('CL');

        $response = $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => $primary->id,
            'primary_need_notes' => 'Classroom strategies focus on vocabulary.',
            'secondary_need_term_id' => $secondary->id,
            'secondary_need_notes' => 'Working memory support.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.primary_need.id', $primary->id)
            ->assertJsonPath('data.primary_need.code', 'CI')
            ->assertJsonPath('data.primary_need.label', 'Communication and interaction')
            ->assertJsonPath('data.primary_need.notes', 'Classroom strategies focus on vocabulary.')
            ->assertJsonPath('data.secondary_need.id', $secondary->id)
            ->assertJsonPath('data.secondary_need.code', 'CL')
            ->assertJsonPath('data.secondary_need.label', 'Cognition and learning')
            ->assertJsonPath('data.secondary_need.notes', 'Working memory support.');

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'primary_need_term_id' => $primary->id,
            'secondary_need_term_id' => $secondary->id,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilUpdated->value)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($primary->id, $audit->metadata['primary_need_term_id'] ?? null);
        $this->assertSame('CI', $audit->metadata['primary_need_term_code'] ?? null);
        $this->assertSame($secondary->id, $audit->metadata['secondary_need_term_id'] ?? null);
        $this->assertSame('CL', $audit->metadata['secondary_need_term_code'] ?? null);
    }

    public function test_tenant_admin_can_set_primary_need_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $primary = $this->needTerm('SEMH');

        $response = $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Jordan',
            'family_name' => 'Lee',
            'year_group' => 'Year 8',
            'send_status' => SendStatus::SenSupport->value,
            'primary_need_term_id' => $primary->id,
            'primary_need_notes' => 'Check-ins after transitions.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.primary_need.id', $primary->id)
            ->assertJsonPath('data.primary_need.code', 'SEMH')
            ->assertJsonPath('data.secondary_need', null);
    }

    public function test_free_text_need_labels_are_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need' => 'ASD',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need']);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'diagnosis' => 'ADHD',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['diagnosis']);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'primary_need_term_id' => null,
        ]);
    }

    public function test_invalid_and_inactive_need_term_ids_are_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $inactive = NeedTerm::factory()->inactive()->create([
            'code' => 'INACTIVE',
            'label' => 'Inactive term',
        ]);
        $draftVersion = OntologyVersion::factory()->create([
            'status' => OntologyVersionStatus::Draft,
        ]);
        $draftTerm = NeedTerm::factory()->forVersion($draftVersion)->create([
            'code' => 'DRAFT',
            'label' => 'Draft-only term',
        ]);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => '01INVALIDTERM0ID0000000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need_term_id']);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => $inactive->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need_term_id']);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => $draftTerm->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need_term_id']);
    }

    public function test_secondary_need_must_differ_from_primary(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $term = $this->needTerm('SP');
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($term)->create();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'secondary_need_term_id' => $term->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['secondary_need_term_id']);
    }

    public function test_clearing_secondary_need_succeeds_and_is_audited(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $primary = $this->needTerm('CI');
        $secondary = $this->needTerm('CL');
        $pupil = Pupil::factory()
            ->forSchool($school)
            ->withPrimaryNeed($primary)
            ->withSecondaryNeed($secondary, 'Temporary note')
            ->create();

        $response = $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'secondary_need_term_id' => null,
            'secondary_need_notes' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.primary_need.id', $primary->id)
            ->assertJsonPath('data.secondary_need', null);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'primary_need_term_id' => $primary->id,
            'secondary_need_term_id' => null,
            'secondary_need_notes' => null,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilUpdated->value)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertIsArray($audit->metadata, 'audit metadata: '.json_encode($audit->getAttributes()));
        $this->assertArrayHasKey('secondary_need_term_id', $audit->metadata);
        $this->assertNull($audit->metadata['secondary_need_term_id']);
        $this->assertSame($primary->id, $audit->metadata['primary_need_term_id'] ?? null);
    }

    public function test_teacher_cannot_show_pupil_need_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $primary = $this->needTerm('CI');
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($primary)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $response = $this->actingAs($teacher)->getJson('/api/v1/pupils/'.$pupil->id);

        $response->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
        $this->assertStringNotContainsString($primary->id, $response->getContent());
        $this->assertStringNotContainsString('Communication and interaction', $response->getContent());
    }

    public function test_cross_tenant_pupil_need_update_returns_404(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $term = $this->needTerm('CI');

        $this->actingAs($adminA)->patchJson('/api/v1/pupils/'.$pupilB->id, [
            'primary_need_term_id' => $term->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('pupils', [
            'id' => $pupilB->id,
            'tenant_id' => $tenantB->id,
            'primary_need_term_id' => null,
        ]);
    }

    public function test_need_ontology_seeder_exposes_published_stub_terms(): void
    {
        $version = OntologyVersion::query()
            ->where('code', PilotOntology::VERSION_CODE)
            ->first();

        $this->assertNotNull($version);
        $this->assertSame(OntologyVersionStatus::Published, $version->status);
        $this->assertSame(
            count(NeedOntologySeeder::NEED_TERMS),
            NeedTerm::query()->forTenant()->count()
        );
    }

    public function test_create_with_need_records_audit_metadata(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $primary = $this->needTerm('SEMH');

        $response = $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Audit',
            'family_name' => 'Create',
            'year_group' => 'Year 8',
            'send_status' => SendStatus::SenSupport->value,
            'primary_need_term_id' => $primary->id,
            'primary_need_notes' => 'Create notes.',
        ]);

        $response->assertCreated();
        $pupilId = $response->json('data.id');

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilCreated->value)
            ->where('resource_id', $pupilId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($primary->id, $audit->metadata['primary_need_term_id'] ?? null);
        $this->assertSame('SEMH', $audit->metadata['primary_need_term_code'] ?? null);
        $this->assertSame('Create notes.', $audit->metadata['primary_need_notes'] ?? null);
    }

    public function test_database_seeder_loads_need_ontology_stub(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('ontology_versions', [
            'code' => PilotOntology::VERSION_CODE,
            'status' => OntologyVersionStatus::Published->value,
        ]);
        $this->assertSame(
            count(NeedOntologySeeder::NEED_TERMS),
            NeedTerm::query()->forTenant()->count()
        );
    }

    public function test_secondary_without_primary_is_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $secondary = $this->needTerm('CL');

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'secondary_need_term_id' => $secondary->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['secondary_need_term_id']);
    }

    public function test_notes_without_term_are_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_notes' => 'Orphan notes',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need_notes']);
    }

    public function test_create_rejects_matching_primary_and_secondary_in_one_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $term = $this->needTerm('CI');

        $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Same',
            'family_name' => 'Need',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
            'primary_need_term_id' => $term->id,
            'secondary_need_term_id' => $term->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['secondary_need_term_id']);
    }

    public function test_inactive_secondary_need_term_is_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $primary = $this->needTerm('CI');
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($primary)->create();
        $inactive = NeedTerm::factory()->inactive()->create([
            'ontology_version_id' => $primary->ontology_version_id,
            'code' => 'INACTIVE',
        ]);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'secondary_need_term_id' => $inactive->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['secondary_need_term_id']);
    }

    public function test_support_staff_cannot_show_pupil_need_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $primary = $this->needTerm('CI');
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($primary)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);

        $this->actingAs($support)->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    public function test_notes_only_change_is_audited(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $primary = $this->needTerm('CI');
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($primary)->create([
            'primary_need_notes' => 'Before',
        ]);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_notes' => 'After notes only',
        ])->assertOk()
            ->assertJsonPath('data.primary_need.notes', 'After notes only');

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilUpdated->value)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('After notes only', $audit->metadata['primary_need_notes'] ?? null);
        $this->assertSame('CI', $audit->metadata['primary_need_term_code'] ?? null);
    }

    public function test_clearing_primary_need_also_clears_secondary_and_notes(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $primary = $this->needTerm('CI');
        $secondary = $this->needTerm('CL');
        $pupil = Pupil::factory()->forSchool($school)->create([
            'primary_need_term_id' => $primary->id,
            'primary_need_notes' => 'Primary notes',
            'secondary_need_term_id' => $secondary->id,
            'secondary_need_notes' => 'Secondary notes',
        ]);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.primary_need', null)
            ->assertJsonPath('data.secondary_need', null);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'primary_need_term_id' => null,
            'primary_need_notes' => null,
            'secondary_need_term_id' => null,
            'secondary_need_notes' => null,
        ]);
    }

    public function test_additional_free_text_need_keys_are_rejected(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'need' => 'ASD',
            'secondary_need' => 'ADHD',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['need', 'secondary_need']);
    }

    private function needTerm(string $code): NeedTerm
    {
        $term = NeedTerm::query()->forTenant()->where('code', $code)->first();
        $this->assertNotNull($term, "Expected seeded Need term [{$code}]");

        return $term;
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
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenants(): array
    {
        return [
            Tenant::factory()->school()->create(['name' => 'Tenant A']),
            Tenant::factory()->school()->create(['name' => 'Tenant B']),
        ];
    }
}
