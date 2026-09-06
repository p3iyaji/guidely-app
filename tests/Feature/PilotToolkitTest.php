<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotToolkitTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_download_import_template(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->get('/api/v1/pilot/import-template');

        $response->assertOk()
            ->assertDownload('guidely-import-template-placeholder.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('pupil_identifier', $content);
    }

    public function test_tenant_admin_can_read_disclaimer_pack(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/pilot/disclaimers')
            ->assertOk()
            ->assertJsonPath('data.title', 'Pilot disclaimer pack')
            ->assertJsonPath('data.items.0.id', 'data-minimisation')
            ->assertJsonFragment(['heading' => 'UK residency']);
    }

    public function test_tenant_admin_can_export_success_metrics_stub(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/pilot/success-metrics')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.metrics.0.key', 'time_to_prepare_review_cycle_hours')
            ->assertJsonPath('data.metrics.0.value', null);
    }

    public function test_teacher_cannot_access_pilot_toolkit_reads(): void
    {
        $tenant = Tenant::factory()->school()->create();
        $teacher = User::factory()->forTenant($tenant)->create([
            'role' => Role::Teacher,
        ]);

        foreach (['/api/v1/pilot/import-template', '/api/v1/pilot/disclaimers', '/api/v1/pilot/success-metrics'] as $path) {
            $this->actingAs($teacher)
                ->getJson($path)
                ->assertForbidden()
                ->assertExactJson([
                    'message' => AccessMessages::FORBIDDEN,
                    'code' => 'forbidden',
                ]);
        }
    }

    public function test_platform_operator_cannot_access_tenant_admin_toolkit_reads(): void
    {
        $operator = User::factory()->platformOperator()->create();

        $this->actingAs($operator);
        $this->assertNull(CurrentTenant::id());

        foreach (['/api/v1/pilot/import-template', '/api/v1/pilot/disclaimers', '/api/v1/pilot/success-metrics'] as $path) {
            $this->getJson($path)
                ->assertForbidden()
                ->assertExactJson([
                    'message' => AccessMessages::FORBIDDEN,
                    'code' => 'forbidden',
                ]);
        }
    }

    public function test_guest_cannot_access_pilot_toolkit_reads(): void
    {
        $this->getJson('/api/v1/pilot/disclaimers')->assertUnauthorized();
        $this->getJson('/api/v1/pilot/success-metrics')->assertUnauthorized();
        $this->getJson('/api/v1/pilot/import-template')->assertUnauthorized();
    }
}
