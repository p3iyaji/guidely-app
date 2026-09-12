<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\ThresholdTerm;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\ThresholdTermController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThresholdTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/threshold-terms')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_threshold_terms(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        ThresholdTerm::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);
        ThresholdTerm::factory()->inactive()->create(['code' => 'WATCH', 'label' => 'Watch list']);

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/threshold-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('HIGH_RISK', $codes);
        $this->assertContains('WATCH', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'WATCH')['is_active']);
    }

    public function test_teacher_index_omits_inactive_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        ThresholdTerm::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);
        ThresholdTerm::factory()->inactive()->create(['code' => 'WATCH', 'label' => 'Watch list']);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/threshold-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('HIGH_RISK', $codes);
        $this->assertNotContains('WATCH', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/threshold-terms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/threshold-terms', [
            'code' => 'HIGH_RISK',
            'label' => 'High risk',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('threshold_terms', ['code' => 'HIGH_RISK']);
    }

    public function test_tenant_admin_creates_a_threshold_term_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = ThresholdTerm::factory()->create(['code' => 'SEED'])->ontology_version_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/threshold-terms', [
            'code' => '  high_risk  ',
            'label' => '  High risk  ',
            'sort_order' => 12,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'HIGH_RISK')
            ->assertJsonPath('data.label', 'High risk')
            ->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ontology_version_id', $versionId);

        $term = ThresholdTerm::query()->where('code', 'HIGH_RISK')->first();
        $this->assertNotNull($term);
        $this->assertSame($versionId, $term->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::ThresholdTermCreated->value,
            'resource_type' => 'threshold_term',
            'resource_id' => $term->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        ThresholdTerm::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/threshold-terms', [
            'code' => 'high_risk',
            'label' => 'Another high risk',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'A Threshold term with this code already exists on this Ontology version.',
            );
    }

    public function test_tenant_admin_updates_a_threshold_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ThresholdTerm::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/threshold-terms/'.$term->id, [
            'label' => 'High risk (updated)',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'High risk (updated)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('threshold_terms', [
            'id' => $term->id,
            'label' => 'High risk (updated)',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::ThresholdTermUpdated->value)
                ->where('resource_id', $term->id)
                ->count(),
        );
    }

    public function test_cross_version_threshold_term_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-threshold-crud',
        ]);
        $foreign = ThresholdTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret threshold',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/threshold-terms/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret threshold', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('threshold_terms', [
            'id' => $foreign->id,
            'label' => 'Secret threshold',
        ]);
    }

    public function test_delete_returns_409_when_the_term_is_used_by_a_relationship_mapping(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ThresholdTerm::factory()->create(['code' => 'MAPPING_USED', 'label' => 'Mapping used']);

        RelationshipMapping::factory()
            ->forVersion($term->ontologyVersion)
            ->create([
                'from_domain' => 'threshold',
                'from_term_id' => $term->id,
                'to_domain' => 'provision',
                'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
            ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/threshold-terms/'.$term->id);

        $response->assertConflict()
            ->assertJson([
                'message' => 'This Threshold term is in use and cannot be deleted. Deactivate it instead.',
                'code' => ThresholdTermController::IN_USE_CODE,
            ]);

        $this->assertDatabaseHas('threshold_terms', ['id' => $term->id]);
    }

    public function test_tenant_admin_deletes_an_unused_threshold_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = ThresholdTerm::factory()->create(['code' => 'CUSTOM_DELETE', 'label' => 'Custom delete']);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/threshold-terms/'.$term->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('threshold_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::ThresholdTermDeleted->value,
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
