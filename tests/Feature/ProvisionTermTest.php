<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\ProvisionTermController;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionTermTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvisionOntologySeeder::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/provision-terms')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_provision_terms(): void
    {
        [$tenant, $admin] = $this->tenantAndAdmin();
        $inactive = ProvisionTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'SEMH_SUPPORT')
            ->firstOrFail();
        $inactive->forceFill(['is_active' => false])->save();

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/provision-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('UNIVERSAL', $codes);
        $this->assertContains('SEMH_SUPPORT', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'SEMH_SUPPORT')['is_active']);
        $this->assertSame($tenant->id, $admin->tenant_id);
    }

    public function test_teacher_index_omits_inactive_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        $inactive = ProvisionTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'SEMH_SUPPORT')
            ->firstOrFail();
        $inactive->forceFill(['is_active' => false])->save();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/provision-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('UNIVERSAL', $codes);
        $this->assertNotContains('SEMH_SUPPORT', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/provision-terms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/provision-terms', [
            'code' => 'MENTORING',
            'label' => 'Peer mentoring',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('provision_terms', ['code' => 'MENTORING']);
    }

    public function test_tenant_admin_creates_a_provision_term_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = PilotOntology::ensurePublishedVersion()->id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/provision-terms', [
            'code' => '  mentoring  ',
            'label' => '  Peer mentoring  ',
            'sort_order' => 12,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'MENTORING')
            ->assertJsonPath('data.label', 'Peer mentoring')
            ->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ontology_version_id', $versionId);

        $term = ProvisionTerm::query()->where('code', 'MENTORING')->first();
        $this->assertNotNull($term);
        $this->assertSame($versionId, $term->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::ProvisionTermCreated->value,
            'resource_type' => 'provision_term',
            'resource_id' => $term->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/provision-terms', [
            'code' => 'UNIVERSAL',
            'label' => 'Another universal',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'A Provision term with this code already exists on this Ontology version.',
            );
    }

    public function test_tenant_admin_updates_a_provision_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ProvisionTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/provision-terms/'.$term->id, [
            'label' => 'Universal strategies (updated)',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Universal strategies (updated)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('provision_terms', [
            'id' => $term->id,
            'label' => 'Universal strategies (updated)',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::ProvisionTermUpdated->value)
                ->where('resource_id', $term->id)
                ->count(),
        );
    }

    public function test_cross_version_provision_term_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-provision-crud',
        ]);
        $foreign = ProvisionTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret provision',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/provision-terms/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret provision', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('provision_terms', [
            'id' => $foreign->id,
            'label' => 'Secret provision',
        ]);
    }

    public function test_delete_returns_409_when_the_term_is_used_on_evidence(): void
    {
        [$tenant, $school, $teacher, $pupil] = $this->tenantSchoolTeacherWithAssignedPupil();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $term = ProvisionTerm::factory()->create([
            'code' => 'UNUSED_THEN_USED',
            'label' => 'Used term',
        ]);
        EvidenceRecord::factory()->intervention($term)->create([
            'pupil_id' => $pupil->id,
            'author_id' => $teacher->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/provision-terms/'.$term->id);

        $response->assertConflict()
            ->assertJson([
                'message' => 'This Provision term is in use and cannot be deleted. Deactivate it instead.',
                'code' => ProvisionTermController::IN_USE_CODE,
            ]);

        $this->assertDatabaseHas('provision_terms', ['id' => $term->id]);
        $this->assertSame($school->tenant_id, $tenant->id);
    }

    public function test_delete_returns_409_when_the_term_is_used_in_a_relationship_mapping(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ProvisionTerm::factory()->create([
            'code' => 'MAPPED_PROVISION',
            'label' => 'Mapped provision',
        ]);
        RelationshipMapping::factory()->forVersion($term->ontologyVersion)->create([
            'to_term_id' => $term->id,
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/provision-terms/'.$term->id);

        $response->assertConflict()
            ->assertJsonPath('code', ProvisionTermController::IN_USE_CODE);

        $this->assertDatabaseHas('provision_terms', ['id' => $term->id]);
    }

    public function test_tenant_admin_deletes_an_unused_provision_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ProvisionTerm::factory()->create([
            'code' => 'CUSTOM_DELETE',
            'label' => 'Custom delete',
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/provision-terms/'.$term->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('provision_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::ProvisionTermDeleted->value,
            'resource_id' => $term->id,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantAndAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        return [$tenant, $admin];
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
}
