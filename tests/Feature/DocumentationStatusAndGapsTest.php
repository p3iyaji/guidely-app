<?php

namespace Tests\Feature;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Sre\DocumentationStatusDeriver;
use App\Domain\Sre\Gap;
use App\Domain\Sre\GapMaterialiser;
use App\Domain\Sre\SreEvaluator;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Database\Seeders\NeedOntologySeeder;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\RelationshipOntologySeeder;
use Database\Seeders\RuleLibrarySeeder;
use Database\Seeders\SettingOntologySeeder;
use Database\Seeders\ThresholdOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DocumentationStatusAndGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            NeedOntologySeeder::class,
            SettingOntologySeeder::class,
            ProvisionOntologySeeder::class,
            ThresholdOntologySeeder::class,
            RelationshipOntologySeeder::class,
            RuleLibrarySeeder::class,
        ]);
    }

    public function test_submitted_observation_sets_evaluating_and_enqueues_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $setting = SettingTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'CLASSROOM')
            ->firstOrFail();

        $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Observed difficulty with turn-taking.',
        ])->assertCreated();

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->documentation_status);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_submitted';
        });
    }

    public function test_submitted_intervention_sets_evaluating_and_enqueues_sre(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $provision = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        $this->actingAs($teacher)->postJson('/api/v1/interventions', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'provision_term_id' => $provision->id,
            'body' => 'Used visual timetable before the transition.',
        ])->assertCreated();

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->documentation_status);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_submitted';
        });
    }

    public function test_draft_observation_does_not_enqueue_or_set_evaluating(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $setting = SettingTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'CLASSROOM')
            ->firstOrFail();

        $this->actingAs($teacher)->postJson('/api/v1/observations', [
            'pupil_id' => $pupil->id,
            'occurred_at' => now()->subHour()->utc()->toIso8601String(),
            'setting_term_id' => $setting->id,
            'body' => 'Draft note only.',
            'lifecycle' => 'draft',
        ])->assertCreated();

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::NotStarted, $pupil->documentation_status);
        Queue::assertNothingPushed();
    }

    public function test_job_success_with_all_met_sets_ready_and_clears_gaps(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $this->seedHappyPathEvidence($pupil, $teacher);

        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'evidence.submitted');
        $job->handle(
            app(SreEvaluator::class),
            app(GapMaterialiser::class),
            app(DocumentationStatusDeriver::class),
        );

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Ready, $pupil->documentation_status);
        $this->assertSame(0, Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->count());
    }

    public function test_job_success_with_unmet_materialises_gaps_and_sets_gaps_status(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'evidence.submitted');
        $job->handle(
            app(SreEvaluator::class),
            app(GapMaterialiser::class),
            app(DocumentationStatusDeriver::class),
        );

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Gaps, $pupil->documentation_status);

        $openGaps = Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->get();
        $this->assertNotEmpty($openGaps);
        $this->assertTrue(
            $openGaps->every(
                fn (Gap $gap): bool => in_array($gap->result, GapMaterialiser::GAP_OPENING_RESULTS, true),
            ),
        );
    }

    public function test_job_success_with_all_uncovered_sets_uncovered_without_gaps(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();

        foreach (SreDimension::cases() as $dimension) {
            Determination::factory()
                ->forPupil($pupil)
                ->forDimension($dimension)
                ->withResult(DeterminationResult::Uncovered)
                ->current()
                ->create();
        }

        $stub = new class extends SreEvaluator
        {
            public function __construct() {}

            public function evaluate(string $tenantId, string $pupilId, string $reason = 'manual'): Collection
            {
                return Determination::withoutGlobalScope('tenant')
                    ->current()
                    ->forPupil($pupilId)
                    ->get();
            }
        };

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'stub.uncovered');
        $job->handle($stub, app(GapMaterialiser::class), app(DocumentationStatusDeriver::class));

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Uncovered, $pupil->documentation_status);
        $this->assertSame(0, Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->count());
    }

    public function test_job_failure_does_not_leave_pupil_stuck_evaluating(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();

        $stub = new class extends SreEvaluator
        {
            public function __construct() {}

            public function evaluate(string $tenantId, string $pupilId, string $reason = 'manual'): Collection
            {
                throw new RuntimeException('evaluator boom');
            }
        };

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'fail');

        try {
            $job->handle($stub, app(GapMaterialiser::class), app(DocumentationStatusDeriver::class));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $pupil->refresh();
        $this->assertNotSame(DocumentationStatus::Evaluating, $pupil->documentation_status);
        $this->assertSame(DocumentationStatus::NotStarted, $pupil->documentation_status);
    }

    public function test_materialise_insufficient_and_escalated_results_open_gaps_status(): void
    {
        [, $pupil] = $this->tenantPupilAndTeacher();

        $insufficient = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::EvidentialSufficiency)
            ->withResult(DeterminationResult::Insufficient)
            ->current()
            ->create();

        $escalated = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::Proportionality)
            ->withResult(DeterminationResult::Escalated)
            ->current()
            ->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Met)
            ->current()
            ->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::OutcomeProgression)
            ->withResult(DeterminationResult::ReviewRequired)
            ->current()
            ->create();

        $currents = Determination::withoutGlobalScope('tenant')->current()->forPupil($pupil->id)->get();
        $gaps = app(GapMaterialiser::class)->materialise($pupil, $currents);
        $status = app(DocumentationStatusDeriver::class)->apply($pupil, $currents);

        $this->assertSame(DocumentationStatus::Gaps, $status);
        $this->assertCount(3, $gaps);
        $this->assertDatabaseHas('gaps', [
            'pupil_id' => $pupil->id,
            'determination_id' => $insufficient->id,
            'result' => DeterminationResult::Insufficient->value,
            'is_open' => true,
        ]);
        $this->assertDatabaseHas('gaps', [
            'pupil_id' => $pupil->id,
            'determination_id' => $escalated->id,
            'result' => DeterminationResult::Escalated->value,
            'is_open' => true,
        ]);
        $this->assertDatabaseHas('gaps', [
            'pupil_id' => $pupil->id,
            'result' => DeterminationResult::ReviewRequired->value,
            'is_open' => true,
        ]);
    }

    public function test_derive_with_provided_currents_ignores_stale_open_gap_rows(): void
    {
        [, $pupil] = $this->tenantPupilAndTeacher();

        foreach (SreDimension::cases() as $dimension) {
            Determination::factory()
                ->forPupil($pupil)
                ->forDimension($dimension)
                ->withResult(DeterminationResult::Met)
                ->current()
                ->create();
        }

        Gap::factory()->forPupil($pupil)->open()->create([
            'dimension' => SreDimension::SequentialCompliance,
            'result' => DeterminationResult::Unmet,
        ]);

        $currents = Determination::withoutGlobalScope('tenant')->current()->forPupil($pupil->id)->get();
        $status = app(DocumentationStatusDeriver::class)->derive($pupil, $currents);

        $this->assertSame(DocumentationStatus::Ready, $status);
    }

    public function test_mixed_unmet_and_uncovered_prefers_gaps_status(): void
    {
        [, $pupil] = $this->tenantPupilAndTeacher();

        $unmet = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Unmet)
            ->current()
            ->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::EvidentialSufficiency)
            ->withResult(DeterminationResult::Uncovered)
            ->current()
            ->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::Proportionality)
            ->withResult(DeterminationResult::Met)
            ->current()
            ->create();

        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::OutcomeProgression)
            ->withResult(DeterminationResult::Met)
            ->current()
            ->create();

        $currents = Determination::withoutGlobalScope('tenant')->current()->forPupil($pupil->id)->get();
        app(GapMaterialiser::class)->materialise($pupil, $currents);
        $status = app(DocumentationStatusDeriver::class)->apply($pupil, $currents);

        $this->assertSame(DocumentationStatus::Gaps, $status);
        $this->assertDatabaseHas('gaps', [
            'pupil_id' => $pupil->id,
            'determination_id' => $unmet->id,
            'is_open' => true,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: Pupil, 2: User}
     */
    private function tenantPupilAndTeacher(): array
    {
        $tenant = Tenant::factory()->create();
        $tenant->forceFill([
            'current_ontology_version_id' => PilotOntology::ensurePublishedVersion()->id,
            'current_rule_library_version_id' => PilotRuleLibrary::ensurePublishedVersion()->id,
        ])->save();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        $need = NeedTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)->where('code', 'CI')->firstOrFail();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'primary_need_term_id' => $need->id,
        ]);
        $pupil->assignTo($teacher);

        return [$tenant, $pupil, $teacher];
    }

    private function seedHappyPathEvidence(Pupil $pupil, User $teacher): void
    {
        $setting = SettingTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'CLASSROOM')
            ->firstOrFail();
        $provision = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create([
                'type' => EvidenceType::Observation,
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHours(3),
                'body' => 'Observed difficulty with turn-taking.',
            ]);

        $intervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->intervention($provision)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHours(2),
                'body' => 'Visual timetable strategy.',
            ]);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->response($intervention)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHour(),
                'body' => 'Pupil engaged with the timetable.',
            ]);
    }
}
