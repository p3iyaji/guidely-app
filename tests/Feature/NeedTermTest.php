<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\NeedTermController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeedTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/need-terms')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_need_terms(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        NeedTerm::factory()->create(['code' => 'LEARNING', 'label' => 'Learning needs']);
        NeedTerm::factory()->inactive()->create(['code' => 'SOCIAL_EMOTIONAL', 'label' => 'Social & emotional']);

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/need-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('LEARNING', $codes);
        $this->assertContains('SOCIAL_EMOTIONAL', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'SOCIAL_EMOTIONAL')['is_active']);
    }

    public function test_teacher_index_omits_inactive_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        NeedTerm::factory()->create(['code' => 'LEARNING', 'label' => 'Learning needs']);
        NeedTerm::factory()->inactive()->create(['code' => 'SOCIAL_EMOTIONAL', 'label' => 'Social & emotional']);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/need-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('LEARNING', $codes);
        $this->assertNotContains('SOCIAL_EMOTIONAL', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/need-terms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/need-terms', [
            'code' => 'MENTORING',
            'label' => 'Peer mentoring',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('need_terms', ['code' => 'MENTORING']);
    }

    public function test_tenant_admin_creates_a_need_term_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = NeedTerm::factory()->create(['code' => 'SEED'])->ontology_version_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/need-terms', [
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

        $term = NeedTerm::query()->where('code', 'MENTORING')->first();
        $this->assertNotNull($term);
        $this->assertSame($versionId, $term->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::NeedTermCreated->value,
            'resource_type' => 'need_term',
            'resource_id' => $term->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        NeedTerm::factory()->create(['code' => 'LEARNING', 'label' => 'Learning needs']);

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/need-terms', [
            'code' => 'learning',
            'label' => 'Another learning',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'A Need term with this code already exists on this Ontology version.',
            );
    }

    public function test_tenant_admin_updates_a_need_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = NeedTerm::factory()->create(['code' => 'LEARNING', 'label' => 'Learning needs']);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/need-terms/'.$term->id, [
            'label' => 'Learning needs (updated)',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Learning needs (updated)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('need_terms', [
            'id' => $term->id,
            'label' => 'Learning needs (updated)',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::NeedTermUpdated->value)
                ->where('resource_id', $term->id)
                ->count(),
        );
    }

    public function test_cross_version_need_term_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-need-crud',
        ]);
        $foreign = NeedTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret need',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/need-terms/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret need', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('need_terms', [
            'id' => $foreign->id,
            'label' => 'Secret need',
        ]);
    }

    public function test_delete_returns_409_when_the_term_is_used_by_a_pupil_primary_need(): void
    {
        [, $school, $admin] = $this->tenantSchoolAndAdmin();
        $term = NeedTerm::factory()->create(['code' => 'PRIMARY_USED', 'label' => 'Primary used']);
        Pupil::factory()->forSchool($school)->create(['primary_need_term_id' => $term->id]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/need-terms/'.$term->id);

        $response->assertConflict()
            ->assertJson([
                'message' => 'This Need term is in use and cannot be deleted. Deactivate it instead.',
                'code' => NeedTermController::IN_USE_CODE,
            ]);

        $this->assertDatabaseHas('need_terms', ['id' => $term->id]);
    }

    public function test_delete_returns_409_when_the_term_is_used_by_a_pupil_secondary_need(): void
    {
        [, $school, $admin] = $this->tenantSchoolAndAdmin();
        $term = NeedTerm::factory()->create(['code' => 'SECONDARY_USED', 'label' => 'Secondary used']);
        Pupil::factory()->forSchool($school)->create(['secondary_need_term_id' => $term->id]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/need-terms/'.$term->id);

        $response->assertConflict()
            ->assertJsonPath('code', NeedTermController::IN_USE_CODE);

        $this->assertDatabaseHas('need_terms', ['id' => $term->id]);
    }

    public function test_tenant_admin_deletes_an_unused_need_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = NeedTerm::factory()->create(['code' => 'CUSTOM_DELETE', 'label' => 'Custom delete']);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/need-terms/'.$term->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('need_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::NeedTermDeleted->value,
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
    private function tenantSchoolAndAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        return [$tenant, $school, $admin];
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
