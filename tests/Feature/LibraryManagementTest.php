<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\Role;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\RuleLibraryVersionStatus;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantType;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_library_management_endpoints(): void
    {
        $tenant = Tenant::factory()->school()->create();

        $this->getJson('/api/v1/operator/library-management')->assertUnauthorized();
        $this->patchJson("/api/v1/operator/library-management/{$tenant->id}", [])->assertUnauthorized();
    }

    #[DataProvider('tenantRoles')]
    public function test_tenant_roles_cannot_access_library_management_endpoints(Role $role): void
    {
        $tenant = Tenant::factory()->school()->create();
        $user = User::factory()->forTenant($tenant)->create(['role' => $role]);

        $this->actingAs($user)
            ->getJson('/api/v1/operator/library-management')
            ->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
        $this->actingAs($user)
            ->patchJson("/api/v1/operator/library-management/{$tenant->id}", [])
            ->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function tenantRoles(): array
    {
        return collect(Role::cases())
            ->reject(fn (Role $role): bool => $role === Role::PlatformOperator)
            ->mapWithKeys(fn (Role $role): array => [$role->value => [$role]])
            ->all();
    }

    public function test_platform_operator_with_a_tenant_cannot_manage_libraries(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $operator = User::factory()->forTenant($tenant)->create([
            'role' => Role::PlatformOperator,
        ]);

        $this->actingAs($operator)
            ->getJson('/api/v1/operator/library-management')
            ->assertForbidden();
    }

    public function test_deactivated_platform_operator_cannot_manage_libraries(): void
    {
        $operator = User::factory()->platformOperator()->deactivated()->create();

        $this->actingAs($operator)
            ->getJson('/api/v1/operator/library-management')
            ->assertUnauthorized();
    }

    public function test_get_lists_tenants_and_versions_in_deterministic_order(): void
    {
        $operator = User::factory()->platformOperator()->create();
        $ontologyZulu = OntologyVersion::factory()->published()->create([
            'code' => 'ontology-zulu',
            'label' => 'Zulu Ontology',
        ]);
        $ontologyAlpha = OntologyVersion::factory()->create([
            'code' => 'ontology-alpha',
            'label' => 'Alpha Ontology',
        ]);
        $ruleZulu = RuleLibraryVersion::factory()->published()->create([
            'code' => 'rules-zulu',
            'label' => 'Zulu Rules',
        ]);
        $ruleAlpha = RuleLibraryVersion::factory()->create([
            'code' => 'rules-alpha',
            'label' => 'Alpha Rules',
        ]);
        $tenantZulu = Tenant::factory()->trust()->create(['name' => 'Zulu Trust']);
        $tenantZulu->forceFill([
            'current_ontology_version_id' => $ontologyZulu->id,
            'current_rule_library_version_id' => $ruleZulu->id,
        ])->save();
        $tenantAlpha = Tenant::factory()->school()->create(['name' => 'Alpha School']);

        $response = $this->actingAs($operator)
            ->getJson('/api/v1/operator/library-management');

        $response->assertOk()
            ->assertJsonPath('data.tenants.0.id', $tenantAlpha->id)
            ->assertJsonPath('data.tenants.1.id', $tenantZulu->id)
            ->assertJsonPath('data.tenants.1.type', TenantType::Trust->value)
            ->assertJsonPath('data.tenants.1.current_ontology_version.id', $ontologyZulu->id)
            ->assertJsonPath('data.tenants.1.current_rule_library_version.id', $ruleZulu->id)
            ->assertJsonFragment([
                'id' => $ontologyAlpha->id,
                'code' => 'ontology-alpha',
                'label' => 'Alpha Ontology',
                'status' => OntologyVersionStatus::Draft->value,
                'published_at' => null,
            ])
            ->assertJsonFragment([
                'id' => $ruleAlpha->id,
                'code' => 'rules-alpha',
                'label' => 'Alpha Rules',
                'status' => RuleLibraryVersionStatus::Draft->value,
                'published_at' => null,
            ]);

        $ontologyCodes = collect($response->json('data.ontology_versions'))->pluck('code')->all();
        $ruleLibraryCodes = collect($response->json('data.rule_library_versions'))->pluck('code')->all();

        $this->assertSame(collect($ontologyCodes)->sort()->values()->all(), $ontologyCodes);
        $this->assertSame(collect($ruleLibraryCodes)->sort()->values()->all(), $ruleLibraryCodes);
    }

    public function test_operator_publishes_and_pins_both_libraries_with_audit_and_reevaluation(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->freezeTime();

        $operator = User::factory()->platformOperator()->create();
        $priorOntology = OntologyVersion::factory()->published()->create([
            'code' => 'ontology-prior',
        ]);
        $priorRules = RuleLibraryVersion::factory()->published()->create([
            'code' => 'rules-prior',
        ]);
        $nextOntology = OntologyVersion::factory()->create([
            'code' => 'ontology-next',
        ]);
        $nextRules = RuleLibraryVersion::factory()->create([
            'code' => 'rules-next',
        ]);
        $tenant = Tenant::factory()->school()->create();
        $tenant->forceFill([
            'current_ontology_version_id' => $priorOntology->id,
            'current_rule_library_version_id' => $priorRules->id,
        ])->save();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $response = $this->actingAs($operator)
            ->patchJson("/api/v1/operator/library-management/{$tenant->id}", [
                'ontology_version_id' => $nextOntology->id,
                'rule_library_version_id' => $nextRules->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tenant.current_ontology_version.id', $nextOntology->id)
            ->assertJsonPath('data.tenant.current_rule_library_version.id', $nextRules->id)
            ->assertJsonPath('data.result.ontology_published', true)
            ->assertJsonPath('data.result.rule_library_published', true)
            ->assertJsonPath('data.result.ontology_pin_changed', true)
            ->assertJsonPath('data.result.rule_library_pin_changed', true)
            ->assertJsonPath('data.result.queued_count', 1);

        $this->assertSame(OntologyVersionStatus::Published, $nextOntology->fresh()->status);
        $this->assertSame(RuleLibraryVersionStatus::Published, $nextRules->fresh()->status);
        $this->assertSame($nextOntology->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($nextRules->id, $tenant->fresh()->current_rule_library_version_id);
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->fresh()->documentation_status);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'library_published';
        });

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::LibraryPublished->value)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($operator->id, $audit->user_id);
        $this->assertSame('operator-ui', $audit->metadata['source'] ?? null);
        $this->assertSame($priorOntology->id, $audit->metadata['prior_ontology_version_id'] ?? null);
        $this->assertSame($nextOntology->id, $audit->metadata['new_ontology_version_id'] ?? null);
        $this->assertSame($priorRules->id, $audit->metadata['prior_rule_library_version_id'] ?? null);
        $this->assertSame($nextRules->id, $audit->metadata['new_rule_library_version_id'] ?? null);
        $this->assertSame(true, $audit->metadata['ontology_published'] ?? null);
        $this->assertSame(true, $audit->metadata['rule_library_published'] ?? null);
        $this->assertSame(true, $audit->metadata['ontology_pin_changed'] ?? null);
        $this->assertSame(true, $audit->metadata['rule_library_pin_changed'] ?? null);
        $this->assertSame(1, $audit->metadata['queued_count'] ?? null);
    }

    public function test_operator_can_publish_only_one_library(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $operator = User::factory()->platformOperator()->create();
        $tenant = Tenant::factory()->school()->create();
        $ruleLibrary = RuleLibraryVersion::factory()->create();

        $this->actingAs($operator)
            ->patchJson("/api/v1/operator/library-management/{$tenant->id}", [
                'rule_library_version_id' => $ruleLibrary->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.tenant.current_ontology_version', null)
            ->assertJsonPath('data.tenant.current_rule_library_version.id', $ruleLibrary->id)
            ->assertJsonPath('data.result.rule_library_published', true);
    }

    public function test_update_requires_at_least_one_library_selection(): void
    {
        $operator = User::factory()->platformOperator()->create();
        $tenant = Tenant::factory()->school()->create();

        $this->actingAs($operator)
            ->patchJson("/api/v1/operator/library-management/{$tenant->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ontology_version_id',
                'rule_library_version_id',
            ]);
    }

    public function test_update_rejects_unknown_library_versions_without_changes(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $operator = User::factory()->platformOperator()->create();
        $tenant = Tenant::factory()->school()->create();

        $this->actingAs($operator)
            ->patchJson("/api/v1/operator/library-management/{$tenant->id}", [
                'ontology_version_id' => '01H00000000000000000000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ontology_version_id']);

        $this->assertNull($tenant->fresh()->current_ontology_version_id);
        Bus::assertNothingDispatched();
        $this->assertSame(
            0,
            AuditEvent::query()->where('event_type', AuditEventType::LibraryPublished->value)->count(),
        );
    }
}
