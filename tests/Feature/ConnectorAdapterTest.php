<?php

namespace Tests\Feature;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorAdapter;
use App\Domain\Connectors\ConnectorAdapterRegistry;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorType;
use App\Domain\Connectors\PilotStubAdapter;
use App\Domain\Connectors\UnsupportedConnectorTypeException;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Jobs\ConnectorSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class ConnectorAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_upserts_from_adapter_pull_without_duplicating_pupils(): void
    {
        Http::preventStrayRequests();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();
        $adapter = new class implements ConnectorAdapter
        {
            public function type(): ConnectorType
            {
                return ConnectorType::PilotStub;
            }

            public function pull(Connector $connector, School $school, array $inboundPupils): array
            {
                return [[
                    'mis_key' => 'MIS-PULLED',
                    'given_name' => 'Adapter',
                    'family_name' => 'Pupil',
                    'year_group' => 'Year 9',
                ]];
            }
        };
        $this->app->instance(ConnectorAdapterRegistry::class, new ConnectorAdapterRegistry([$adapter]));

        $payload = [
            'school_id' => $school->id,
            'pupils' => [[
                'mis_key' => 'MIS-INBOUND',
                'given_name' => 'Inbound',
                'family_name' => 'Row',
            ]],
        ];

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();
        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();

        $this->assertSame(0, Pupil::query()->where('mis_key', 'MIS-INBOUND')->count());
        $this->assertSame(1, Pupil::query()->where('school_id', $school->id)->where('mis_key', 'MIS-PULLED')->count());
        $this->assertDatabaseHas('pupils', [
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'mis_key' => 'MIS-PULLED',
            'given_name' => 'Adapter',
            'family_name' => 'Pupil',
        ]);
        $this->assertNotSame('Year 9', Pupil::query()->where('mis_key', 'MIS-PULLED')->value('year_group'));
    }

    public function test_pilot_stub_sync_upserts_inbound_pupils_for_an_enabled_connector(): void
    {
        Http::preventStrayRequests();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $school->id,
            'pupils' => [[
                'mis_key' => 'MIS-100',
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
            ]],
        ])->assertAccepted();

        $this->assertInstanceOf(
            PilotStubAdapter::class,
            app(ConnectorAdapterRegistry::class)->for(ConnectorType::PilotStub),
        );
        $this->assertSame(1, Pupil::query()->where('school_id', $school->id)->where('mis_key', 'MIS-100')->count());
        $this->assertDatabaseHas('pupils', [
            'tenant_id' => $tenant->id,
            'mis_key' => 'MIS-100',
            'given_name' => 'Ada',
        ]);
    }

    public function test_second_school_syncs_through_the_same_adapter_class(): void
    {
        Http::preventStrayRequests();
        [$tenant, $schoolA, $admin] = $this->tenantWithEnabledConnector();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $fields = [
            ConnectorField::MisKey->value => true,
            ConnectorField::GivenName->value => true,
            ConnectorField::FamilyName->value => true,
        ];

        $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'field_shares' => [
                $this->share($schoolA, $fields),
                $this->share($schoolB, $fields),
            ],
        ])->assertOk()
            ->assertJsonPath('data.field_shares.1.school_id', $schoolB->id);

        $this->assertDatabaseCount('connectors', 1);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $schoolB->id,
            'pupils' => [[
                'mis_key' => 'MIS-100',
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
            ]],
        ])->assertAccepted();

        $adapter = app(ConnectorAdapterRegistry::class)->for(ConnectorType::PilotStub);
        $this->assertInstanceOf(PilotStubAdapter::class, $adapter);
        $this->assertSame(0, Pupil::query()->where('school_id', $schoolA->id)->count());
        $this->assertSame(1, Pupil::query()->where('school_id', $schoolB->id)->where('mis_key', 'MIS-100')->count());
        $this->assertDatabaseHas('pupils', [
            'tenant_id' => $tenant->id,
            'school_id' => $schoolB->id,
            'mis_key' => 'MIS-100',
            'given_name' => 'Ada',
        ]);
    }

    public function test_returns_422_and_does_not_write_when_type_is_not_a_registered_connector_type(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'type' => 'wonde',
            'enabled' => true,
            'secret' => 'must-not-persist',
            'field_shares' => [$this->share($school, [ConnectorField::MisKey->value => true])],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_returns_422_and_does_not_write_when_enum_type_has_no_adapter(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();
        $this->app->instance(ConnectorAdapterRegistry::class, new ConnectorAdapterRegistry([]));

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'type' => ConnectorType::PilotStub->value,
            'enabled' => true,
            'secret' => 'must-not-persist',
            'field_shares' => [$this->share($school, [ConnectorField::MisKey->value => true])],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->assertDatabaseCount('connectors', 0);
    }

    public function test_sync_returns_422_and_does_not_dispatch_when_type_has_no_adapter(): void
    {
        Http::preventStrayRequests();
        Queue::fake([ConnectorSync::class]);
        [, $school, $admin] = $this->tenantWithEnabledConnector();
        $this->app->instance(ConnectorAdapterRegistry::class, new ConnectorAdapterRegistry([]));

        $response = $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $school->id,
            'pupils' => [['mis_key' => 'MIS-100']],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_returns_403_feature_not_available_for_connector_http_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $payload = [
            'type' => ConnectorType::PilotStub->value,
            'enabled' => true,
            'secret' => 'must-not-persist',
            'field_shares' => [$this->share($school, [ConnectorField::MisKey->value => true])],
        ];
        $unavailable = [
            'message' => 'This feature is not available for this Tenant.',
            'code' => 'feature_not_available',
            'feature' => 'connectors',
        ];

        $this->actingAs($admin)->getJson('/api/v1/connectors')
            ->assertForbidden()
            ->assertExactJson($unavailable);

        $this->actingAs($admin)->putJson('/api/v1/connectors', $payload)
            ->assertForbidden()
            ->assertExactJson($unavailable);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $school->id,
            'pupils' => [['mis_key' => 'MIS-100']],
        ])->assertForbidden()
            ->assertExactJson($unavailable);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_senco_can_download_import_template_while_connectors_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();

        $this->assertDatabaseHas('tenant_feature_flags', [
            'tenant_id' => $tenant->id,
            'key' => FeatureFlagKey::Connectors->value,
            'enabled' => false,
        ]);

        $response = $this->actingAs($senco)->get('/api/v1/import/template');

        $response->assertOk()
            ->assertDownload('guidely-import-template.csv');

        $this->assertStringContainsString('pupil_identifier', $response->streamedContent());
    }

    public function test_senco_put_returns_403_forbidden_and_does_not_write(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableConnectors($tenant);
        $senco = User::factory()->forTenant($tenant)->senco()->create();

        $this->actingAs($senco)->putJson('/api/v1/connectors', [
            'type' => ConnectorType::PilotStub->value,
            'enabled' => true,
            'secret' => 'senco-must-not-write',
        ])->assertForbidden()
            ->assertJsonPath('message', AccessMessages::FORBIDDEN)
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseCount('connectors', 0);
    }

    public function test_guest_returns_401_for_connector_http(): void
    {
        $this->getJson('/api/v1/connectors')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->putJson('/api/v1/connectors', [
            'type' => ConnectorType::PilotStub->value,
            'enabled' => true,
        ])->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->postJson('/api/v1/connectors/sync', [
            'school_id' => '01INVALIDSCHOOLID0000000000',
            'pupils' => [['mis_key' => 'MIS-100']],
        ])->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_registry_throws_when_type_has_no_adapter(): void
    {
        $this->expectException(UnsupportedConnectorTypeException::class);

        (new ConnectorAdapterRegistry([]))->for(ConnectorType::PilotStub);
    }

    public function test_registry_throws_when_two_adapters_share_the_same_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConnectorAdapterRegistry([
            new PilotStubAdapter,
            new PilotStubAdapter,
        ]);
    }

    /**
     * @param  array<string, bool>  $fields
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantWithEnabledConnector(?array $fields = null): array
    {
        [$tenant, $school, $admin] = $this->tenantWithConnectorsEnabled();

        Connector::factory()->forTenant($tenant)->enabled()->create([
            'field_shares' => [$this->share($school, $fields ?? [
                ConnectorField::MisKey->value => true,
                ConnectorField::GivenName->value => true,
                ConnectorField::FamilyName->value => true,
            ])],
        ]);

        return [$tenant, $school, $admin];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantWithConnectorsEnabled(): array
    {
        $tenant = Tenant::factory()->create();
        $this->enableConnectors($tenant);
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        return [$tenant, $school, $admin];
    }

    private function enableConnectors(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::Connectors->value)
            ->update(['enabled' => true]);
    }

    /**
     * @param  array<string, bool>  $fields
     * @return array{school_id: string, fields: array<string, bool>}
     */
    private function share(School $school, array $fields): array
    {
        return [
            'school_id' => $school->id,
            'fields' => $fields,
        ];
    }
}
