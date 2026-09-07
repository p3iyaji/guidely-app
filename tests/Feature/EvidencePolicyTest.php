<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class EvidencePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingOntologySeeder::class);
    }

    public function test_capture_evidence_gate_allows_teacher_support_and_senco(): void
    {
        $tenant = Tenant::factory()->create();

        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();

        $this->actingAs($teacher);
        $this->assertTrue(Gate::allows('capture-evidence'));
        $this->assertTrue($teacher->can('create', EvidenceRecord::class));

        $this->actingAs($support);
        $this->assertTrue(Gate::allows('capture-evidence'));

        $this->actingAs($senco);
        $this->assertTrue(Gate::allows('capture-evidence'));

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('capture-evidence'));
        $this->assertFalse(Gate::allows('view-evidence'));

        $this->actingAs($leader);
        $this->assertFalse(Gate::allows('capture-evidence'));
        $this->assertTrue(Gate::allows('view-evidence'));
    }

    public function test_view_evidence_gate_allows_evidence_base_roles(): void
    {
        $tenant = Tenant::factory()->create();

        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($teacher);
        $this->assertTrue(Gate::allows('view-evidence'));

        $this->actingAs($support);
        $this->assertTrue(Gate::allows('view-evidence'));

        $this->actingAs($senco);
        $this->assertTrue(Gate::allows('view-evidence'));

        $this->actingAs($leader);
        $this->assertTrue(Gate::allows('view-evidence'));

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('view-evidence'));
    }

    public function test_create_for_pupil_requires_assignment_for_teacher(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $this->actingAs($teacher);

        $assigned = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $other = Pupil::factory()->forSchool($school)->create();

        $this->assertTrue($teacher->can('createForPupil', [EvidenceRecord::class, $assigned]));
        $this->assertFalse($teacher->can('createForPupil', [EvidenceRecord::class, $other]));
    }

    public function test_senco_can_create_for_school_pupil_without_assignment(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $this->actingAs($senco);

        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->assertTrue($senco->can('createForPupil', [EvidenceRecord::class, $pupil]));
    }

    public function test_submitted_evidence_view_follows_role_and_assignment_matrix(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $assigned = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $unassigned = Pupil::factory()->forSchool($school)->create();

        $assignedRecord = EvidenceRecord::factory()
            ->forPupil($assigned)
            ->authoredBy($teacher)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'setting_term_id' => null,
                'body' => 'Assigned submitted',
            ]);

        $unassignedRecord = EvidenceRecord::factory()
            ->forPupil($unassigned)
            ->authoredBy($senco)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'setting_term_id' => null,
                'body' => 'Unassigned submitted',
            ]);

        $this->actingAs($teacher);
        $assignedRecord->unsetRelation('pupil');
        $unassignedRecord->unsetRelation('pupil');
        $assignedRecord->load('pupil.school');
        $unassignedRecord->load('pupil.school');

        $this->assertTrue($teacher->can('view', $assignedRecord));
        $this->assertFalse($teacher->can('view', $unassignedRecord));
        $this->assertTrue($teacher->can('listForPupil', [EvidenceRecord::class, $assigned]));
        $this->assertFalse($teacher->can('listForPupil', [EvidenceRecord::class, $unassigned]));

        $this->actingAs($leader);
        $this->assertTrue($leader->can('view', $assignedRecord));
        $this->assertTrue($leader->can('listForPupil', [EvidenceRecord::class, $assigned]));
        $this->assertFalse($leader->can('create', EvidenceRecord::class));

        $this->actingAs($senco);
        $this->assertTrue($senco->can('view', $unassignedRecord));

        $this->actingAs($admin);
        $this->assertFalse($admin->can('view', $assignedRecord));
        $this->assertFalse($admin->can('listForPupil', [EvidenceRecord::class, $assigned]));
    }

    public function test_draft_view_remains_author_or_senco_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $otherTeacher = User::factory()->forTenant($tenant)->teacher()->create();
        $otherTeacher->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $pupil->assignedUsers()->attach($otherTeacher->id);

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => null,
            ]);

        $this->actingAs($teacher);
        $draft->unsetRelation('pupil');
        $draft->load('pupil.school');
        $this->assertTrue($teacher->can('view', $draft));

        $this->actingAs($senco);
        $this->assertTrue($senco->can('view', $draft));

        $this->actingAs($otherTeacher);
        $this->assertFalse($otherTeacher->can('view', $draft));

        $this->actingAs($leader);
        $this->assertFalse($leader->can('view', $draft));
    }

    public function test_amend_allows_author_or_senco_and_denies_leader(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $otherTeacher = User::factory()->forTenant($tenant)->teacher()->create();
        $otherTeacher->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $pupil->assignedUsers()->attach($otherTeacher->id);

        $submitted = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'setting_term_id' => null,
                'body' => 'Submitted for amend policy',
            ]);

        $draft = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->draft()
            ->create([
                'setting_term_id' => null,
                'body' => null,
            ]);

        $submitted->load('pupil.school');
        $draft->load('pupil.school');

        $this->actingAs($teacher);
        $this->assertTrue($teacher->can('amend', $submitted));
        $this->assertFalse($teacher->can('amend', $draft));

        $this->actingAs($senco);
        $this->assertTrue($senco->can('amend', $submitted));

        $this->actingAs($otherTeacher);
        $this->assertFalse($otherTeacher->can('amend', $submitted));

        $this->actingAs($leader);
        $this->assertFalse($leader->can('amend', $submitted));
    }

    public function test_support_staff_author_assigned_can_amend(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->assignedTo($support)->create();

        $submitted = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($support)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'setting_term_id' => null,
                'body' => 'Support authored submitted',
            ]);

        $submitted->load('pupil.school');

        $this->actingAs($support);
        $this->assertTrue($support->can('amend', $submitted));
    }

    public function test_create_review_note_is_senco_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();

        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $pupil->assignedUsers()->attach($support->id);
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();

        $this->actingAs($senco);
        $this->assertTrue($senco->can('createReviewNote', [EvidenceRecord::class, $pupil]));
        $this->assertFalse($senco->can('createReviewNote', [EvidenceRecord::class, $otherPupil]));

        $this->actingAs($teacher);
        $this->assertFalse($teacher->can('createReviewNote', [EvidenceRecord::class, $pupil]));
        $this->assertTrue($teacher->can('create', EvidenceRecord::class));

        $this->actingAs($leader);
        $this->assertFalse($leader->can('createReviewNote', [EvidenceRecord::class, $pupil]));

        $this->actingAs($support);
        $this->assertFalse($support->can('createReviewNote', [EvidenceRecord::class, $pupil]));
    }

    public function test_amend_denies_review_notes(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $reviewNote = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->reviewNote()
            ->create(['body' => 'Commentary']);

        $reviewNote->load('pupil.school');

        $this->actingAs($senco);
        $this->assertFalse($senco->can('amend', $reviewNote));
    }
}
