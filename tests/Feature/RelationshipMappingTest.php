<?php

namespace Tests\Feature;

use App\Domains\Audit\AuditEvent;
use App\Domains\Audit\AuditEventType;
use App\Domains\Identity\AccessMessages;
use App\Domains\Ontology\OntologyVersion;
use App\Domains\Ontology\RelationshipMapping;
use App\Domains\Ontology\ThresholdTerm;
use App\Domains\Tenancy\School;
use App\Domains\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\RelationshipMappingController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/relationship-mappings')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_relationship_mappings(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);
        RelationshipMapping::factory()->inactive()->create(['code' => 'WATCH', 'label' => 'Watch list']);

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/relationship-mappings');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('HIGH_RISK', $codes);
        $this->assertContains('WATCH', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'WATCH')['is_active']);
    }

    public function test_teacher_index_omits_inactive_mappings(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);
        RelationshipMapping::factory()->inactive()->create(['code' => 'WATCH', 'label' => 'Watch list']);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/relationship-mappings');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('HIGH_RISK', $codes);
        $this->assertNotContains('WATCH', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label', 'relationship_type', 'from_domain', 'from_term_id', 'to_domain', 'to_term_id']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'HIGH_RISK',
            'label' => 'High risk',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('relationship_mappings', ['code' => 'HIGH_RISK']);
    }

    public function test_tenant_admin_creates_a_relationship_mapping_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = RelationshipMapping::factory()->create(['code' => 'SEED'])->ontology_version_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => '  high_risk  ',
            'label' => '  High risk  ',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'sort_order' => 12,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'HIGH_RISK')
            ->assertJsonPath('data.label', 'High risk')
            ->assertJsonPath('data.relationship_type', 'need_to_provision')
            ->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ontology_version_id', $versionId);

        $mapping = RelationshipMapping::query()->where('code', 'HIGH_RISK')->first();
        $this->assertNotNull($mapping);
        $this->assertSame($versionId, $mapping->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::RelationshipMappingCreated->value,
            'resource_type' => 'relationship_mapping',
            'resource_id' => $mapping->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        RelationshipMapping::factory()->create(['code' => 'HIGH_RISK', 'label' => 'High risk']);

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'high_risk',
            'label' => 'Another high risk',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'A Relationship mapping with this code already exists on this Ontology version.',
            );
    }

    public function test_invalid_code_regex_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'invalid code',
            'label' => 'Invalid code test',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'The code may contain only letters, numbers, hyphens, and underscores.',
            );
    }

    public function test_invalid_relationship_type_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'VALID_CODE',
            'label' => 'Valid code test',
            'relationship_type' => 'NeedToProvision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['relationship_type'])
            ->assertJsonPath(
                'errors.relationship_type.0',
                'The relationship type must be lowercase snake_case (for example need_to_provision).',
            );
    }

    public function test_invalid_from_domain_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'VALID_CODE',
            'label' => 'Valid code test',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'invalid_domain',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from_domain']);
    }

    public function test_invalid_to_domain_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'VALID_CODE',
            'label' => 'Valid code test',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'invalid_domain',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_domain']);
    }

    public function test_nonexistent_from_term_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'VALID_CODE',
            'label' => 'Valid code test',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from_term_id']);
    }

    public function test_nonexistent_to_term_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/relationship-mappings', [
            'code' => 'VALID_CODE',
            'label' => 'Valid code test',
            'relationship_type' => 'need_to_provision',
            'from_domain' => 'need',
            'from_term_id' => (string) \Illuminate\Support\Str::ulid(),
            'to_domain' => 'provision',
            'to_term_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_term_id']);
    }

    public function test_tenant_admin_updates_a_relationship_mapping(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $mapping = RelationshipMapping::factory()->create([
            'code' => 'HIGH_RISK', 
            'label' => 'High risk',
            'relationship_type' => 'need_to_provision'
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/relationship-mappings/'.$mapping->id, [
            'label' => 'High risk (updated)',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'High risk (updated)')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('relationship_mappings', [
            'id' => $mapping->id,
            'label' => 'High risk (updated)',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::RelationshipMappingUpdated->value)
                ->where('resource_id', $mapping->id)
                ->count(),
        );
    }

    public function test_cross_version_relationship_mapping_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-relationship-crud',
        ]);
        $foreign = RelationshipMapping::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret mapping',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/relationship-mappings/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret mapping', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('relationship_mappings', [
            'id' => $foreign->id,
            'label' => 'Secret mapping',
        ]);
    }

    public function test_tenant_admin_deletes_a_relationship_mapping(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $mapping = RelationshipMapping::factory()->create([
            'code' => 'CUSTOM_DELETE', 
            'label' => 'Custom delete'
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/relationship-mappings/'.$mapping->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('relationship_mappings', ['id' => $mapping->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::RelationshipMappingDeleted->value,
            'resource_id' => $mapping->id,
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