<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_lists_open_gaps_with_nested_determination_pathway(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'given_name' => 'Maya',
            'family_name' => 'Okonkwo',
        ]);

        $ruleLibrary = PilotRuleLibrary::ensurePublishedVersion();
        $rule = Rule::query()
            ->where('rule_library_version_id', $ruleLibrary->id)
            ->where('dimension', SreDimension::SequentialCompliance->value)
            ->first();

        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Unmet)
            ->forRuleLibraryVersion($ruleLibrary)
            ->current()
            ->create([
                'rule_id' => $rule?->id,
                'reasoning_pathway' => [
                    'dimension' => SreDimension::SequentialCompliance->value,
                    'rule' => $rule === null ? null : [
                        'id' => $rule->id,
                        'code' => $rule->code,
                        'label' => $rule->label,
                        'version_id' => $ruleLibrary->id,
                    ],
                    'condition_steps' => [['type' => 'evidence_count', 'status' => 'passed', 'detail' => 'Has evidence']],
                    'evaluation_steps' => [['type' => 'threshold', 'status' => 'failed', 'detail' => 'Below threshold']],
                    'evidence_ids' => ['ev_1'],
                    'result' => DeterminationResult::Unmet->value,
                ],
            ]);

        $gap = Gap::factory()->forDetermination($determination)->open()->create();

        Gap::factory()
            ->forPupil($pupil)
            ->closed()
            ->create([
                'dimension' => SreDimension::Proportionality,
                'result' => DeterminationResult::Unmet,
            ]);

        $response = $this->actingAs($senco)->getJson('/api/v1/gaps');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $gap->id)
            ->assertJsonPath('data.0.pupil.given_name', 'Maya')
            ->assertJsonPath('data.0.dimension', SreDimension::SequentialCompliance->value)
            ->assertJsonPath('data.0.result', DeterminationResult::Unmet->value)
            ->assertJsonPath('data.0.result_label', 'Not met')
            ->assertJsonPath('data.0.determination.id', $determination->id)
            ->assertJsonPath('data.0.determination.reasoning_pathway.result', DeterminationResult::Unmet->value)
            ->assertJsonPath('data.0.determination.rule_library_version.label', $ruleLibrary->label);

        $this->assertStringNotContainsString('confidence', strtolower($response->getContent()));
        $this->assertStringNotContainsString('diagnos', strtolower($response->getContent()));
    }

    public function test_senco_only_sees_gaps_for_accessible_schools(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();

        Gap::factory()->forPupil($pupil)->open()->create([
            'dimension' => SreDimension::EvidentialSufficiency,
            'result' => DeterminationResult::Insufficient,
        ]);
        Gap::factory()->forPupil($otherPupil)->open()->create([
            'dimension' => SreDimension::Proportionality,
            'result' => DeterminationResult::Unmet,
        ]);

        $this->actingAs($senco)
            ->getJson('/api/v1/gaps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pupil_id', $pupil->id);
    }

    public function test_empty_open_gaps_returns_empty_data(): void
    {
        [, , $senco] = $this->tenantSchoolAndSenco();

        $this->actingAs($senco)
            ->getJson('/api/v1/gaps')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_teacher_and_school_leader_cannot_list_gaps(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        Gap::factory()->forPupil($pupil)->open()->create();

        $this->assertForbidden($this->actingAs($teacher)->getJson('/api/v1/gaps'));
        $this->assertForbidden($this->actingAs($leader)->getJson('/api/v1/gaps'));
    }

    public function test_guest_cannot_list_gaps(): void
    {
        $this->getJson('/api/v1/gaps')->assertUnauthorized();
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
