<?php

namespace Tests\Feature;

use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCyclePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_and_school_leader_can_view_any_review_cycles(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();

        $this->assertTrue($senco->can('viewAny', ReviewCycle::class));
        $this->assertTrue($leader->can('viewAny', ReviewCycle::class));
        $this->assertFalse($teacher->can('viewAny', ReviewCycle::class));
        $this->assertFalse($support->can('viewAny', ReviewCycle::class));
    }

    public function test_senco_can_create_and_close_in_accessible_school_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $otherCycle = ReviewCycle::factory()->forPupil($otherPupil)->open()->create();

        $this->actingAs($senco);

        $this->assertTrue($senco->can('create', [ReviewCycle::class, $pupil]));
        $this->assertFalse($senco->can('create', [ReviewCycle::class, $otherPupil]));
        $this->assertTrue($senco->can('close', $cycle));
        $this->assertFalse($senco->can('close', $otherCycle));
        $this->assertTrue($senco->can('view', $cycle));
        $this->assertFalse($senco->can('view', $otherCycle));

        $this->assertFalse($leader->can('create', [ReviewCycle::class, $pupil]));
        $this->assertFalse($leader->can('close', $cycle));
        $this->assertTrue($leader->can('view', $cycle));
    }
}
