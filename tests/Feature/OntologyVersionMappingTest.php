<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\NeedOntologySeeder;
use Database\Seeders\OutcomeOntologySeeder;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\RelationshipOntologySeeder;
use Database\Seeders\SettingOntologySeeder;
use Database\Seeders\ThresholdOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OntologyVersionMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            NeedOntologySeeder::class,
            SettingOntologySeeder::class,
            ProvisionOntologySeeder::class,
            OutcomeOntologySeeder::class,
            ThresholdOntologySeeder::class,
            RelationshipOntologySeeder::class,
        ]);
    }

    public function test_capture_roles_can_list_all_fr23_domains_and_setting_for_effective_version(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        $version = PilotOntology::ensurePublishedVersion();

        $endpoints = [
            '/api/v1/ontology/need-terms' => 'CI',
            '/api/v1/ontology/provision-terms' => 'UNIVERSAL',
            '/api/v1/ontology/outcome-terms' => 'ENGAGEMENT',
            '/api/v1/ontology/threshold-terms' => 'INITIAL_REVIEW',
            '/api/v1/ontology/relationship-mappings' => 'CI_TO_UNIVERSAL',
            '/api/v1/ontology/setting-terms' => 'CLASSROOM',
        ];

        foreach ($endpoints as $path => $expectedCode) {
            $response = $this->actingAs($teacher)->getJson($path);

            $response->assertOk()
                ->assertJsonPath('data.0.ontology_version_id', $version->id);

            $codes = collect($response->json('data'))->pluck('code')->all();
            $this->assertContains($expectedCode, $codes, "Expected [{$expectedCode}] on {$path}");
        }
    }

    public function test_list_endpoints_return_pinned_published_version_terms(): void
    {
        [$tenant, , $teacher] = $this->tenantSchoolAndTeacher();

        $pinned = OntologyVersion::factory()->published()->create([
            'code' => 'pinned-ontology-v2',
            'label' => 'Pinned Ontology v2',
        ]);

        SettingTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_SETTING',
            'label' => 'Pinned setting',
            'sort_order' => 1,
        ]);
        NeedTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_NEED',
            'label' => 'Pinned need',
            'sort_order' => 1,
        ]);
        ProvisionTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_PROVISION',
            'label' => 'Pinned provision',
            'sort_order' => 1,
        ]);
        OutcomeTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_OUTCOME',
            'label' => 'Pinned outcome',
            'sort_order' => 1,
        ]);
        ThresholdTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_THRESHOLD',
            'label' => 'Pinned threshold',
            'sort_order' => 1,
        ]);
        RelationshipMapping::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_REL',
            'label' => 'Pinned relationship',
            'sort_order' => 1,
        ]);

        $tenant->forceFill(['current_ontology_version_id' => $pinned->id])->save();

        $endpoints = [
            '/api/v1/ontology/setting-terms' => 'PINNED_SETTING',
            '/api/v1/ontology/need-terms' => 'PINNED_NEED',
            '/api/v1/ontology/provision-terms' => 'PINNED_PROVISION',
            '/api/v1/ontology/outcome-terms' => 'PINNED_OUTCOME',
            '/api/v1/ontology/threshold-terms' => 'PINNED_THRESHOLD',
            '/api/v1/ontology/relationship-mappings' => 'PINNED_REL',
        ];

        foreach ($endpoints as $path => $expectedCode) {
            $response = $this->actingAs($teacher)->getJson($path);

            $response->assertOk()
                ->assertJsonPath('data.0.ontology_version_id', $pinned->id)
                ->assertJsonPath('data.0.code', $expectedCode);

            $versionIds = collect($response->json('data'))->pluck('ontology_version_id')->unique()->all();
            $this->assertSame([$pinned->id], $versionIds);
        }
    }

    public function test_observation_rejects_setting_from_non_effective_version(): void
    {
        [$tenant, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-ontology-v2',
        ]);
        $foreignSetting = SettingTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_SETTING',
            'label' => 'Foreign setting',
        ]);

        $response = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $foreignSetting->id,
            'body' => 'Observed during literacy.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['setting_term_id']);

        $this->assertDatabaseMissing('evidence_records', [
            'pupil_id' => $pupil->id,
            'setting_term_id' => $foreignSetting->id,
        ]);

        $tenant->forceFill(['current_ontology_version_id' => $otherVersion->id])->save();

        $accepted = $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $foreignSetting->id,
            'body' => 'Observed after pin change.',
        ]);

        $accepted->assertCreated()
            ->assertJsonPath('data.setting.id', $foreignSetting->id);
    }

    public function test_intervention_rejects_then_accepts_provision_after_pin(): void
    {
        [$tenant, , $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();

        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-provision-v2',
        ]);
        $foreignProvision = ProvisionTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_PROVISION',
            'label' => 'Foreign provision',
        ]);

        $response = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $foreignProvision->id,
            'body' => 'Tried a foreign provision.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provision_term_id']);

        $this->assertDatabaseMissing('evidence_records', [
            'pupil_id' => $pupil->id,
            'provision_term_id' => $foreignProvision->id,
        ]);

        $tenant->forceFill(['current_ontology_version_id' => $otherVersion->id])->save();

        $accepted = $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $foreignProvision->id,
            'body' => 'Accepted after pin change.',
        ]);

        $accepted->assertCreated()
            ->assertJsonPath('data.provision.id', $foreignProvision->id);
    }

    public function test_pupil_need_rejects_then_accepts_term_after_pin(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-need-v2',
        ]);
        $foreignNeed = NeedTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_NEED',
            'label' => 'Foreign need',
        ]);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => $foreignNeed->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_need_term_id']);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'primary_need_term_id' => null,
        ]);

        $tenant->forceFill(['current_ontology_version_id' => $otherVersion->id])->save();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'primary_need_term_id' => $foreignNeed->id,
        ])->assertOk()
            ->assertJsonPath('data.primary_need.id', $foreignNeed->id);
    }

    public function test_import_succeeds_with_provision_code_on_pinned_version(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();

        $pinned = OntologyVersion::factory()->published()->create([
            'code' => 'import-pinned-v2',
        ]);
        ProvisionTerm::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_IMPORT_PROV',
            'label' => 'Pinned import provision',
        ]);
        $tenant->forceFill(['current_ontology_version_id' => $pinned->id])->save();

        $occurredAt = now()->subDay()->utc()->toIso8601String();
        $csv = "pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes,evidence_type,evidence_provision_code,evidence_occurred_at,evidence_external_id,evidence_body\n"
            .'MIS-PIN,Alex,Taylor,,'.$school->name.',,Year 8,sen_support,,intervention,PINNED_IMPORT_PROV,'.$occurredAt.',ext-pin,Pinned provision import.'."\n";

        $response = $this->actingAs($senco)->post('/api/v1/import/pupils', [
            'file' => UploadedFile::fake()->createWithContent('pupils.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.error_count', 0)
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.committed.0.evidence_action', 'evidence_created');

        $provision = ProvisionTerm::query()
            ->forVersion($pinned->id)
            ->where('code', 'PINNED_IMPORT_PROV')
            ->firstOrFail();

        $this->assertDatabaseHas('evidence_records', [
            'tenant_id' => $tenant->id,
            'provision_term_id' => $provision->id,
            'external_id' => 'ext-pin',
        ]);
    }

    public function test_import_rejects_unknown_provision_code_without_storing_fk(): void
    {
        $this->seed(ProvisionOntologySeeder::class);
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $occurredAt = now()->subDay()->utc()->toIso8601String();

        $csv = "pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes,evidence_type,evidence_provision_code,evidence_occurred_at,evidence_external_id,evidence_body\n"
            .'MIS-UNK,Alex,Taylor,,'.$school->name.',,Year 8,sen_support,,intervention,NOT_A_REAL_CODE,'.$occurredAt.',ext-unk,Unknown provision.'."\n";

        $response = $this->actingAs($senco)->post('/api/v1/import/pupils', [
            'file' => UploadedFile::fake()->createWithContent('pupils.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.error_count', 1)
            ->assertJsonPath('data.errors.0.message', 'Evidence Provision code must match an active published Ontology term.');

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    public function test_school_roles_have_no_ontology_publish_endpoint(): void
    {
        [$tenant, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        foreach ([$teacher, $senco] as $user) {
            $this->actingAs($user)->postJson('/api/v1/ontology/versions', [
                'code' => 'should-not-publish',
                'label' => 'Forbidden publish',
            ])->assertNotFound();

            $this->actingAs($user)->postJson('/api/v1/ontology/publish', [])
                ->assertNotFound();
        }
    }

    public function test_tenant_admin_can_list_need_terms_but_not_capture_ontology_domains(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $version = PilotOntology::ensurePublishedVersion();

        $this->actingAs($admin)->getJson('/api/v1/ontology/need-terms')
            ->assertOk()
            ->assertJsonPath('data.0.ontology_version_id', $version->id);

        $this->actingAs($admin)->getJson('/api/v1/ontology/provision-terms')
            ->assertOk()
            ->assertJsonPath('data.0.ontology_version_id', $version->id);

        foreach ([
            '/api/v1/ontology/outcome-terms',
            '/api/v1/ontology/threshold-terms',
            '/api/v1/ontology/relationship-mappings',
            '/api/v1/ontology/setting-terms',
        ] as $path) {
            $this->actingAs($admin)->getJson($path)
                ->assertForbidden()
                ->assertJson([
                    'message' => AccessMessages::FORBIDDEN,
                    'code' => 'forbidden',
                ]);
        }
    }

    public function test_unified_pilot_ontology_owns_all_seeded_domains(): void
    {
        $version = PilotOntology::ensurePublishedVersion();

        $this->assertSame(PilotOntology::VERSION_CODE, $version->code);
        $this->assertSame(1, OntologyVersion::query()->where('code', PilotOntology::VERSION_CODE)->count());
        $this->assertGreaterThan(0, $version->needTerms()->count());
        $this->assertGreaterThan(0, $version->settingTerms()->count());
        $this->assertGreaterThan(0, $version->provisionTerms()->count());
        $this->assertGreaterThan(0, $version->outcomeTerms()->count());
        $this->assertGreaterThan(0, $version->thresholdTerms()->count());
        $this->assertGreaterThan(0, $version->relationshipMappings()->count());
    }

    public function test_draft_pin_falls_back_to_pilot_published_version(): void
    {
        [$tenant, , $teacher] = $this->tenantSchoolAndTeacher();
        $pilot = PilotOntology::ensurePublishedVersion();

        $draft = OntologyVersion::factory()->create([
            'code' => 'draft-pin-only',
            'status' => OntologyVersionStatus::Draft,
        ]);
        SettingTerm::factory()->forVersion($draft)->create([
            'code' => 'DRAFT_ONLY_SETTING',
            'label' => 'Draft only',
        ]);

        $tenant->forceFill(['current_ontology_version_id' => $draft->id])->save();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk()
            ->assertJsonPath('data.0.ontology_version_id', $pilot->id);

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('CLASSROOM', $codes);
        $this->assertNotContains('DRAFT_ONLY_SETTING', $codes);
        $this->assertSame($pilot->id, $tenant->fresh()->effectiveOntologyVersion()?->id);
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
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndSenco(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create(['name' => 'Oak Primary']);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User, 3: Pupil}
     */
    private function tenantSchoolTeacherWithAssignedPupil(): array
    {
        [$tenant, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher);

        return [$tenant, $school, $teacher, $pupil];
    }
}
