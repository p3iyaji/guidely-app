<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorType;
use App\Domain\Connectors\FilterConnectorPayload;
use App\Domain\Identity\AccessMessages;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectorConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_403_feature_not_available_for_get_and_put_when_flag_is_off(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create();

        $get = $this->actingAs($admin)->getJson('/api/v1/connectors');

        $get->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'connectors',
            ]);

        $put = $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, 'flag-off-secret'));

        $put->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'connectors',
            ]);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::ConnectorUpdated->value,
        ]);
    }

    public function test_tenant_admin_first_save_returns_has_secret_and_opt_in_fields(): void
    {
        [$tenant, $school, $admin] = $this->tenantWithConnectorsEnabled();
        $secret = 'pilot-connector-secret';

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, $secret));

        $response->assertOk()
            ->assertJsonPath('data.type', ConnectorType::PilotStub->value)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.has_secret', true)
            ->assertJsonPath('data.field_shares.0.school_id', $school->id)
            ->assertJsonPath('data.field_shares.0.fields.mis_key', true)
            ->assertJsonPath('data.field_shares.0.fields.given_name', false)
            ->assertJsonPath('data.field_shares.0.fields.family_name', false)
            ->assertJsonPath('data.field_shares.0.fields.date_of_birth', false)
            ->assertJsonPath('data.field_shares.0.fields.year_group', false)
            ->assertJsonPath('data.field_shares.0.fields.send_status', false)
            ->assertJsonMissing(['secret'])
            ->assertJsonMissing(['placeholder' => true]);

        $this->assertStringNotContainsString($secret, (string) $response->getContent());

        $rawSecret = DB::table('connectors')->where('tenant_id', $tenant->id)->value('secret');
        $this->assertIsString($rawSecret);
        $this->assertNotSame($secret, $rawSecret);
        $this->assertSame($secret, Crypt::decryptString($rawSecret));

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::ConnectorUpdated->value)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertTrue($audit->metadata['enabled'] ?? false);
        $this->assertSame(ConnectorType::PilotStub->value, $audit->metadata['type'] ?? null);
        $this->assertTrue($audit->metadata['has_secret'] ?? false);
        $this->assertTrue($audit->metadata[$school->id.'.mis_key'] ?? false);
        $this->assertArrayNotHasKey('secret', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
        $this->assertStringNotContainsString($secret, (string) json_encode($audit->metadata));
    }

    public function test_get_after_save_omits_secret_and_ciphertext_keys(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();
        $secret = 'stored-once-secret';

        $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, $secret))
            ->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/v1/connectors');

        $response->assertOk()
            ->assertJsonPath('data.has_secret', true)
            ->assertJsonPath('data.field_shares.0.fields.mis_key', true)
            ->assertJsonMissing(['secret'])
            ->assertJsonMissing(['ciphertext']);

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertStringNotContainsString($secret, (string) $response->getContent());
        $this->assertStringNotContainsString('ciphertext', (string) $response->getContent());
    }

    public function test_filter_omits_unshared_fields_and_keeps_opted_in_keys(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, 'filter-secret'))
            ->assertOk();

        $filtered = (new FilterConnectorPayload)->handle($school->id, [
            'mis_key' => 'MIS-100',
            'year_group' => '7',
            'given_name' => 'Ada',
        ]);

        $this->assertSame(['mis_key' => 'MIS-100'], $filtered);
        $this->assertArrayNotHasKey('year_group', $filtered);
        $this->assertArrayNotHasKey('given_name', $filtered);
    }

    public function test_filter_omits_all_fields_for_a_school_without_opt_in(): void
    {
        [$tenant, $schoolA, $admin] = $this->tenantWithConnectorsEnabled();
        $schoolB = School::factory()->forTenant($tenant)->create();

        $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($schoolA, 'filter-secret'))
            ->assertOk();

        $filtered = (new FilterConnectorPayload)->handle($schoolB->id, [
            'mis_key' => 'MIS-100',
            'year_group' => '7',
        ]);

        $this->assertSame([], $filtered);
    }

    public function test_filter_returns_empty_when_connector_is_disabled(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, 'filter-secret'))
            ->assertOk();

        $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => false,
        ])->assertOk();

        $filtered = (new FilterConnectorPayload)->handle($school->id, [
            'mis_key' => 'MIS-100',
        ]);

        $this->assertSame([], $filtered);
    }

    public function test_get_and_put_do_not_use_another_tenant_connector_row(): void
    {
        [, $schoolA, $adminA] = $this->tenantWithConnectorsEnabled();
        $tenantB = Tenant::factory()->create();
        $this->enableConnectors($tenantB);
        $schoolB = School::factory()->forTenant($tenantB)->create();

        Connector::factory()->forTenant($tenantB)->enabled()->withSecret('tenant-b-secret')->create([
            'field_shares' => [
                [
                    'school_id' => $schoolB->id,
                    'fields' => [
                        ConnectorField::MisKey->value => true,
                    ],
                ],
            ],
        ]);

        $get = $this->actingAs($adminA)->getJson('/api/v1/connectors');

        $get->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.has_secret', false)
            ->assertJsonPath('data.field_shares', [])
            ->assertJsonMissing(['tenant-b-secret']);

        $put = $this->actingAs($adminA)->putJson(
            '/api/v1/connectors',
            $this->validPayload($schoolA, 'tenant-a-secret'),
        );

        $put->assertOk()
            ->assertJsonPath('data.has_secret', true)
            ->assertJsonPath('data.field_shares.0.school_id', $schoolA->id);

        $this->assertSame(
            'tenant-b-secret',
            Crypt::decryptString((string) DB::table('connectors')->where('tenant_id', $tenantB->id)->value('secret')),
        );
    }

    public function test_duplicate_school_id_in_field_shares_returns_422(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => 'should-not-persist',
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'mis_key' => true,
                    ],
                ],
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'year_group' => true,
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['field_shares.0.school_id', 'field_shares.1.school_id']);

        $this->assertDatabaseCount('connectors', 0);
    }

    public function test_string_zero_field_share_is_stored_as_unshared(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => 'zero-secret',
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'mis_key' => '0',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.field_shares.0.fields.mis_key', false);
    }

    public function test_whitespace_only_secret_is_not_stored(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => '   ',
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'mis_key' => true,
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_secret', false);

        $this->assertNull(DB::table('connectors')->value('secret'));
    }

    public function test_senco_get_returns_403_forbidden_when_flag_is_on(): void
    {
        $tenant = Tenant::factory()->create();
        $this->enableConnectors($tenant);
        $senco = User::factory()->forTenant($tenant)->senco()->create();

        $this->actingAs($senco)->getJson('/api/v1/connectors')
            ->assertForbidden()
            ->assertJsonPath('message', AccessMessages::FORBIDDEN)
            ->assertJsonPath('code', 'forbidden');

        $this->actingAs($senco)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => 'senco-must-not-write',
        ])->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseCount('connectors', 0);
    }

    public function test_other_tenant_school_id_returns_422_and_does_not_write(): void
    {
        [, , $admin] = $this->tenantWithConnectorsEnabled();
        $foreignSchool = School::factory()->forTenant(Tenant::factory()->create())->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => 'should-not-persist',
            'field_shares' => [
                [
                    'school_id' => $foreignSchool->id,
                    'fields' => [
                        'mis_key' => true,
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['field_shares.0.school_id']);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::ConnectorUpdated->value,
        ]);
    }

    public function test_unknown_field_key_returns_422_and_does_not_write(): void
    {
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => true,
            'secret' => 'should-not-persist',
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'notes' => true,
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['field_shares.0.fields.notes']);

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::ConnectorUpdated->value,
        ]);
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

    public function test_guest_returns_401_for_get_and_put(): void
    {
        $this->getJson('/api/v1/connectors')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->putJson('/api/v1/connectors', [
            'enabled' => true,
        ])->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('connectors', 0);
    }

    public function test_omitting_secret_on_update_keeps_existing_ciphertext(): void
    {
        [$tenant, $school, $admin] = $this->tenantWithConnectorsEnabled();
        $secret = 'keep-this-secret';

        $this->actingAs($admin)->putJson('/api/v1/connectors', $this->validPayload($school, $secret))
            ->assertOk();

        $original = DB::table('connectors')->where('tenant_id', $tenant->id)->value('secret');

        $update = $this->actingAs($admin)->putJson('/api/v1/connectors', [
            'enabled' => false,
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        'mis_key' => true,
                    ],
                ],
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.has_secret', true)
            ->assertJsonMissing(['secret']);

        $this->assertSame($original, DB::table('connectors')->where('tenant_id', $tenant->id)->value('secret'));
        $this->assertSame($secret, Crypt::decryptString($original));
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
     * @return array{enabled: bool, secret: string, field_shares: list<array{school_id: string, fields: array<string, bool>}>}
     */
    private function validPayload(School $school, string $secret): array
    {
        return [
            'enabled' => true,
            'secret' => $secret,
            'field_shares' => [
                [
                    'school_id' => $school->id,
                    'fields' => [
                        ConnectorField::MisKey->value => true,
                    ],
                ],
            ],
        ];
    }
}
