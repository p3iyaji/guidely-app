<?php

namespace Tests\Feature;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterminationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_and_school_leader_can_list_determinations_for_school_pupil(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        $this->actingAs($senco);
        $this->assertTrue($senco->can('listForPupil', [Determination::class, $pupil]));

        $this->actingAs($leader);
        $this->assertTrue($leader->can('listForPupil', [Determination::class, $pupil]));

        $this->actingAs($teacher);
        $this->assertFalse($teacher->can('listForPupil', [Determination::class, $pupil]));
    }

    public function test_school_leader_cannot_list_determinations_outside_accessible_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($otherSchool)->create();

        $this->actingAs($leader);
        $this->assertFalse($leader->can('listForPupil', [Determination::class, $pupil]));
    }

    public function test_view_follows_pupil_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Uncovered)
            ->current()
            ->create();

        $this->actingAs($senco);
        $this->assertTrue($senco->can('view', $determination));
    }

    public function test_senco_and_school_leader_can_override_in_school_determination(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Unmet)
            ->current()
            ->create();

        $this->actingAs($senco);
        $this->assertTrue($senco->can('override', $determination));

        $this->actingAs($leader);
        $this->assertTrue($leader->can('override', $determination));

        $this->actingAs($teacher);
        $this->assertFalse($teacher->can('override', $determination));

        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $this->actingAs($support);
        $this->assertFalse($support->can('override', $determination));
    }

    public function test_senco_cannot_override_determination_outside_accessible_schools(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($otherSchool)->create();

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Unmet)
            ->current()
            ->create();

        $this->actingAs($senco);
        $this->assertFalse($senco->can('override', $determination));
    }
}
