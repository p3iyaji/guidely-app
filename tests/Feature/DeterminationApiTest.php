<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeterminationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_lists_current_determinations_with_pathway_and_humanised_uncovered(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $ruleLibrary = PilotRuleLibrary::ensurePublishedVersion();

        $current = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::OutcomeProgression)
            ->withResult(DeterminationResult::Uncovered)
            ->forRuleLibraryVersion($ruleLibrary)
            ->current()
            ->create([
                'reasoning_pathway' => [
                    'dimension' => SreDimension::OutcomeProgression->value,
                    'rule' => null,
                    'condition_steps' => [[
                        'type' => 'applicable_rule',
                        'status' => 'failed',
                        'detail' => 'No applicable Rule for this dimension.',
                    ]],
                    'evaluation_steps' => [],
                    'evidence_ids' => [],
                    'result' => DeterminationResult::Uncovered->value,
                ],
            ]);

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::OutcomeProgression)
            ->withResult(DeterminationResult::Met)
            ->forRuleLibraryVersion($ruleLibrary)
            ->create([
                'is_current' => false,
                'reasoning_pathway' => ['result' => DeterminationResult::Met->value],
            ]);

        $response = $this->actingAs($senco)
            ->getJson("/api/v1/pupils/{$pupil->id}/determinations?current=1");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $current->id)
            ->assertJsonPath('data.0.result', DeterminationResult::Uncovered->value)
            ->assertJsonPath('data.0.result_label', 'Uncovered')
            ->assertJsonPath('data.0.rule_library_version.label', $ruleLibrary->label)
            ->assertJsonPath('data.0.reasoning_pathway.result', DeterminationResult::Uncovered->value);

        $this->assertStringNotContainsString('confidence', strtolower($response->getContent()));
        $this->assertStringNotContainsString('diagnos', strtolower($response->getContent()));
    }

    public function test_school_leader_can_list_current_determinations_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::Proportionality)
            ->withResult(DeterminationResult::Insufficient)
            ->current()
            ->create([
                'reasoning_pathway' => [
                    'result' => DeterminationResult::Insufficient->value,
                    'condition_steps' => [],
                    'evaluation_steps' => [],
                    'evidence_ids' => [],
                ],
            ]);

        $this->actingAs($leader)
            ->getJson("/api/v1/pupils/{$pupil->id}/determinations?current=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result_label', 'Insufficient');
    }

    public function test_teacher_cannot_list_determinations(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();

        Determination::factory()
            ->forPupil($pupil)
            ->current()
            ->create();

        $this->assertForbidden(
            $this->actingAs($teacher)->getJson("/api/v1/pupils/{$pupil->id}/determinations?current=1")
        );
    }

    public function test_guest_cannot_list_determinations(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();

        $this->getJson("/api/v1/pupils/{$pupil->id}/determinations?current=1")
            ->assertUnauthorized();
    }

    /**
     * @param  TestResponse  $response
     */
    private function assertForbidden($response): void
    {
        $response->assertForbidden()
            ->assertJson([
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
}
