<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\AssignmentSource;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PupilAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_can_assign_teacher_to_pupil_with_optional_labels_and_audit(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $response = $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $teacher->id,
            'class_label' => '7A',
            'cohort_label' => 'Wave 1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $pupil->id);

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
            'class_label' => '7A',
            'cohort_label' => 'Wave 1',
            'source' => AssignmentSource::Senco->value,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilAssignmentUpdated)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($senco->id, $audit->user_id);
        $this->assertSame('pupil', $audit->resource_type);
        $this->assertSame([
            'action' => 'assigned',
            'assigned_user_id' => $teacher->id,
            'class_label' => '7A',
            'cohort_label' => 'Wave 1',
            'source' => AssignmentSource::Senco->value,
        ], $audit->metadata);
    }

    public function test_tenant_admin_can_assign_support_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);

        $this->actingAs($admin)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $support->id,
        ])->assertOk();

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $support->id,
            'source' => AssignmentSource::Senco->value,
        ]);
    }

    public function test_tenant_admin_can_unassign(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/pupils/'.$pupil->id.'/assignments/'.$teacher->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilAssignmentUpdated)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame([
            'action' => 'unassigned',
            'assigned_user_id' => $teacher->id,
        ], $audit->metadata);
    }

    public function test_assign_rejects_wrong_role_with_422(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);

        $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $leader->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_assign_rejects_deactivated_assignee(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create([
            'deactivated_at' => now(),
        ]);
        $teacher->schools()->attach($school->id);

        $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $teacher->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);
    }

    public function test_assign_rejects_assignee_without_school_access(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($otherSchool->id);

        $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $teacher->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_senco_cannot_assign_for_inaccessible_school(): void
    {
        $tenant = Tenant::factory()->create();
        $assigned = School::factory()->forTenant($tenant)->create();
        $other = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($assigned->id);
        $pupil = Pupil::factory()->forSchool($other)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($other->id);

        $this->assertForbidden(
            $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
                'user_id' => $teacher->id,
            ])
        );
    }

    #[DataProvider('nonMutatorRoles')]
    public function test_non_mutator_cannot_assign(string $factoryState): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $actor->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $this->assertForbidden(
            $this->actingAs($actor)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
                'user_id' => $teacher->id,
            ])
        );

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);
    }

    #[DataProvider('nonMutatorRoles')]
    public function test_non_mutator_cannot_unassign(string $factoryState): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $actor = User::factory()->forTenant($tenant)->{$factoryState}()->create();
        $actor->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher);

        $this->assertForbidden(
            $this->actingAs($actor)->deleteJson('/api/v1/pupils/'.$pupil->id.'/assignments/'.$teacher->id)
        );

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);
    }

    public function test_teacher_index_returns_only_assigned_pupils(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $assignedA = Pupil::factory()->forSchool($school)->create(['family_name' => 'AssignedA']);
        $assignedB = Pupil::factory()->forSchool($school)->create(['family_name' => 'AssignedB']);
        $unassigned = Pupil::factory()->forSchool($school)->create(['family_name' => 'Hidden']);
        $assignedA->assignTo($teacher);
        $assignedB->assignTo($teacher);

        $response = $this->actingAs($teacher)->getJson('/api/v1/pupils');

        $response->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($assignedA->id, $ids);
        $this->assertContains($assignedB->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    public function test_teacher_without_school_membership_does_not_see_assigned_pupil_on_index(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher);

        $teacher->schools()->detach($school->id);

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);
    }

    public function test_support_staff_index_and_show_match_teacher_assignment_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $term = NeedTerm::factory()->create();
        $assigned = Pupil::factory()->forSchool($school)->withPrimaryNeed($term, 'Focus notes')->create();
        $other = Pupil::factory()->forSchool($school)->create();
        $assigned->assignTo($support);

        $this->actingAs($support)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);

        $this->actingAs($support)->getJson('/api/v1/pupils/'.$assigned->id)
            ->assertOk()
            ->assertJsonPath('data.id', $assigned->id)
            ->assertJsonPath('data.primary_need.id', $term->id)
            ->assertJsonPath('data.primary_need.notes', 'Focus notes');

        $this->assertForbidden(
            $this->actingAs($support)->getJson('/api/v1/pupils/'.$other->id)
        );
    }

    public function test_teacher_can_show_assigned_pupil_including_needs(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $term = NeedTerm::factory()->create();
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($term)->create();
        $pupil->assignTo($teacher);

        $this->actingAs($teacher)->getJson('/api/v1/pupils/'.$pupil->id)
            ->assertOk()
            ->assertJsonPath('data.id', $pupil->id)
            ->assertJsonPath('data.primary_need.code', $term->code);
    }

    public function test_school_leader_still_sees_school_scoped_pupils_after_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create(['name' => 'Assigned']);
        $schoolB = School::factory()->forTenant($tenant)->create(['name' => 'Other']);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create(['family_name' => 'Assigned']);
        $pupilB = Pupil::factory()->forSchool($schoolB)->create(['family_name' => 'Hidden']);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($schoolA->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($schoolA->id);
        $pupilA->assignTo($teacher);

        $this->actingAs($leader)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pupilA->id)
            ->assertJsonMissing(['id' => $pupilB->id]);

        $this->actingAs($leader)->getJson('/api/v1/pupils/'.$pupilA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $pupilA->id);
    }

    public function test_reassign_updates_labels_without_wiping_connector_source(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher, [
            'class_label' => 'Old',
            'cohort_label' => 'Cohort A',
            'source' => AssignmentSource::Connector->value,
        ]);

        $this->actingAs($senco)->postJson('/api/v1/pupils/'.$pupil->id.'/assignments', [
            'user_id' => $teacher->id,
            'class_label' => '7B',
            'cohort_label' => 'Wave 2',
        ])->assertOk();

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
            'class_label' => '7B',
            'cohort_label' => 'Wave 2',
            'source' => AssignmentSource::Connector->value,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilAssignmentUpdated)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(AssignmentSource::Connector->value, $audit->metadata['source']);
        $this->assertSame('7B', $audit->metadata['class_label']);
    }

    public function test_unassign_removes_pupil_from_teacher_index_and_audits(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $pupil->assignTo($teacher);

        $this->actingAs($senco)
            ->deleteJson('/api/v1/pupils/'.$pupil->id.'/assignments/'.$teacher->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacher->id,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::PupilAssignmentUpdated)
            ->where('resource_id', $pupil->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($tenant->id, $audit->tenant_id);
        $this->assertSame($senco->id, $audit->user_id);
        $this->assertSame([
            'action' => 'unassigned',
            'assigned_user_id' => $teacher->id,
        ], $audit->metadata);

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertForbidden(
            $this->actingAs($teacher)->getJson('/api/v1/pupils/'.$pupil->id)
        );
    }

    public function test_soft_deleted_assigned_pupils_are_omitted_from_teacher_index(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $active = Pupil::factory()->forSchool($school)->create(['family_name' => 'Active']);
        $left = Pupil::factory()->forSchool($school)->create(['family_name' => 'Left']);
        $active->assignTo($teacher);
        $left->assignTo($teacher);
        $left->delete();

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $left->id,
            'user_id' => $teacher->id,
        ]);

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['id' => $left->id]);
    }

    public function test_cross_tenant_assignment_target_returns_404(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $pupilB = Pupil::factory()->forSchool($schoolB)->create([
            'given_name' => 'Secret',
        ]);
        $teacherB = User::factory()->forTenant($tenantB)->teacher()->create();
        $teacherB->schools()->attach($schoolB->id);

        $response = $this->actingAs($adminA)->postJson('/api/v1/pupils/'.$pupilB->id.'/assignments', [
            'user_id' => $teacherB->id,
        ]);

        $response->assertNotFound()
            ->assertJsonMissing(['given_name' => 'Secret']);

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupilB->id,
            'user_id' => $teacherB->id,
        ]);
    }

    public function test_cross_tenant_assignee_id_is_rejected(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolA = School::factory()->forTenant($tenantA)->create();
        $schoolB = School::factory()->forTenant($tenantB)->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        $teacherB = User::factory()->forTenant($tenantB)->teacher()->create();
        $teacherB->schools()->attach($schoolB->id);

        $this->actingAs($adminA)->postJson('/api/v1/pupils/'.$pupilA->id.'/assignments', [
            'user_id' => $teacherB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cross_tenant_unassign_target_user_returns_404(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $schoolA = School::factory()->forTenant($tenantA)->create();
        $adminA = User::factory()->forTenant($tenantA)->tenantAdmin()->create();
        $teacherA = User::factory()->forTenant($tenantA)->teacher()->create();
        $teacherA->schools()->attach($schoolA->id);
        $pupilA = Pupil::factory()->forSchool($schoolA)->create();
        $pupilA->assignTo($teacherA);
        $teacherB = User::factory()->forTenant($tenantB)->teacher()->create();

        $this->actingAs($adminA)
            ->deleteJson('/api/v1/pupils/'.$pupilA->id.'/assignments/'.$teacherB->id)
            ->assertNotFound();

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupilA->id,
            'user_id' => $teacherA->id,
        ]);
    }

    public function test_moving_pupil_to_school_prunes_assignees_without_access(): void
    {
        $tenant = Tenant::factory()->create();
        $schoolA = School::factory()->forTenant($tenant)->create();
        $schoolB = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->sync([$schoolA->id, $schoolB->id]);

        $teacherAOnly = User::factory()->forTenant($tenant)->teacher()->create();
        $teacherAOnly->schools()->attach($schoolA->id);

        $teacherBoth = User::factory()->forTenant($tenant)->teacher()->create();
        $teacherBoth->schools()->sync([$schoolA->id, $schoolB->id]);

        $pupil = Pupil::factory()->forSchool($schoolA)->create();
        $pupil->assignTo($teacherAOnly);
        $pupil->assignTo($teacherBoth);

        $this->actingAs($senco)->patchJson('/api/v1/pupils/'.$pupil->id, [
            'school_id' => $schoolB->id,
        ])->assertOk();

        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacherAOnly->id,
        ]);
        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $pupil->id,
            'user_id' => $teacherBoth->id,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonMutatorRoles(): array
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
