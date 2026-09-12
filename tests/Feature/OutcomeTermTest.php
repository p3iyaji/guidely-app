<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\OutcomeTermController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutcomeTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/outcome-terms')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_outcome_terms(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        OutcomeTerm::factory()->create(['code' => 'INDEPENDENT', 'label' => 'Independent']);
        OutcomeTerm::factory()->inactive()->create(['code' => 'CONFIDENT', 'label' => 'Confident']);

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/outcome-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('INDEPENDENT', $codes);
        $this->assertContains('CONFIDENT', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'CONFIDENT')['is_active']);
    }

    public function test_teacher_index_omits_inactive_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        OutcomeTerm::factory()->create(['code' => 'INDEPENDENT', 'label' => 'Independent']);
        OutcomeTerm::factory()->inactive()->create(['code' => 'CONFIDENT', 'label' => 'Confident']);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/outcome-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('INDEPENDENT', $codes);
        $this->assertNotContains('CONFIDENT', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/outcome-terms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/outcome-terms', [
            'code' => 'RESILIENT',
            'label' => 'Resilient',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('outcome_terms', ['code' => 'RESILIENT']);
    }

    public function test_tenant_admin_creates_an_outcome_term_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = OutcomeTerm::factory()->create(['code' => 'SEED'])->ontology_version_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/outcome-terms', [
            'code' => '  resilient  ',
            'label' => '  Resilient  ',
            'sort_order' => 12,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'RESILIENT')
            ->assertJsonPath('data.label', 'Resilient')
            ->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ontology_version_id', $versionId);

        $term = OutcomeTerm::query()->where('code', 'RESILIENT')->first();
        $this->assertNotNull($term);
        $this->assertSame($versionId, $term->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::OutcomeTermCreated->value,
            'resource_type' => 'outcome_term',
            'resource_id' => $term->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        OutcomeTerm::factory()->create(['code' => 'INDEPENDENT', 'label' => 'Independent']);

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/outcome-terms', [
            'code' => 'independent',
            'label' => 'Another independent',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'An Outcome term with this code already exists on this Ontology version.',
            );
    }

    public function test_tenant_admin_updates_an_outcome_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = OutcomeTerm::factory()->create(['code' => 'INDEPENDENT', 'label' => 'Independent']);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/outcome-terms/'.$term->id, [
            'label' => 'Independent (updated)',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Independent (updated)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('outcome_terms', [
            'id' => $term->id,
            'label' => 'Independent (updated)',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::OutcomeTermUpdated->value)
                ->where('resource_id', $term->id)
                ->count(),
        );
    }

    public function test_cross_version_outcome_term_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-outcome-crud',
        ]);
        $foreign = OutcomeTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret outcome',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/outcome-terms/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret outcome', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('outcome_terms', [
            'id' => $foreign->id,
            'label' => 'Secret outcome',
        ]);
    }

    public function test_delete_returns_409_when_the_term_is_used_by_a_relationship_mapping(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = OutcomeTerm::factory()->create(['code' => 'MAPPING_USED', 'label' => 'Mapping used']);

        RelationshipMapping::factory()
            ->forVersion($term->ontologyVersion)
            ->create([
                'from_domain' => 'outcome',
                'from_term_id' => $term->id,
                'to_domain' => 'provision',
                'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
            ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/outcome-terms/'.$term->id);

        $response->assertConflict()
            ->assertJson([
                'message' => 'This Outcome term is in use and cannot be deleted. Deactivate it instead.',
                'code' => OutcomeTermController::IN_USE_CODE,
            ]);

        $this->assertDatabaseHas('outcome_terms', ['id' => $term->id]);
    }

    public function test_tenant_admin_deletes_an_unused_outcome_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = OutcomeTerm::factory()->create(['code' => 'CUSTOM_DELETE', 'label' => 'Custom delete']);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/outcome-terms/'.$term->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('outcome_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::OutcomeTermDeleted->value,
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
}
