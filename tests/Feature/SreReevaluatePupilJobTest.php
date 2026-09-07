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
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SreReevaluatePupilJobTest extends TestCase
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

    public function test_job_handle_writes_current_determinations_via_domain_sre(): void
    {
        [$tenant, $pupil, $teacher] = $this->tenantPupilAndTeacher();
        $this->seedHappyPathEvidence($pupil, $teacher);

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'evidence.submitted');
        $job->handle(
            app(SreEvaluator::class),
            app(GapMaterialiser::class),
            app(DocumentationStatusDeriver::class),
        );

        $currents = Determination::withoutGlobalScope('tenant')->current()->forPupil($pupil->id)->get();
        $this->assertCount(4, $currents);

        $seq = $currents->first(
            fn (Determination $row): bool => $row->dimension === SreDimension::SequentialCompliance,
        );
        $this->assertNotNull($seq);
        $this->assertSame(DeterminationResult::Met, $seq->result);
        $this->assertSame($tenant->id, $seq->tenant_id);
        $this->assertNotNull($seq->reasoning_pathway);
        $this->assertSame(
            PilotOntology::ensurePublishedVersion()->id,
            $seq->reasoning_pathway['ontology_version_id'] ?? null,
        );
        $this->assertSame(
            PilotRuleLibrary::ensurePublishedVersion()->id,
            $seq->reasoning_pathway['rule_library_version_id'] ?? null,
        );

        $outcome = $currents->first(
            fn (Determination $row): bool => $row->dimension === SreDimension::OutcomeProgression,
        );
        $this->assertNotNull($outcome);
        $this->assertSame(DeterminationResult::Met, $outcome->result);
    }

    public function test_job_handle_skips_safely_when_pupil_missing(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create();
        $job = new SreReevaluatePupil($tenant->id, '01MISSINGPUPILID0000000000', 'orphan');
        $job->handle(
            app(SreEvaluator::class),
            app(GapMaterialiser::class),
            app(DocumentationStatusDeriver::class),
        );

        $this->assertSame(0, Determination::withoutGlobalScope('tenant')->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_job_handle_skips_with_warning_on_cross_tenant_mismatch(): void
    {
        Log::spy();

        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();
        $otherTenant = Tenant::factory()->create();

        $job = new SreReevaluatePupil($otherTenant->id, $pupil->id, 'cross-tenant');
        $job->handle(
            app(SreEvaluator::class),
            app(GapMaterialiser::class),
            app(DocumentationStatusDeriver::class),
        );

        $this->assertSame(0, Determination::withoutGlobalScope('tenant')->forPupil($pupil->id)->count());
        Log::shouldHaveReceived('warning')->once();

        $pupil->refresh();
        $this->assertNotSame(DocumentationStatus::Evaluating, $pupil->documentation_status);
    }

    public function test_job_failed_clears_evaluating_when_still_evaluating(): void
    {
        [$tenant, $pupil] = $this->tenantPupilAndTeacher();
        $pupil->forceFill(['documentation_status' => DocumentationStatus::Evaluating])->save();

        $job = new SreReevaluatePupil($tenant->id, $pupil->id, 'failed-hook');
        $job->failed(new RuntimeException('queue exhausted'));

        $pupil->refresh();
        $this->assertNotSame(DocumentationStatus::Evaluating, $pupil->documentation_status);
        $this->assertSame(DocumentationStatus::NotStarted, $pupil->documentation_status);
    }

    public function test_job_handle_rethrows_non_invalid_argument_exceptions(): void
    {
        Log::spy();

        $stub = new class extends SreEvaluator
        {
            public function __construct() {}

            public function evaluate(string $tenantId, string $pupilId, string $reason = 'manual'): Collection
            {
                throw new RuntimeException('evaluator boom');
            }
        };

        $job = new SreReevaluatePupil('tenant', 'pupil', 'fail');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('evaluator boom');

        try {
            $job->handle($stub, app(GapMaterialiser::class), app(DocumentationStatusDeriver::class));
        } finally {
            Log::shouldHaveReceived('error')->once();
        }
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
