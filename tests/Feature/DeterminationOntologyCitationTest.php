<?php

namespace Tests\Feature;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\NeedOntologySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterminationOntologyCitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_ontology_pin_change_does_not_rewrite_stored_determination_version_id(): void
    {
        $this->seed(NeedOntologySeeder::class);

        $v1 = PilotOntology::ensurePublishedVersion();
        $tenant = Tenant::factory()->create();
        $tenant->forceFill([
            'current_ontology_version_id' => $v1->id,
        ])->save();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($teacher);

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forOntologyVersion($v1)
            ->create();

        $this->assertSame($v1->id, $determination->fresh()->ontology_version_id);

        $v2 = OntologyVersion::factory()->published()->create([
            'code' => 'pilot-ontology-stub-v2',
            'label' => 'Pilot Ontology stub v2',
        ]);

        $tenant->forceFill([
            'current_ontology_version_id' => $v2->id,
        ])->save();

        $this->assertSame($v2->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($v2->id, $tenant->fresh()->effectiveOntologyVersion()?->id);

        $cited = Determination::query()->findOrFail($determination->id);

        $this->assertSame($v1->id, $cited->ontology_version_id);
        $this->assertNotSame($v2->id, $cited->ontology_version_id);
    }

    public function test_deleting_cited_ontology_version_is_restricted_and_determination_remains(): void
    {
        $this->seed(NeedOntologySeeder::class);

        $version = OntologyVersion::factory()->published()->create([
            'code' => 'cited-only-v1',
        ]);
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($teacher);

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forOntologyVersion($version)
            ->create();

        try {
            $version->delete();
            $this->fail('Expected deleting a cited OntologyVersion to fail with restrictOnDelete.');
        } catch (QueryException) {
            // SQLite / MySQL foreign key restriction
        }

        $this->assertDatabaseHas('ontology_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('determinations', [
            'id' => $determination->id,
            'ontology_version_id' => $version->id,
        ]);
    }
}
