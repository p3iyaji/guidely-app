<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Api\V1\SettingTermController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ontology/setting-terms')->assertUnauthorized();
    }

    public function test_tenant_admin_lists_active_and_inactive_setting_terms(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        SettingTerm::factory()->create(['code' => 'CLASSROOM', 'label' => 'Classroom']);
        SettingTerm::factory()->inactive()->create(['code' => 'PLAYGROUND', 'label' => 'Playground']);

        $response = $this->actingAs($admin)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('CLASSROOM', $codes);
        $this->assertContains('PLAYGROUND', $codes);
        $this->assertFalse(collect($response->json('data'))->firstWhere('code', 'PLAYGROUND')['is_active']);
    }

    public function test_teacher_index_omits_inactive_terms(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        SettingTerm::factory()->create(['code' => 'CLASSROOM', 'label' => 'Classroom']);
        SettingTerm::factory()->inactive()->create(['code' => 'PLAYGROUND', 'label' => 'Playground']);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/setting-terms');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('CLASSROOM', $codes);
        $this->assertNotContains('PLAYGROUND', $codes);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        [, $admin] = $this->tenantAndAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/setting-terms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_teacher_store_returns_403(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();

        $response = $this->actingAs($teacher)->postJson('/api/v1/ontology/setting-terms', [
            'code' => 'CORRIDOR',
            'label' => 'Corridor',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);

        $this->assertDatabaseMissing('setting_terms', ['code' => 'CORRIDOR']);
    }

    public function test_tenant_admin_creates_a_setting_term_on_the_effective_version(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $versionId = SettingTerm::factory()->create(['code' => 'SEED'])->ontology_version_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/setting-terms', [
            'code' => '  corridor  ',
            'label' => '  Corridor  ',
            'sort_order' => 12,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CORRIDOR')
            ->assertJsonPath('data.label', 'Corridor')
            ->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ontology_version_id', $versionId);

        $term = SettingTerm::query()->where('code', 'CORRIDOR')->first();
        $this->assertNotNull($term);
        $this->assertSame($versionId, $term->ontology_version_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SettingTermCreated->value,
            'resource_type' => 'setting_term',
            'resource_id' => $term->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_on_the_same_version_returns_422(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        SettingTerm::factory()->create(['code' => 'CLASSROOM', 'label' => 'Classroom']);

        $response = $this->actingAs($admin)->postJson('/api/v1/ontology/setting-terms', [
            'code' => 'classroom',
            'label' => 'Another classroom',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath(
                'errors.code.0',
                'A Setting term with this code already exists on this Ontology version.',
            );
    }

    public function test_tenant_admin_updates_a_setting_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = SettingTerm::factory()->create(['code' => 'CLASSROOM', 'label' => 'Classroom']);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/setting-terms/'.$term->id, [
            'label' => 'Classroom (updated)',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Classroom (updated)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('setting_terms', [
            'id' => $term->id,
            'label' => 'Classroom (updated)',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('event_type', AuditEventType::SettingTermUpdated->value)
                ->where('resource_id', $term->id)
                ->count(),
        );
    }

    public function test_cross_version_setting_term_returns_404_without_leakage(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $otherVersion = OntologyVersion::factory()->published()->create([
            'code' => 'other-published-setting-crud',
        ]);
        $foreign = SettingTerm::factory()->forVersion($otherVersion)->create([
            'code' => 'FOREIGN_CRUD',
            'label' => 'Secret setting',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/ontology/setting-terms/'.$foreign->id, [
            'label' => 'Hijacked',
        ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('Secret setting', $response->getContent());
        $this->assertStringNotContainsString('Hijacked', $response->getContent());

        $this->assertDatabaseHas('setting_terms', [
            'id' => $foreign->id,
            'label' => 'Secret setting',
        ]);
    }

    public function test_delete_returns_409_when_the_term_is_used_by_an_evidence_record(): void
    {
        [, $school, $admin] = $this->tenantSchoolAndAdmin();
        $term = SettingTerm::factory()->create(['code' => 'EVIDENCE_USED', 'label' => 'Evidence used']);
        $pupil = Pupil::factory()->forSchool($school)->create();
        EvidenceRecord::factory()->forPupil($pupil)->withSetting($term)->create();

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/setting-terms/'.$term->id);

        $response->assertConflict()
            ->assertJson([
                'message' => 'This Setting term is in use and cannot be deleted. Deactivate it instead.',
                'code' => SettingTermController::IN_USE_CODE,
            ]);

        $this->assertDatabaseHas('setting_terms', ['id' => $term->id]);
    }

    public function test_tenant_admin_deletes_an_unused_setting_term(): void
    {
        [, $admin] = $this->tenantAndAdmin();
        $term = SettingTerm::factory()->create(['code' => 'CUSTOM_DELETE', 'label' => 'Custom delete']);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/ontology/setting-terms/'.$term->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('setting_terms', ['id' => $term->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SettingTermDeleted->value,
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
