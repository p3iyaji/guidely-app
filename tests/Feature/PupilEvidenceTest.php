<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PupilEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingOntologySeeder::class);
    }

    public function test_senco_lists_submitted_evidence_in_occurred_at_desc_order(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $older = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->withSetting($setting)
            ->create([
                'occurred_at' => now()->subDays(2)->utc(),
                'body' => 'Older observation',
            ]);

        $newer = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->intervention()
            ->create([
                'occurred_at' => now()->subDay()->utc(),
                'body' => 'Newer intervention',
            ]);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->draft()
            ->withSetting($setting)
            ->create([
                'occurred_at' => now()->utc(),
                'body' => 'Draft must not appear',
            ]);

        $otherPupil = Pupil::factory()->forSchool($school)->create();
        EvidenceRecord::factory()
            ->forPupil($otherPupil)
            ->authoredBy($senco)
            ->withSetting($setting)
            ->create([
                'body' => 'Other pupil evidence',
            ]);

        $response = $this->actingAs($senco)->getJson("/api/v1/pupils/{$pupil->id}/evidence");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$newer->id, $older->id], $ids);
        $this->assertSame(EvidenceLifecycle::Submitted->value, $response->json('data.0.lifecycle'));
        $this->assertSame(EvidenceType::Intervention->value, $response->json('data.0.type'));
    }

    public function test_filters_by_type_source_and_review_note_empty(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        $observation = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->withSetting($setting)
            ->create(['body' => 'Observation row']);

        $imported = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->intervention()
            ->fromImport('ext-1')
            ->create(['body' => 'Imported intervention']);

        $responseRecord = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->response()
            ->create(['body' => 'Response row']);

        $responseObservation = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=observation");
        $responseObservation->assertOk();
        $this->assertSame([$observation->id], collect($responseObservation->json('data'))->pluck('id')->all());

        $responseIntervention = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=intervention");
        $responseIntervention->assertOk();
        $this->assertSame([$imported->id], collect($responseIntervention->json('data'))->pluck('id')->all());

        $responseResponse = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=response");
        $responseResponse->assertOk();
        $this->assertSame([$responseRecord->id], collect($responseResponse->json('data'))->pluck('id')->all());

        $responseImport = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=import");
        $responseImport->assertOk()
            ->assertJsonPath('data.0.id', $imported->id)
            ->assertJsonPath('data.0.source', EvidenceSource::Import->value);

        $responseReviewNote = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=review_note");
        $responseReviewNote->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence?filter=not-a-filter")
            ->assertUnprocessable();
    }

    public function test_support_staff_assigned_can_list_and_unassigned_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $assigned = Pupil::factory()->forSchool($school)->assignedTo($support)->create();
        $unassigned = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($support)
            ->withSetting($setting)
            ->create(['body' => 'Support evidence']);

        $this->actingAs($support)
            ->getJson("/api/v1/pupils/{$assigned->id}/evidence")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertForbidden(
            $this->actingAs($support)->getJson("/api/v1/pupils/{$unassigned->id}/evidence")
        );
    }

    public function test_senco_without_school_access_cannot_list_pupil_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($schoolA->id);
        $pupil = Pupil::factory()->forSchool($schoolB)->create();

        $this->assertForbidden(
            $this->actingAs($senco)->getJson("/api/v1/pupils/{$pupil->id}/evidence")
        );
    }

    public function test_teacher_assigned_can_list_and_unassigned_is_forbidden(): void
    {
        [$tenant, $school, $teacher, $assigned] = $this->tenantSchoolTeacherWithAssignedPupil();
        $unassigned = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Assigned evidence']);

        $this->actingAs($teacher)
            ->getJson("/api/v1/pupils/{$assigned->id}/evidence")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertForbidden(
            $this->actingAs($teacher)->getJson("/api/v1/pupils/{$unassigned->id}/evidence")
        );
    }

    public function test_school_leader_can_list_submitted_evidence_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $setting = $this->settingTerm('CLASSROOM');

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create(['body' => 'Leader-visible']);

        $this->actingAs($leader)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Leader-visible');

        $this->assertFalse($leader->can('create', EvidenceRecord::class));
        $this->assertTrue(Gate::forUser($leader)->allows('view-evidence'));
    }

    public function test_tenant_admin_cannot_list_pupil_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->assertForbidden(
            $this->actingAs($admin)->getJson("/api/v1/pupils/{$pupil->id}/evidence")
        );
    }

    public function test_guest_cannot_list_pupil_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->getJson("/api/v1/pupils/{$pupil->id}/evidence")->assertUnauthorized();
    }

    public function test_empty_submitted_list_returns_empty_data(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/evidence")
            ->assertOk()
            ->assertJsonPath('data', []);
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
        $term = SettingTerm::query()->fromPublishedStub()->where('code', $code)->first();
        $this->assertNotNull($term, "Expected seeded Setting term [{$code}]");

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
