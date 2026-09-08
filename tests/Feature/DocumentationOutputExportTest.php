<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\SreDimension;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Outputs\ExportDocumentationOutput;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Determination;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantFeatureFlag;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class DocumentationOutputExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_downloads_confirmed_summary_as_encrypted_zip_with_checksum_and_audit(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Bus::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create([
                'payload' => [
                    'kind' => DocumentationOutputType::ReviewSummary->value,
                    'determination_ids' => ['det_1'],
                    'evidence_ids' => ['ev_1'],
                    'gap_ids' => ['gap_1'],
                ],
            ]);

        $response = $this->actingAs($senco)
            ->get('/api/v1/documentation-outputs/'.$output->id.'/download');

        $response->assertOk()
            ->assertDownload('review-summary-v1.zip')
            ->assertHeader('content-type', 'application/zip');

        $cacheControl = strtolower((string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $response->assertHeader('pragma', 'no-cache');
        $response->assertHeader('expires', '0');

        $zipBytes = $response->streamedContent();
        $this->assertSame('PK', substr($zipBytes, 0, 2));

        $output->refresh();
        $this->assertSame(hash('sha256', $zipBytes), $output->checksum);
        $this->assertSame('local', $output->file_disk);
        $this->assertSame('documentation-outputs/'.$output->id.'/bundle.zip.enc', $output->file_path);
        $this->assertSame(strlen($zipBytes), $output->byte_size);
        $this->assertTrue($output->file_path !== null && $output->file_path !== '');

        $encrypted = Storage::disk('local')->get($output->file_path);
        $this->assertNotSame($zipBytes, $encrypted);
        $this->assertSame($zipBytes, Crypt::decryptString($encrypted));
        $this->assertFalse(Storage::disk('public')->exists($output->file_path));

        [$html, $sidecar] = $this->unzip($zipBytes);
        $this->assertStringContainsString(DocumentationOutput::DISCLAIMER_TEXT, $html);
        $this->assertSame(['ev_1'], $sidecar['evidence_ids'] ?? null);
        $this->assertSame(['det_1'], $sidecar['determination_ids'] ?? null);
        $this->assertSame(['gap_1'], $sidecar['gap_ids'] ?? null);
        $this->assertSame($senco->id, $sidecar['confirmer_user_id'] ?? null);
        $this->assertSame(1, $sidecar['version'] ?? null);
        $this->assertSame(hash('sha256', $html), $sidecar['checksum'] ?? null);
        $this->assertNoDiagnosisCopy($html, $sidecar);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::DocumentationOutputDownloaded->value)
            ->where('resource_id', $output->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(DocumentationOutputType::ReviewSummary->value, $audit->metadata['type'] ?? null);
        $this->assertSame(1, $audit->metadata['version'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($cycle->id, $audit->metadata['review_cycle_id'] ?? null);
        $this->assertSame($output->checksum, $audit->metadata['checksum'] ?? null);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        Bus::assertNothingDispatched();
    }

    public function test_school_leader_downloads_confirmed_output(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $this->actingAs($leader)
            ->get('/api/v1/documentation-outputs/'.$output->id.'/download')
            ->assertOk()
            ->assertDownload('review-summary-v1.zip');
    }

    public function test_returns_403_when_teacher_downloads(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $this->assertForbidden($this->actingAs($teacher)->getJson(
            '/api/v1/documentation-outputs/'.$output->id.'/download',
        ));
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::DocumentationOutputDownloaded->value,
            'resource_id' => $output->id,
        ]);
    }

    public function test_returns_403_when_other_school_senco_downloads(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($otherSchool->id);
        $owner = User::factory()->forTenant($tenant)->senco()->create();
        $owner->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($owner)
            ->reviewSummary()
            ->create();

        $this->assertForbidden($this->actingAs($senco)->getJson(
            '/api/v1/documentation-outputs/'.$output->id.'/download',
        ));
    }

    public function test_repeat_download_streams_the_same_checksum_and_writes_a_second_audit(): void
    {
        Storage::fake('local');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $first = $this->actingAs($senco)
            ->get('/api/v1/documentation-outputs/'.$output->id.'/download');
        $first->assertOk();
        $firstBytes = $first->streamedContent();
        $firstChecksum = $output->refresh()->checksum;

        $second = $this->actingAs($senco)
            ->get('/api/v1/documentation-outputs/'.$output->id.'/download');
        $second->assertOk();
        $secondBytes = $second->streamedContent();

        $this->assertSame($firstChecksum, $output->refresh()->checksum);
        $this->assertSame($firstChecksum, hash('sha256', $firstBytes));
        $this->assertSame($firstChecksum, hash('sha256', $secondBytes));
        $this->assertSame(hash('sha256', $firstBytes), hash('sha256', $secondBytes));
        $this->assertSame(2, AuditEvent::query()
            ->where('event_type', AuditEventType::DocumentationOutputDownloaded->value)
            ->where('resource_id', $output->id)
            ->count());
    }

    public function test_returns_403_feature_not_available_when_flag_off_and_senco_posts_tribunal_pack(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::TribunalPack->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
                'purpose' => 'Tribunal bundle for hearing',
            ])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This feature is not available for this Tenant.',
                'code' => 'feature_not_available',
                'feature' => 'advanced_documentation_packs',
            ]);

        $this->assertDatabaseCount('documentation_outputs', 0);
    }

    public function test_returns_422_when_flag_on_and_tribunal_pack_has_no_purpose(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $this->enableAdvancedPacks($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::TribunalPack->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose'])
            ->assertJsonPath('errors.purpose.0', 'A purpose is required for tribunal and inspection packs.');

        $this->assertDatabaseCount('documentation_outputs', 0);
    }

    public function test_flag_on_generates_tribunal_pack_with_purpose_and_generate_audit(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $this->enableAdvancedPacks($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $evidence = EvidenceRecord::factory()->forPupil($pupil)->authoredBy($senco)->create();
        $draft = EvidenceRecord::factory()->forPupil($pupil)->authoredBy($senco)->draft()->create();
        $determinationIds = [];

        foreach (SreDimension::cases() as $dimension) {
            $determination = Determination::factory()
                ->forPupil($pupil)
                ->forDimension($dimension)
                ->current()
                ->create([
                    'reasoning_pathway' => [
                        'dimension' => $dimension->value,
                        'evidence_ids' => $dimension === SreDimension::SequentialCompliance
                            ? [$evidence->id, $draft->id]
                            : [],
                    ],
                ]);
            $determinationIds[] = $determination->id;
        }

        $gap = Gap::factory()->forPupil($pupil)->open()->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::TribunalPack->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
            'purpose' => 'Prepare papers for a SEND tribunal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', DocumentationOutputType::TribunalPack->value)
            ->assertJsonPath('data.purpose', 'Prepare papers for a SEND tribunal')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.has_file', false)
            ->assertJsonPath('data.payload.kind', DocumentationOutputType::TribunalPack->value)
            ->assertJsonPath('data.payload.evidence_ids.0', $evidence->id)
            ->assertJsonPath('data.payload.gap_ids.0', $gap->id);

        $this->assertSame([$evidence->id], $response->json('data.payload.evidence_ids'));
        $this->assertSame($determinationIds, $response->json('data.payload.determination_ids'));

        $this->assertDatabaseHas('documentation_outputs', [
            'id' => $response->json('data.id'),
            'type' => DocumentationOutputType::TribunalPack->value,
            'purpose' => 'Prepare papers for a SEND tribunal',
            'version' => 1,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::DocumentationOutputGenerated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Prepare papers for a SEND tribunal', $audit->metadata['purpose'] ?? null);
        $this->assertSame(DocumentationOutputType::TribunalPack->value, $audit->metadata['type'] ?? null);

        Bus::assertNothingDispatched();
    }

    public function test_flag_on_generates_inspection_pack_with_purpose(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $this->enableAdvancedPacks($tenant);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::InspectionPack->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
                'purpose' => 'Ofsted inspection pack',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', DocumentationOutputType::InspectionPack->value)
            ->assertJsonPath('data.purpose', 'Ofsted inspection pack');
    }

    public function test_senco_downloads_tribunal_pack_html_includes_purpose(): void
    {
        Storage::fake('local');

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->tribunalPack('Prepare papers for a SEND tribunal')
            ->create();

        $response = $this->actingAs($senco)
            ->get('/api/v1/documentation-outputs/'.$output->id.'/download');

        $response->assertOk()
            ->assertDownload('tribunal-pack-v1.zip');

        [$html] = $this->unzip($response->streamedContent());
        $this->assertStringContainsString('Prepare papers for a SEND tribunal', $html);
        $this->assertStringContainsString(DocumentationOutput::DISCLAIMER_TEXT, $html);
    }

    public function test_review_summary_generate_is_not_flag_gated(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::ReviewSummary->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', DocumentationOutputType::ReviewSummary->value)
            ->assertJsonPath('data.purpose', null);
    }

    public function test_guest_cannot_download_an_output(): void
    {
        $this->getJson('/api/v1/documentation-outputs/01h00000000000000000000000/download')
            ->assertUnauthorized();
    }

    public function test_returns_404_when_another_tenant_downloads_an_output(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        $otherSenco = User::factory()->forTenant($otherTenant)->senco()->create();
        $otherSenco->schools()->attach($otherSchool->id);

        $this->actingAs($otherSenco)
            ->getJson('/api/v1/documentation-outputs/'.$output->id.'/download')
            ->assertNotFound();
    }

    #[DataProvider('forbiddenDownloadRoles')]
    public function test_returns_403_when_support_or_tenant_admin_downloads(string $roleFactory): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $actor = User::factory()->forTenant($tenant)->{$roleFactory}()->create();
        $actor->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $this->assertForbidden($this->actingAs($actor)->getJson(
            '/api/v1/documentation-outputs/'.$output->id.'/download',
        ));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenDownloadRoles(): array
    {
        return [
            'support' => ['supportStaff'],
            'tenant_admin' => ['tenantAdmin'],
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function unzip(string $zipBytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'guidely-export-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $zipBytes);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $html = $zip->getFromName(ExportDocumentationOutput::HTML_FILENAME);
        $sidecarJson = $zip->getFromName(ExportDocumentationOutput::SIDECAR_FILENAME);
        $zip->close();
        unlink($path);

        $this->assertIsString($html);
        $this->assertIsString($sidecarJson);
        $sidecar = json_decode($sidecarJson, true);
        $this->assertIsArray($sidecar);

        return [$html, $sidecar];
    }

    /**
     * @param  array<string, mixed>  $sidecar
     */
    private function assertNoDiagnosisCopy(string $html, array $sidecar): void
    {
        $htmlLower = strtolower($html);
        $withoutDisclaimer = str_replace(strtolower(DocumentationOutput::DISCLAIMER_TEXT), '', $htmlLower);
        $sidecarBody = strtolower((string) json_encode($sidecar));

        $this->assertStringNotContainsString('confidence', $htmlLower);
        $this->assertStringNotContainsString('auto-approved', $htmlLower);
        $this->assertStringNotContainsString('diagnos', $withoutDisclaimer);
        $this->assertStringNotContainsString('diagnos', $sidecarBody);
        $this->assertStringNotContainsString('confidence', $sidecarBody);
        $this->assertStringNotContainsString('auto-approved', $sidecarBody);
    }

    private function assertForbidden(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    private function enableAdvancedPacks(Tenant $tenant): void
    {
        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', FeatureFlagKey::AdvancedDocumentationPacks->value)
            ->update(['enabled' => true]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndSenco(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create([
            'name' => 'Alex SENCO',
        ]);
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }
}
