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
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Sre\SreEvaluator;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\NeedOntologySeeder;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\RelationshipOntologySeeder;
use Database\Seeders\RuleLibrarySeeder;
use Database\Seeders\SettingOntologySeeder;
use Database\Seeders\ThresholdOntologySeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SreEvaluatorTest extends TestCase
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

    public function test_happy_path_writes_four_current_determinations_with_seq_met(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $this->seedHappyPathEvidence($pupil, $teacher);

        $determinations = app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'test-happy');

        $this->assertCount(4, $determinations);
        $this->assertSame(4, $this->determinations()->current()->forPupil($pupil->id)->count());

        $byDimension = $this->currentByDimension($pupil->id);

        $this->assertSame(DeterminationResult::Met, $byDimension[SreDimension::SequentialCompliance->value]->result);
        $this->assertSame('SEQ_DOC_INITIAL', $byDimension[SreDimension::SequentialCompliance->value]->rule?->code);

        $this->assertSame(DeterminationResult::Met, $byDimension[SreDimension::EvidentialSufficiency->value]->result);
        $this->assertSame('EVID_THR_INITIAL', $byDimension[SreDimension::EvidentialSufficiency->value]->rule?->code);

        $this->assertSame(DeterminationResult::Met, $byDimension[SreDimension::Proportionality->value]->result);
        $this->assertSame('PROP_DOC_INITIAL', $byDimension[SreDimension::Proportionality->value]->rule?->code);

        $this->assertSame(DeterminationResult::Met, $byDimension[SreDimension::OutcomeProgression->value]->result);
        $this->assertSame('OUT_DOC_INITIAL', $byDimension[SreDimension::OutcomeProgression->value]->rule?->code);

        foreach ($byDimension as $determination) {
            $this->assertTrue($determination->is_current);
            $this->assertSame($tenant->id, $determination->tenant_id);
            $this->assertSame(PilotOntology::ensurePublishedVersion()->id, $determination->ontology_version_id);
            $this->assertSame(PilotRuleLibrary::ensurePublishedVersion()->id, $determination->rule_library_version_id);
            $this->assertIsArray($determination->reasoning_pathway);
            $this->assertArrayHasKey('rule', $determination->reasoning_pathway);
            $this->assertArrayHasKey('condition_steps', $determination->reasoning_pathway);
            $this->assertArrayHasKey('evaluation_steps', $determination->reasoning_pathway);
            $this->assertArrayHasKey('evidence_ids', $determination->reasoning_pathway);
            $this->assertArrayHasKey('ontology_version_id', $determination->reasoning_pathway);
            $this->assertArrayHasKey('rule_library_version_id', $determination->reasoning_pathway);
            $this->assertSame(
                PilotOntology::ensurePublishedVersion()->id,
                $determination->reasoning_pathway['ontology_version_id'],
            );
            $this->assertSame(
                PilotRuleLibrary::ensurePublishedVersion()->id,
                $determination->reasoning_pathway['rule_library_version_id'],
            );
            $this->assertNotEmpty($determination->reasoning_pathway['evidence_ids']);
            $this->assertNotNull($determination->rule_id);
        }
    }

    public function test_applicable_rule_with_failed_evaluation_yields_unmet_for_sequential_compliance(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $setting = SettingTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'CLASSROOM')
            ->firstOrFail();
        $provision = ProvisionTerm::query()
            ->forVersion(PilotOntology::ensurePublishedVersion()->id)
            ->where('code', 'UNIVERSAL')
            ->firstOrFail();

        // Intervention before Observation — SEQ condition applies, chronological evaluation fails.
        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->intervention($provision)
            ->create([
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHours(3),
            ]);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create([
                'type' => EvidenceType::Observation,
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHours(2),
            ]);

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'test-unmet');

        $seq = $this->currentByDimension($pupil->id)[SreDimension::SequentialCompliance->value];
        $this->assertSame(DeterminationResult::Unmet, $seq->result);
        $this->assertSame('SEQ_DOC_INITIAL', $seq->rule?->code);
        $this->assertNotNull($seq->rule_id);
    }

    public function test_cross_tenant_pupil_mismatch_writes_no_determinations(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $otherTenant = Tenant::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SreEvaluator Pupil does not belong to the given Tenant.');

        try {
            app(SreEvaluator::class)->evaluate($otherTenant->id, $pupil->id, 'cross-tenant');
        } finally {
            $this->assertSame(0, $this->determinations()->forPupil($pupil->id)->count());
        }
    }

    public function test_zero_evidence_yields_insufficient_not_met_for_all_dimensions(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'test-zero');

        $currents = $this->determinations()->current()->forPupil($pupil->id)->get();
        $this->assertCount(4, $currents);

        foreach (SreDimension::cases() as $dimension) {
            $determination = $currents->first(
                fn (Determination $row): bool => $row->dimension === $dimension,
            );
            $this->assertNotNull($determination);
            $this->assertSame(DeterminationResult::Insufficient, $determination->result);
            $this->assertNull($determination->rule_id);
            $this->assertNotSame(DeterminationResult::Met, $determination->result);
        }
    }

    public function test_observation_only_yields_uncovered_when_no_rule_applies(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $setting = SettingTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)->where('code', 'CLASSROOM')->firstOrFail();

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->withSetting($setting)
            ->create([
                'type' => EvidenceType::Observation,
                'lifecycle' => EvidenceLifecycle::Submitted,
                'occurred_at' => now()->subHours(2),
            ]);

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'test-uncovered');

        $byDimension = $this->currentByDimension($pupil->id);

        foreach (SreDimension::cases() as $dimension) {
            $this->assertSame(
                DeterminationResult::Uncovered,
                $byDimension[$dimension->value]->result,
                "Expected Uncovered for [{$dimension->value}]",
            );
            $this->assertNull($byDimension[$dimension->value]->rule_id);
        }
    }

    public function test_identical_inputs_produce_identical_results_and_pathway_shape(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $this->seedHappyPathEvidence($pupil, $teacher);

        $first = app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'determinism-1');
        $firstSnapshot = $this->determinismSnapshot($first);

        $second = app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'determinism-2');
        $secondSnapshot = $this->determinismSnapshot($second);

        $this->assertSame($firstSnapshot, $secondSnapshot);
    }

    public function test_re_run_supersedes_prior_current_determinations(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $this->seedHappyPathEvidence($pupil, $teacher);

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'run-1');
        $firstIds = $this->determinations()->current()->forPupil($pupil->id)->orderBy('dimension')->pluck('id')->all();
        $this->assertCount(4, $firstIds);

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'run-2');
        $secondIds = $this->determinations()->current()->forPupil($pupil->id)->orderBy('dimension')->pluck('id')->all();
        $this->assertCount(4, $secondIds);

        foreach ($firstIds as $id) {
            $this->assertFalse($this->determinations()->findOrFail($id)->is_current);
            $this->assertNotContains($id, $secondIds);
        }

        $this->assertSame(8, $this->determinations()->forPupil($pupil->id)->count());
        $this->assertSame(4, $this->determinations()->current()->forPupil($pupil->id)->count());
    }

    public function test_draft_evidence_is_excluded_from_snapshot(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
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
            ->draft()
            ->create([
                'occurred_at' => now()->subHours(3),
            ]);

        EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($teacher)
            ->intervention($provision)
            ->draft()
            ->create([
                'occurred_at' => now()->subHours(2),
            ]);

        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'drafts-only');

        $currents = $this->determinations()->current()->forPupil($pupil->id)->get();
        $this->assertCount(4, $currents);
        foreach ($currents as $determination) {
            $this->assertSame(DeterminationResult::Insufficient, $determination->result);
        }
    }

    /**
     * Domain\Sre / job paths run without Auth; bypass tenant global scope in assertions.
     *
     * @return Builder<Determination>
     */
    private function determinations(): Builder
    {
        return Determination::withoutGlobalScope('tenant');
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

    /**
     * @return array<string, Determination>
     */
    private function currentByDimension(string $pupilId): array
    {
        return $this->determinations()
            ->current()
            ->forPupil($pupilId)
            ->with('rule')
            ->get()
            ->keyBy(fn (Determination $determination): string => $determination->dimension->value)
            ->all();
    }

    /**
     * @param  Collection<int, Determination>  $determinations
     * @return list<array{dimension: string, result: string, rule_code: ?string, pathway: array<string, mixed>}>
     */
    private function determinismSnapshot(Collection $determinations): array
    {
        return $determinations
            ->sortBy(fn (Determination $determination): string => $determination->dimension->value)
            ->values()
            ->map(function (Determination $determination): array {
                $pathway = $determination->reasoning_pathway ?? [];
                unset($pathway['notes']['prior_skipped_rules']);

                return [
                    'dimension' => $determination->dimension->value,
                    'result' => $determination->result->value,
                    'rule_code' => $determination->rule?->code ?? ($pathway['rule']['code'] ?? null),
                    'pathway' => [
                        'dimension' => $pathway['dimension'] ?? null,
                        'result' => $pathway['result'] ?? null,
                        'ontology_version_id' => $pathway['ontology_version_id'] ?? null,
                        'rule_library_version_id' => $pathway['rule_library_version_id'] ?? null,
                        'rule_code' => $pathway['rule']['code'] ?? null,
                        'condition_steps' => $pathway['condition_steps'] ?? [],
                        'evaluation_steps' => $pathway['evaluation_steps'] ?? [],
                        'evidence_ids' => $pathway['evidence_ids'] ?? [],
                    ],
                ];
            })
            ->all();
    }
}
