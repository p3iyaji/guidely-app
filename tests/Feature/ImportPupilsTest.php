<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportPupilsTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_can_download_import_template(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $response = $this->actingAs($senco)->get('/api/v1/import/template');

        $response->assertOk()
            ->assertDownload('guidely-import-template.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('pupil_identifier', $content);
        $this->assertStringContainsString('school_name', $content);
        $this->assertStringContainsString('school_id', $content);
        $this->assertStringContainsString('sen_status', $content);
    }

    public function test_guest_cannot_download_template_or_upload_pupils(): void
    {
        $this->getJson('/api/v1/import/template')->assertUnauthorized();

        $this->postJson('/api/v1/import/pupils', [
            'file' => $this->csvUpload($this->csvContents([
                $this->validRow('Oak Primary', 'MIS-G'),
            ])),
        ])->assertUnauthorized();
    }

    public function test_tenant_admin_can_download_import_template(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)
            ->get('/api/v1/import/template')
            ->assertOk()
            ->assertDownload('guidely-import-template.csv');
    }

    public function test_teacher_cannot_download_or_upload_import(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $this->actingAs($teacher)
            ->getJson('/api/v1/import/template')
            ->assertForbidden()
            ->assertJsonPath('message', AccessMessages::FORBIDDEN)
            ->assertJsonPath('code', 'forbidden');

        $file = $this->csvUpload($this->csvContents([
            $this->validRow($school->name, 'MIS-T1'),
        ]));

        $this->actingAs($teacher)
            ->post('/api/v1/import/pupils', ['file' => $file])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_partial_success_commits_valid_rows_and_reports_errors(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            $this->validRow($school->name, 'MIS-100', 'Alex', 'Taylor'),
            ['', '', '', '', $school->name, '', 'Year 8', 'neither', ''],
            $this->validRow($school->name, 'MIS-101', 'Jamie', 'Lee'),
        ]);

        $response = $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)]);

        $response->assertOk()
            ->assertJsonPath('data.summary.committed_count', 2)
            ->assertJsonPath('data.summary.error_count', 1)
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.committed.0.action', 'created')
            ->assertJsonPath('data.committed.0.mis_key', 'MIS-100')
            ->assertJsonPath('data.committed.1.mis_key', 'MIS-101');

        $this->assertDatabaseHas('pupils', [
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'mis_key' => 'MIS-100',
            'given_name' => 'Alex',
        ]);
        $this->assertDatabaseHas('pupils', [
            'mis_key' => 'MIS-101',
            'given_name' => 'Jamie',
        ]);
        $this->assertDatabaseCount('pupils', 2);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilCreated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $senco->id,
            'resource_type' => 'pupil',
        ]);

        $createAudit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilCreated->value)
            ->where('user_id', $senco->id)
            ->latest('created_at')
            ->firstOrFail();
        $this->assertSame('import', $createAudit->metadata['source'] ?? null);
    }

    public function test_upsert_updates_existing_pupil_by_school_and_mis_key(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->withMisKey('MIS-UP')->create([
            'given_name' => 'Old',
            'family_name' => 'Name',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither,
        ]);

        $csv = $this->csvContents([
            $this->validRow($school->name, 'MIS-UP', 'New', 'Name', 'Year 9', 'ehcp'),
        ]);

        $response = $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)]);

        $response->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.committed.0.action', 'updated')
            ->assertJsonPath('data.committed.0.id', $pupil->id)
            ->assertJsonPath('data.committed.0.given_name', 'New')
            ->assertJsonPath('data.committed.0.year_group', 'Year 9')
            ->assertJsonPath('data.committed.0.send_status', SendStatus::Ehcp->value);

        $this->assertDatabaseCount('pupils', 1);
        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'given_name' => 'New',
            'year_group' => 'Year 9',
            'send_status' => SendStatus::Ehcp->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilUpdated->value,
            'resource_id' => $pupil->id,
        ]);

        $updateAudit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilUpdated->value)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->firstOrFail();
        $this->assertSame('import', $updateAudit->metadata['source'] ?? null);
    }

    public function test_both_school_id_and_school_name_is_row_error(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            ['MIS-BOTH', 'Alex', 'Taylor', '', $school->name, $school->id, 'Year 8', 'neither', ''],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.errors.0.message', 'Provide either school_id or school_name, not both.');
    }

    public function test_missing_school_is_row_error(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            ['MIS-NOSCH', 'Alex', 'Taylor', '', '', '', 'Year 8', 'neither', ''],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.errors.0.message', 'School is required (school_name or school_id).');
    }

    public function test_inaccessible_school_id_is_row_error(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $other = School::factory()->forTenant($tenant)->create(['name' => 'Other Primary']);

        $csv = $this->csvContents([
            ['MIS-INACC', 'Alex', 'Taylor', '', '', $other->id, 'Year 8', 'neither', ''],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.errors.0.message', 'You do not have access to the selected School.');

        $this->assertDatabaseMissing('pupils', ['mis_key' => 'MIS-INACC']);
        $this->assertTrue($senco->schools()->whereKey($school->id)->exists());
    }

    public function test_ambiguous_school_name_is_row_error(): void
    {
        $tenant = Tenant::factory()->create();
        $first = School::factory()->forTenant($tenant)->create(['name' => 'Twin Primary']);
        $second = School::factory()->forTenant($tenant)->create(['name' => 'Twin Primary']);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach([$first->id, $second->id]);

        $csv = $this->csvContents([
            $this->validRow('Twin Primary', 'MIS-AMB'),
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.errors.0.message', 'Multiple Schools match this school_name.');
    }

    public function test_bom_prefixed_header_still_imports(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $header = "\xEF\xBB\xBFpupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes\n";
        $csv = $header.'MIS-BOM,Alex,Taylor,,'.$school->name.',,Year 8,sen_support,,'."\n";

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.committed.0.mis_key', 'MIS-BOM');
    }

    public function test_notes_persist_on_create(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            ['MIS-NOTE', 'Alex', 'Taylor', '', $school->name, '', 'Year 8', 'neither', 'Support plan review'],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.committed.0.notes', 'Support plan review');

        $this->assertDatabaseHas('pupils', [
            'mis_key' => 'MIS-NOTE',
            'notes' => 'Support plan review',
        ]);
    }

    public function test_upsert_with_blank_date_of_birth_does_not_clear_existing_dob(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->withMisKey('MIS-DOB')->create([
            'date_of_birth' => '2012-04-15',
            'notes' => 'Keep me',
        ]);

        $csv = $this->csvContents([
            ['MIS-DOB', 'Updated', 'Name', '', $school->name, '', 'Year 9', 'ehcp', ''],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.committed.0.action', 'updated')
            ->assertJsonPath('data.committed.0.date_of_birth', '2012-04-15')
            ->assertJsonPath('data.committed.0.notes', 'Keep me');

        $pupil->refresh();
        $this->assertSame('Updated', $pupil->given_name);
        $this->assertSame('2012-04-15', $pupil->date_of_birth?->format('Y-m-d'));
        $this->assertSame('Keep me', $pupil->notes);
    }

    public function test_duplicate_headers_return_422(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = "pupil_identifier,given_name,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes\n"
            .'MIS-DUP,Alex,Alex,Taylor,,'.$school->name.',,Year 8,neither,,'."\n";

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_excel_sep_preamble_is_skipped(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = "sep=,\n"
            ."pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes\n"
            .'MIS-SEP,Alex,Taylor,,'.$school->name.',,Year 8,sen_support,,'."\n";

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.committed.0.mis_key', 'MIS-SEP')
            ->assertJsonPath('data.committed.0.row', 3);
    }

    public function test_all_invalid_rows_commit_nothing(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            ['', '', '', '', $school->name, '', 'Year 8', 'neither', ''],
            $this->validRow('Unknown School', 'MIS-X'),
        ]);

        $response = $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)]);

        $response->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.summary.error_count', 2)
            ->assertJsonCount(0, 'data.committed');

        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_empty_file_returns_422(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $file = UploadedFile::fake()->createWithContent('empty.csv', '');

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('pupils', 0);
    }

    public function test_header_only_csv_returns_422(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $file = $this->csvUpload("pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes\n");

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_evidence_column_values_return_row_error(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = "pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes,evidence_body\n"
            .'MIS-E1,Alex,Taylor,,'.$school->name.',,Year 8,sen_support,,Some evidence text'."\n"
            .'MIS-E2,Jamie,Lee,,'.$school->name.',,Year 8,neither,,'."\n";

        $response = $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)]);

        $response->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.summary.error_count', 1)
            ->assertJsonPath('data.errors.0.row', 2)
            ->assertJsonPath('data.errors.0.message', 'Evidence import not available yet.')
            ->assertJsonPath('data.committed.0.mis_key', 'MIS-E2');
    }

    public function test_soft_deleted_mis_key_is_row_error(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $left = Pupil::factory()->forSchool($school)->withMisKey('MIS-LEFT')->create();
        $left->delete();

        $csv = $this->csvContents([
            $this->validRow($school->name, 'MIS-LEFT'),
        ]);

        $response = $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)]);

        $response->assertOk()
            ->assertJsonPath('data.summary.committed_count', 0)
            ->assertJsonPath('data.errors.0.message', 'A left Pupil already uses this MIS key in this School.');

        $this->assertSoftDeleted('pupils', ['id' => $left->id]);
        $this->assertSame(0, Pupil::query()->count());
    }

    public function test_null_mis_keys_create_distinct_pupils(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            $this->validRow($school->name, '', 'One', 'Null'),
            $this->validRow($school->name, '', 'Two', 'Null'),
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 2);

        $this->assertDatabaseCount('pupils', 2);
    }

    public function test_school_id_column_resolves_accessible_school(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $csv = $this->csvContents([
            ['MIS-SID', 'Pat', 'Smith', '', '', $school->id, 'Year 8', 'SEN Support', ''],
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1)
            ->assertJsonPath('data.committed.0.school_id', $school->id);
    }

    public function test_import_does_not_auto_assign_teachers(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $csv = $this->csvContents([
            $this->validRow($school->name, 'MIS-NA'),
        ]);

        $this->actingAs($senco)
            ->post('/api/v1/import/pupils', ['file' => $this->csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1);

        $pupil = Pupil::query()->where('mis_key', 'MIS-NA')->firstOrFail();
        $this->assertFalse($pupil->isAssignedTo($teacher));

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('forbiddenImportRoles')]
    public function test_non_mutator_roles_cannot_import(string $factoryState): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $actor->schools()->attach($school->id);

        $this->actingAs($actor)
            ->post('/api/v1/import/pupils', [
                'file' => $this->csvUpload($this->csvContents([
                    $this->validRow($school->name, 'MIS-F'),
                ])),
            ])
            ->assertForbidden();
    }

    public function test_imported_pupil_appears_for_teacher_after_assignment(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $upload = $this->actingAs($senco)->post('/api/v1/import/pupils', [
            'file' => $this->csvUpload($this->csvContents([
                $this->validRow($school->name, 'MIS-E2E'),
            ])),
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.summary.committed_count', 1);

        $pupilId = $upload->json('data.committed.0.id');
        $this->assertNotEmpty($pupilId);

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupilId.'/assignments', [
            'user_id' => $teacher->id,
        ])->assertOk();

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pupilId);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenImportRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'support_staff' => ['supportStaff'],
            'school_leader' => ['schoolLeader'],
        ];
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndSenco(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create(['name' => 'Oak Primary']);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csvContents(array $rows): string
    {
        $lines = [
            'pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn (string $cell): string => str_contains($cell, ',') ? '"'.$cell.'"' : $cell,
                $row,
            ));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function validRow(
        string $schoolName,
        string $misKey,
        string $given = 'Alex',
        string $family = 'Taylor',
        string $year = 'Year 8',
        string $sen = 'sen_support',
    ): array {
        return [$misKey, $given, $family, '', $schoolName, '', $year, $sen, ''];
    }

    private function csvUpload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pupils.csv', $contents);
    }
}
