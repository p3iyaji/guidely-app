<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEventType;
use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorPupilUpserter;
use App\Domain\Connectors\FilterConnectorPayload;
use App\Domain\Connectors\Import\ImportedInterventionEvidenceUpserter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Jobs\ConnectorSync;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Database\Seeders\ProvisionOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ConnectorSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_403_feature_not_available_when_flag_is_off(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $school = School::factory()->forTenant($tenant)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $this->syncPayload($school));

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'connectors',
            ]);

        $this->assertDatabaseCount('pupils', 0);
        $this->assertDatabaseCount('evidence_records', 0);
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_same_payload_twice_upserts_one_pupil_and_one_connector_evidence(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $this->seed(ProvisionOntologySeeder::class);
        $this->freezeTime();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();
        $payload = $this->syncPayload($school, evidence: true);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();
        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();

        $this->assertSame(1, Pupil::query()->where('school_id', $school->id)->where('mis_key', 'MIS-100')->count());
        $this->assertSame(1, EvidenceRecord::query()->where('tenant_id', $tenant->id)->where('external_id', 'ext-sync-1')->count());

        $this->assertDatabaseHas('evidence_records', [
            'tenant_id' => $tenant->id,
            'author_id' => $admin->id,
            'source' => EvidenceSource::Connector->value,
            'lifecycle' => EvidenceLifecycle::Submitted->value,
            'type' => EvidenceType::Intervention->value,
            'external_id' => 'ext-sync-1',
        ]);

        Queue::assertPushed(SreReevaluatePupil::class, 1);
        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant): bool {
            $pupil = Pupil::query()->where('mis_key', 'MIS-100')->first();

            return $pupil !== null
                && $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'connector_sync';
        });
    }

    public function test_sync_updates_existing_import_pupil_with_same_school_and_mis_key_without_duplicating(): void
    {
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();
        $existing = Pupil::factory()->forSchool($school)->withMisKey('MIS-100')->create([
            'given_name' => 'Imported',
            'family_name' => 'Row',
            'year_group' => 'Year 7',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $school->id,
            'pupils' => [[
                'mis_key' => 'MIS-100',
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
                'year_group' => 'Year 8',
            ]],
        ])->assertAccepted();

        $this->assertSame(1, Pupil::query()->where('school_id', $school->id)->where('mis_key', 'MIS-100')->count());
        $existing->refresh();
        $this->assertSame($existing->id, Pupil::query()->where('mis_key', 'MIS-100')->value('id'));
        $this->assertSame('Ada', $existing->given_name);
        $this->assertSame($tenant->id, $existing->tenant_id);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilUpdated->value,
            'resource_id' => $existing->id,
        ]);
    }

    public function test_unshared_year_group_is_not_persisted_from_the_payload(): void
    {
        [, $school, $admin] = $this->tenantWithEnabledConnector([
            ConnectorField::MisKey->value => true,
            ConnectorField::GivenName->value => true,
            ConnectorField::FamilyName->value => true,
        ]);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', [
            'school_id' => $school->id,
            'pupils' => [[
                'mis_key' => 'MIS-100',
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
                'year_group' => 'Year 8',
            ]],
        ])->assertAccepted();

        $pupil = Pupil::query()->where('mis_key', 'MIS-100')->firstOrFail();
        $this->assertSame('Ada', $pupil->given_name);
        $this->assertNotSame('Year 8', $pupil->year_group);
    }

    public function test_disabled_connector_returns_422_and_does_not_write_or_enqueue_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        [$tenant, $school, $admin] = $this->tenantWithConnectorsEnabled();
        Connector::factory()->forTenant($tenant)->create([
            'enabled' => false,
            'field_shares' => [$this->share($school, [ConnectorField::MisKey->value => true])],
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $this->syncPayload($school));

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'connector_disabled');

        $this->assertDatabaseCount('pupils', 0);
        $this->assertDatabaseCount('evidence_records', 0);
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_identical_second_sync_does_not_dispatch_sre_again(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $this->seed(ProvisionOntologySeeder::class);
        $this->freezeTime();
        [, $school, $admin] = $this->tenantWithEnabledConnector();
        $payload = $this->syncPayload($school, evidence: true);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();
        Queue::assertPushed(SreReevaluatePupil::class, 1);

        Queue::fake([SreReevaluatePupil::class]);
        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_evidence_body_change_keeps_one_row_and_enqueues_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $this->seed(ProvisionOntologySeeder::class);
        $this->freezeTime();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();
        $payload = $this->syncPayload($school, evidence: true);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();

        $payload['pupils'][0]['evidence_body'] = 'Updated connector session notes.';
        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();

        $this->assertSame(1, EvidenceRecord::query()->where('tenant_id', $tenant->id)->where('external_id', 'ext-sync-1')->count());
        $this->assertDatabaseHas('evidence_records', [
            'external_id' => 'ext-sync-1',
            'body' => 'Updated connector session notes.',
            'source' => EvidenceSource::Connector->value,
        ]);
        Queue::assertPushed(SreReevaluatePupil::class, 2);
    }

    public function test_pupil_only_change_without_evidence_change_does_not_enqueue_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $this->seed(ProvisionOntologySeeder::class);
        $this->freezeTime();
        [, $school, $admin] = $this->tenantWithEnabledConnector();
        $payload = $this->syncPayload($school, evidence: true);

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();
        Queue::assertPushed(SreReevaluatePupil::class, 1);

        Queue::fake([SreReevaluatePupil::class]);
        $payload['pupils'][0]['given_name'] = 'Updated';
        unset($payload['pupils'][0]['evidence_external_id'], $payload['pupils'][0]['evidence_provision_code'], $payload['pupils'][0]['evidence_occurred_at'], $payload['pupils'][0]['evidence_body']);
        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $payload)->assertAccepted();

        $this->assertSame('Updated', Pupil::query()->where('mis_key', 'MIS-100')->value('given_name'));
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_job_handle_creates_pupil_and_evidence_without_authenticated_user(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        $this->seed(ProvisionOntologySeeder::class);
        $this->freezeTime();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();

        $this->assertGuest();

        $job = new ConnectorSync($tenant->id, $school->id, $admin->id, $this->syncPayload($school, evidence: true)['pupils']);
        $job->handle(
            app(FilterConnectorPayload::class),
            app(ConnectorPupilUpserter::class),
            app(ImportedInterventionEvidenceUpserter::class),
            app(EnqueueSreReevaluation::class),
        );

        $pupil = Pupil::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('mis_key', 'MIS-100')
            ->first();

        $this->assertNotNull($pupil);
        $this->assertSame($tenant->id, $pupil->tenant_id);
        $this->assertSame(1, EvidenceRecord::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('external_id', 'ext-sync-1')
            ->count());
        Queue::assertPushed(SreReevaluatePupil::class, 1);
    }

    public function test_missing_connector_row_returns_422_and_does_not_write_or_enqueue_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        [, $school, $admin] = $this->tenantWithConnectorsEnabled();

        $response = $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $this->syncPayload($school));

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'connector_disabled');

        $this->assertDatabaseCount('connectors', 0);
        $this->assertDatabaseCount('pupils', 0);
        $this->assertDatabaseCount('evidence_records', 0);
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_job_failure_after_bind_logs_error_with_tenant_id_and_continues_remaining_pupils(): void
    {
        Log::spy();
        [$tenant, $school, $admin] = $this->tenantWithEnabledConnector();
        $realFilter = new FilterConnectorPayload;

        $this->mock(FilterConnectorPayload::class, function (MockInterface $mock) use ($realFilter): void {
            $calls = 0;
            $mock->shouldReceive('handle')->andReturnUsing(function (
                Connector $connector,
                string $schoolId,
                array $payload,
            ) use ($realFilter, &$calls): array {
                $calls++;

                if ($calls === 1) {
                    throw new RuntimeException('sync boom');
                }

                return $realFilter->handle($connector, $schoolId, $payload);
            });
        });

        $job = new ConnectorSync($tenant->id, $school->id, $admin->id, [
            [
                'mis_key' => 'MIS-FAIL',
                'given_name' => 'Fail',
            ],
            [
                'mis_key' => 'MIS-100',
                'given_name' => 'Ada',
            ],
        ]);

        $job->handle(
            app(FilterConnectorPayload::class),
            app(ConnectorPupilUpserter::class),
            app(ImportedInterventionEvidenceUpserter::class),
            app(EnqueueSreReevaluation::class),
        );

        $this->assertSame(0, Pupil::withoutGlobalScope('tenant')->where('mis_key', 'MIS-FAIL')->count());
        $this->assertSame(1, Pupil::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('mis_key', 'MIS-100')->count());
        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($tenant): bool {
            return $message === 'ConnectorSync pupil failed.'
                && ($context['tenant_id'] ?? null) === $tenant->id
                && ($context['message'] ?? null) === 'sync boom'
                && ! array_key_exists('evidence', $context)
                && ! array_key_exists('body', $context);
        })->once();
    }

    public function test_senco_post_returns_403_forbidden_and_does_not_write(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        [$tenant, $school] = $this->tenantWithEnabledConnector();
        $senco = User::factory()->forTenant($tenant)->senco()->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/connectors/sync', $this->syncPayload($school));

        $response->assertForbidden()
            ->assertJsonPath('message', AccessMessages::FORBIDDEN)
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseCount('pupils', 0);
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_guest_returns_401(): void
    {
        $this->postJson('/api/v1/connectors/sync', [
            'school_id' => '01INVALIDSCHOOLID0000000000',
            'pupils' => [['mis_key' => 'MIS-100']],
        ])->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_other_tenant_school_returns_422_and_does_not_write(): void
    {
        Queue::fake([SreReevaluatePupil::class]);
        [, , $admin] = $this->tenantWithEnabledConnector();
        $foreignSchool = School::factory()->forTenant(Tenant::factory()->create())->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $this->syncPayload($foreignSchool));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);

        $this->assertDatabaseCount('pupils', 0);
        Queue::assertNotPushed(SreReevaluatePupil::class);
    }

    public function test_soft_deleted_pupil_with_same_mis_key_is_not_resurrected(): void
    {
        [, $school, $admin] = $this->tenantWithEnabledConnector();
        $left = Pupil::factory()->forSchool($school)->withMisKey('MIS-100')->create();
        $left->delete();

        $this->actingAs($admin)->postJson('/api/v1/connectors/sync', $this->syncPayload($school))->assertAccepted();

        $this->assertSoftDeleted('pupils', ['id' => $left->id]);
        $this->assertSame(1, Pupil::withTrashed()->where('school_id', $school->id)->where('mis_key', 'MIS-100')->count());
        $this->assertSame(0, Pupil::query()->where('mis_key', 'MIS-100')->count());
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
                ConnectorField::YearGroup->value => true,
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
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::Connectors->value)
            ->update(['enabled' => true]);
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        return [$tenant, $school, $admin];
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

    /**
     * @return array{school_id: string, pupils: list<array<string, mixed>>}
     */
    private function syncPayload(School $school, bool $evidence = false): array
    {
        $pupil = [
            'mis_key' => 'MIS-100',
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
            'year_group' => 'Year 7',
        ];

        if ($evidence) {
            $pupil['evidence_external_id'] = 'ext-sync-1';
            $pupil['evidence_provision_code'] = 'UNIVERSAL';
            $pupil['evidence_occurred_at'] = now()->subDay()->utc()->toIso8601String();
            $pupil['evidence_body'] = 'Connector literacy support session.';
        }

        return [
            'school_id' => $school->id,
            'pupils' => [$pupil],
        ];
    }
}
