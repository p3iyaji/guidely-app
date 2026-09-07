<?php

namespace Tests\Feature;

use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_senco_can_view_any_gaps(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();

        $this->assertTrue($senco->can('viewAny', Gap::class));
        $this->assertFalse($leader->can('viewAny', Gap::class));
        $this->assertFalse($teacher->can('viewAny', Gap::class));
    }

    public function test_senco_can_view_gap_in_accessible_school_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $pupil = Pupil::factory()->forSchool($school)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();

        $gap = Gap::factory()->forPupil($pupil)->open()->create();
        $otherGap = Gap::factory()->forPupil($otherPupil)->open()->create();

        $this->actingAs($senco);

        $this->assertTrue($senco->can('view', $gap));
        $this->assertFalse($senco->can('view', $otherGap));
    }
}
