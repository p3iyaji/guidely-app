<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantType;
use App\Http\Controllers\Api\V1\PilotTenantController;
use App\Http\Requests\Api\V1\StorePilotTenantRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PilotTenantBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_creates_sample_pilot_tenant_with_audit_and_flags(): void
    {
        $operator = User::factory()->platformOperator()->create();

        $response = $this->actingAs($operator)->postJson('/api/v1/pilot/tenants', [
            'name' => 'Pilot School Alpha',
            'type' => TenantType::School->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Pilot School Alpha')
            ->assertJsonPath('data.type', TenantType::School->value)
            ->assertJsonPath('data.cohort_enabled', true)
            ->assertJsonPath('data.cohort_label', PilotTenantController::PILOT_COHORT_LABEL)
            ->assertJsonPath('data.feature_flags.trust_dashboard', false)
            ->assertJsonPath('data.feature_flags.connectors', false)
            ->assertJsonPath('data.feature_flags.advanced_documentation_packs', false)
            ->assertJsonPath('data.feature_flags.review_cycle_automation', false)
            ->assertJsonPath('data.feature_flags.portfolio_benchmarking', false)
            ->assertJsonPath('data.feature_flags.compliance_alerts', false);

        $tenantId = $response->json('data.id');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'cohort_enabled' => true,
            'cohort_label' => PilotTenantController::PILOT_COHORT_LABEL,
        ]);

        foreach (FeatureFlagKey::cases() as $key) {
            $this->assertDatabaseHas('tenant_feature_flags', [
                'tenant_id' => $tenantId,
                'key' => $key->value,
                'enabled' => false,
            ]);
        }

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::TenantCreated->value,
            'tenant_id' => $tenantId,
            'user_id' => $operator->id,
            'resource_type' => 'tenant',
            'resource_id' => $tenantId,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::TenantCreated->value)
            ->where('resource_id', $tenantId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(StorePilotTenantRequest::COHORT_MODE_SAMPLE, $audit->metadata['cohort_mode'] ?? null);
        $this->assertSame(TenantType::School->value, $audit->metadata['type'] ?? null);
    }

    public function test_platform_operator_creates_empty_cohort_pilot_tenant(): void
    {
        $operator = User::factory()->platformOperator()->create();

        $response = $this->actingAs($operator)->postJson('/api/v1/pilot/tenants', [
            'name' => 'Pilot Trust Beta',
            'type' => TenantType::Trust->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_EMPTY,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', TenantType::Trust->value)
            ->assertJsonPath('data.cohort_enabled', false)
            ->assertJsonPath('data.cohort_label', null);

        $this->assertDatabaseHas('tenants', [
            'id' => $response->json('data.id'),
            'type' => TenantType::Trust->value,
            'cohort_enabled' => false,
            'cohort_label' => null,
        ]);
    }

    public function test_deactivated_platform_operator_cannot_create_pilot_tenant(): void
    {
        $operator = User::factory()->platformOperator()->deactivated()->create();

        $this->actingAs($operator)->postJson('/api/v1/pilot/tenants', [
            'name' => 'Should Fail',
            'type' => TenantType::School->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
        ])->assertUnauthorized();
    }

    public function test_non_operator_cannot_create_pilot_tenant(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/pilot/tenants', [
            'name' => 'Should Fail',
            'type' => TenantType::School->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_EMPTY,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    public function test_guest_cannot_create_pilot_tenant(): void
    {
        $this->postJson('/api/v1/pilot/tenants', [
            'name' => 'Should Fail',
            'type' => TenantType::School->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_EMPTY,
        ])->assertUnauthorized();
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_pilot_tenant_payload_returns_422(array $payload): void
    {
        $operator = User::factory()->platformOperator()->create();

        $this->actingAs($operator)
            ->postJson('/api/v1/pilot/tenants', $payload)
            ->assertUnprocessable();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [[
                'type' => TenantType::School->value,
                'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
            ]],
            'whitespace name' => [[
                'name' => '   ',
                'type' => TenantType::School->value,
                'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
            ]],
            'invalid type' => [[
                'name' => 'X',
                'type' => 'academy',
                'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
            ]],
            'invalid cohort mode' => [[
                'name' => 'X',
                'type' => TenantType::School->value,
                'cohort_mode' => 'full',
            ]],
        ];
    }

    public function test_teacher_cannot_create_pilot_tenant(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $teacher = User::factory()->forTenant($tenant)->create([
            'role' => Role::Teacher,
        ]);

        $this->actingAs($teacher)->postJson('/api/v1/pilot/tenants', [
            'name' => 'Should Fail',
            'type' => TenantType::School->value,
            'cohort_mode' => StorePilotTenantRequest::COHORT_MODE_SAMPLE,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }
}
