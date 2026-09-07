<?php

namespace Tests\Feature;

use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\RuleLibrarySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterminationRuleLibraryCitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_rule_library_pin_change_does_not_rewrite_stored_determination_version_id(): void
    {
        $this->seed(RuleLibrarySeeder::class);

        $v1 = PilotRuleLibrary::ensurePublishedVersion();
        $tenant = Tenant::factory()->create();
        $tenant->forceFill([
            'current_rule_library_version_id' => $v1->id,
        ])->save();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($teacher);

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forRuleLibraryVersion($v1)
            ->create();

        $this->assertSame($v1->id, $determination->fresh()->rule_library_version_id);

        $v2 = RuleLibraryVersion::factory()->published()->create([
            'code' => 'pilot-rule-library-stub-v2',
            'label' => 'Pilot Rule Library stub v2',
        ]);

        $tenant->forceFill([
            'current_rule_library_version_id' => $v2->id,
        ])->save();

        $this->assertSame($v2->id, $tenant->fresh()->current_rule_library_version_id);
        $this->assertSame($v2->id, $tenant->fresh()->effectiveRuleLibraryVersion()?->id);

        $cited = Determination::query()->findOrFail($determination->id);

        $this->assertSame($v1->id, $cited->rule_library_version_id);
        $this->assertNotSame($v2->id, $cited->rule_library_version_id);
    }

    public function test_deleting_cited_rule_library_version_is_restricted_and_determination_remains(): void
    {
        $this->seed(RuleLibrarySeeder::class);

        $version = RuleLibraryVersion::factory()->published()->create([
            'code' => 'cited-rule-library-only-v1',
        ]);
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->actingAs($teacher);

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forRuleLibraryVersion($version)
            ->create();

        try {
            $version->delete();
            $this->fail('Expected deleting a cited RuleLibraryVersion to fail with restrictOnDelete.');
        } catch (QueryException) {
            // SQLite / MySQL foreign key restriction
        }

        $this->assertDatabaseHas('rule_library_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('determinations', [
            'id' => $determination->id,
            'rule_library_version_id' => $version->id,
        ]);
    }
}
