<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class EvidencePolicyTest extends TestCase
{
    use RefreshDatabase;

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
}
