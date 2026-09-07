<?php

namespace Tests\Feature;

use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationOutputPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_and_school_leader_can_view_any_outputs(): void
    {
        $tenant = Tenant::factory()->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->assertTrue($senco->can('viewAny', DocumentationOutput::class));
        $this->assertTrue($leader->can('viewAny', DocumentationOutput::class));
        $this->assertFalse($teacher->can('viewAny', DocumentationOutput::class));
        $this->assertFalse($admin->can('viewAny', DocumentationOutput::class));
    }

    public function test_senco_can_create_for_accessible_school_only(): void
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

        $this->actingAs($senco);

        $this->assertTrue($senco->can('create', [DocumentationOutput::class, $pupil]));
        $this->assertFalse($senco->can('create', [DocumentationOutput::class, $otherPupil]));
        $this->assertFalse($leader->can('create', [DocumentationOutput::class, $pupil]));
    }

    public function test_senco_and_leader_can_view_output_in_accessible_school_only(): void
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
        $output = DocumentationOutput::factory()->forCycle($cycle)->confirmedBy($senco)->create();
        $otherOutput = DocumentationOutput::factory()->forCycle($otherCycle)->confirmedBy($senco)->create();

        $this->actingAs($senco);
        $this->assertTrue($senco->can('view', $output));
        $this->assertFalse($senco->can('view', $otherOutput));

        $this->actingAs($leader);
        $this->assertTrue($leader->can('view', $output));
        $this->assertFalse($leader->can('view', $otherOutput));
    }
}
