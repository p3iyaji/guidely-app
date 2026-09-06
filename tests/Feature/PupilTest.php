<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PupilTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_can_create_a_pupil_in_an_accessible_school(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();

        $response = $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Alex',
            'family_name' => 'Taylor',
            'mis_key' => 'MIS-1001',
            'date_of_birth' => '2012-04-15',
            'year_group' => 'Year 8',
            'send_status' => SendStatus::SenSupport->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.given_name', 'Alex')
            ->assertJsonPath('data.family_name', 'Taylor')
            ->assertJsonPath('data.school_id', $school->id)
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.mis_key', 'MIS-1001')
            ->assertJsonPath('data.date_of_birth', '2012-04-15')
            ->assertJsonPath('data.year_group', 'Year 8')
            ->assertJsonPath('data.send_status', SendStatus::SenSupport->value)
            ->assertJsonPath('data.documentation_status', DocumentationStatus::NotStarted->value);

        $pupilId = $response->json('data.id');
        $this->assertNotNull($pupilId);
        $this->assertDatabaseHas('pupils', [
            'id' => $pupilId,
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'given_name' => 'Alex',
            'family_name' => 'Taylor',
            'mis_key' => 'MIS-1001',
            'year_group' => 'Year 8',
            'send_status' => SendStatus::SenSupport->value,
            'documentation_status' => DocumentationStatus::NotStarted->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilCreated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $senco->id,
            'resource_type' => 'pupil',
            'resource_id' => $pupilId,
        ]);
    }

    public function test_tenant_admin_can_update_year_group_and_send_status(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither,
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'year_group' => 'Year 9',
            'send_status' => SendStatus::Ehcp->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.year_group', 'Year 9')
            ->assertJsonPath('data.send_status', SendStatus::Ehcp->value);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'year_group' => 'Year 9',
            'send_status' => SendStatus::Ehcp->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilUpdated->value,
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'resource_type' => 'pupil',
            'resource_id' => $pupil->id,
        ]);
    }

    public function test_senco_soft_deletes_a_pupil_and_emits_audit_event(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Left',
            'family_name' => 'Pupil',
        ]);

        $this->actingAs($senco)
            ->deleteJson('/api/v1/pupils/'.$pupil->id)
            ->assertNoContent();

        $this->assertSoftDeleted('pupils', [
            'id' => $pupil->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::PupilDeleted->value,
            'tenant_id' => $tenant->id,
            'user_id' => $senco->id,
            'resource_type' => 'pupil',
            'resource_id' => $pupil->id,
        ]);

        $this->actingAs($senco)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_duplicate_mis_key_in_same_school_returns_422(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        Pupil::factory()->forSchool($school)->withMisKey('MIS-DUPE')->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Sam',
            'family_name' => 'Other',
            'mis_key' => 'MIS-DUPE',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mis_key']);
    }

    public function test_null_mis_keys_may_repeat_within_a_school(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        Pupil::factory()->forSchool($school)->create(['mis_key' => null]);

        $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Jamie',
            'family_name' => 'NoKey',
            'mis_key' => null,
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ])->assertCreated();
    }

    public function test_teacher_index_returns_empty_list_without_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        Pupil::factory()->forSchool($school)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_support_staff_index_returns_empty_list(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        Pupil::factory()->forSchool($school)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);

        $this->actingAs($support)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('mutatingForbiddenRoles')]
    public function test_non_mutator_roles_cannot_create_update_or_delete_pupils(string $factoryState): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $actor->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Protected',
        ]);

        $this->assertForbidden(
            $this->actingAs($actor)->postJson('/api/v1/pupils', [
                'school_id' => $school->id,
                'given_name' => 'Blocked',
                'family_name' => 'Create',
                'year_group' => 'Year 7',
                'send_status' => SendStatus::Neither->value,
            ])
        );

        $this->assertForbidden(
            $this->actingAs($actor)->patchJson('/api/v1/pupils/'.$pupil->id, [
                'year_group' => 'Year 11',
            ])
        );

        $this->assertForbidden(
            $this->actingAs($actor)->deleteJson('/api/v1/pupils/'.$pupil->id)
        );

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'given_name' => 'Protected',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('pupils', [
            'tenant_id' => $tenant->id,
            'given_name' => 'Blocked',
        ]);
    }

    public function test_school_leader_can_view_pupils_in_accessible_schools_only(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create(['name' => 'Assigned']);
        $schoolB = School::factory()->forTenant($tenant)->create(['name' => 'Other']);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create(['family_name' => 'Assigned']);
        $pupilB = Pupil::factory()->forSchool($schoolB)->create(['family_name' => 'Hidden']);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($schoolA->id);

        $this->actingAs($leader)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pupilA->id)
            ->assertJsonMissing(['id' => $pupilB->id]);

        $this->actingAs($leader)->getJson('/api/v1/pupils/'.$pupilA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $pupilA->id);

        $this->assertForbidden(
            $this->actingAs($leader)->getJson('/api/v1/pupils/'.$pupilB->id)
        );
    }

    public function test_soft_deleted_pupils_are_omitted_from_default_index(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $active = Pupil::factory()->forSchool($school)->create(['family_name' => 'Active']);
        $left = Pupil::factory()->forSchool($school)->create(['family_name' => 'Left']);
        $left->delete();

        $this->actingAs($senco)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['id' => $left->id]);
    }

    public function test_senco_can_include_left_pupils_with_include_left(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $active = Pupil::factory()->forSchool($school)->create(['family_name' => 'Active']);
        $left = Pupil::factory()->forSchool($school)->create(['family_name' => 'Left']);
        $left->delete();

        $response = $this->actingAs($senco)->getJson('/api/v1/pupils?include_left=1');

        $response->assertOk()->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($left->id, $ids);
    }

    public function test_school_leader_include_left_does_not_expose_trashed_pupils(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $left = Pupil::factory()->forSchool($school)->create();
        $left->delete();

        $this->actingAs($leader)->getJson('/api/v1/pupils?include_left=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_show_by_id_returns_404_without_leakage(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create([
            'given_name' => 'Secret',
            'family_name' => 'CrossTenant',
        ]);
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $response = $this->actingAs($adminA)->getJson('/api/v1/pupils/'.$pupilB->id);

        $response->assertNotFound()
            ->assertJsonMissing(['given_name' => 'Secret'])
            ->assertJsonMissing(['id' => $pupilB->id]);

        $this->assertStringNotContainsString('Secret', $response->getContent());
        $this->assertStringNotContainsString($tenantB->id, $response->getContent());
    }

    public function test_cross_tenant_update_and_delete_return_404_and_leave_row_unchanged(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create([
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither,
        ]);
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $this->actingAs($adminA)->patchJson('/api/v1/pupils/'.$pupilB->id, [
            'year_group' => 'Year 11',
        ])->assertNotFound();

        $this->actingAs($adminA)->deleteJson('/api/v1/pupils/'.$pupilB->id)
            ->assertNotFound();

        $this->assertDatabaseHas('pupils', [
            'id' => $pupilB->id,
            'tenant_id' => $tenantB->id,
            'year_group' => 'Year 7',
            'deleted_at' => null,
        ]);
    }

    public function test_senco_cannot_create_pupil_for_inaccessible_school(): void
    {
        $tenant = Tenant::factory()->create();
        $assigned = School::factory()->forTenant($tenant)->create();
        $other = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($assigned->id);

        $response = $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $other->id,
            'given_name' => 'No',
            'family_name' => 'Access',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);
    }

    public function test_create_rejects_inactive_school(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->inactive()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Inactive',
            'family_name' => 'School',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);
    }

    public function test_invalid_create_payload_returns_422(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => '',
            'family_name' => '',
            'year_group' => '',
            'send_status' => 'not-a-status',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['given_name', 'family_name', 'year_group', 'send_status']);
    }

    public function test_unauthenticated_pupil_list_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/pupils');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertTrue(
            str_contains($response->headers->get('content-type', ''), 'application/json'),
            'Unauthenticated API responses must be JSON, not the Blade SPA shell'
        );
    }

    public function test_store_ignores_injected_tenant_id_and_documentation_status(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $school = School::factory()->forTenant($tenantA)->create();
        $admin = User::factory()->forTenant($tenantA)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Owned',
            'family_name' => 'ByA',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
            'tenant_id' => $tenantB->id,
            'documentation_status' => DocumentationStatus::Ready->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenantA->id)
            ->assertJsonPath('data.documentation_status', DocumentationStatus::NotStarted->value);

        $this->assertDatabaseHas('pupils', [
            'given_name' => 'Owned',
            'tenant_id' => $tenantA->id,
            'documentation_status' => DocumentationStatus::NotStarted->value,
        ]);
    }

    public function test_same_mis_key_is_allowed_in_different_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        Pupil::factory()->forSchool($schoolA)->withMisKey('SHARED-KEY')->create();

        $this->actingAs($admin)->postJson('/api/v1/pupils', [
            'school_id' => $schoolB->id,
            'given_name' => 'Other',
            'family_name' => 'School',
            'mis_key' => 'SHARED-KEY',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ])->assertCreated()
            ->assertJsonPath('data.mis_key', 'SHARED-KEY');
    }

    public function test_teacher_and_support_staff_cannot_show_unassigned_pupil_by_id(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        foreach (['teacher', 'supportStaff'] as $factoryState) {
            $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
            $actor->schools()->attach($school->id);

            $this->assertForbidden(
                $this->actingAs($actor)->getJson('/api/v1/pupils/'.$pupil->id)
            );
        }
    }

    public function test_teacher_and_support_staff_can_show_assigned_pupil_by_id(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        foreach (['teacher', 'supportStaff'] as $factoryState) {
            $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
            $actor->schools()->attach($school->id);
            $pupil->assignTo($actor);

            $this->actingAs($actor)->getJson('/api/v1/pupils/'.$pupil->id)
                ->assertOk()
                ->assertJsonPath('data.id', $pupil->id);

            $pupil->unassign($actor);
        }
    }

    public function test_senco_can_include_left_pupils_with_with_trashed_alias(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $left = Pupil::factory()->forSchool($school)->create(['family_name' => 'Left']);
        $left->delete();

        $this->actingAs($senco)->getJson('/api/v1/pupils?with_trashed=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $left->id);
    }

    public function test_tenant_admin_index_returns_pupils_across_schools_without_pivot(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/pupils');

        $response->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($pupilA->id, $ids);
        $this->assertContains($pupilB->id, $ids);
    }

    public function test_tenant_admin_can_include_left_pupils(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $left = Pupil::factory()->forSchool($school)->create();
        $left->delete();

        $this->actingAs($admin)->getJson('/api/v1/pupils?include_left=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $left->id);
    }

    public function test_senco_cannot_update_or_delete_pupil_in_inaccessible_school(): void
    {
        $tenant = Tenant::factory()->create();
        $assigned = School::factory()->forTenant($tenant)->create();
        $other = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($assigned->id);
        $pupil = Pupil::factory()->forSchool($other)->create([
            'year_group' => 'Year 7',
        ]);

        $this->assertForbidden(
            $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
                'year_group' => 'Year 11',
            ])
        );

        $this->assertForbidden(
            $this->actingAs($senco)->deleteJson('/api/v1/pupils/'.$pupil->id)
        );

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'year_group' => 'Year 7',
            'deleted_at' => null,
        ]);
    }

    public function test_senco_cannot_move_pupil_to_inaccessible_school(): void
    {
        $tenant = Tenant::factory()->create();
        $assigned = School::factory()->forTenant($tenant)->create();
        $other = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($assigned->id);
        $pupil = Pupil::factory()->forSchool($assigned)->create();

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'school_id' => $other->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'school_id' => $assigned->id,
        ]);
    }

    public function test_update_keeps_own_mis_key_and_rejects_peer_collision_on_school_move(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($schoolA)->withMisKey('KEEP-ME')->create();
        Pupil::factory()->forSchool($schoolB)->withMisKey('KEEP-ME')->create();

        $this->actingAs($admin)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'mis_key' => 'KEEP-ME',
            'year_group' => 'Year 10',
        ])->assertOk()
            ->assertJsonPath('data.mis_key', 'KEEP-ME')
            ->assertJsonPath('data.year_group', 'Year 10');

        $this->actingAs($admin)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'school_id' => $schoolB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mis_key']);
    }

    public function test_empty_string_mis_key_normalises_to_null_and_may_repeat(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();

        $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'One',
            'family_name' => 'BlankKey',
            'mis_key' => '',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither->value,
        ])->assertCreated()
            ->assertJsonPath('data.mis_key', null);

        $this->actingAs($senco)->postJson('/api/v1/pupils', [
            'school_id' => $school->id,
            'given_name' => 'Two',
            'family_name' => 'BlankKey',
            'mis_key' => '',
            'year_group' => 'Year 8',
            'send_status' => SendStatus::Neither->value,
        ])->assertCreated()
            ->assertJsonPath('data.mis_key', null);
    }

    public function test_update_rejects_blank_identity_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Keep',
            'family_name' => 'Name',
            'year_group' => 'Year 7',
        ]);

        $this->actingAs($admin)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'given_name' => '   ',
            'family_name' => '',
            'year_group' => ' ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['given_name', 'family_name', 'year_group']);

        $this->assertDatabaseHas('pupils', [
            'id' => $pupil->id,
            'given_name' => 'Keep',
            'family_name' => 'Name',
            'year_group' => 'Year 7',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function mutatingForbiddenRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'support_staff' => ['supportStaff'],
            'school_leader' => ['schoolLeader'],
        ];
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertExactJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndSenco(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenants(): array
    {
        return [
            Tenant::factory()->school()->create(['name' => 'Tenant A']),
            Tenant::factory()->school()->create(['name' => 'Tenant B']),
        ];
    }
}
