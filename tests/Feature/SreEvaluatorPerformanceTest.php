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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SreEvaluatorPerformanceTest extends TestCase
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

    public function test_evaluator_completes_within_three_seconds_for_five_hundred_submitted_records(): void
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

        $setting = SettingTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)->where('code', 'CLASSROOM')->firstOrFail();
        $provision = ProvisionTerm::query()->forVersion(PilotOntology::ensurePublishedVersion()->id)->where('code', 'UNIVERSAL')->firstOrFail();

        $rows = [];
        $base = now()->subDays(10)->utc();

        for ($i = 0; $i < 500; $i++) {
            $isIntervention = $i % 2 === 1;
            $rows[] = [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'pupil_id' => $pupil->id,
                'author_id' => $teacher->id,
                'type' => $isIntervention ? EvidenceType::Intervention->value : EvidenceType::Observation->value,
                'lifecycle' => EvidenceLifecycle::Submitted->value,
                'source' => null,
                'external_id' => null,
                'occurred_at' => $base->copy()->addMinutes($i)->toDateTimeString(),
                'setting_term_id' => $isIntervention ? null : $setting->id,
                'provision_term_id' => $isIntervention ? $provision->id : null,
                'related_intervention_id' => null,
                'body' => "Perf fixture {$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            EvidenceRecord::withoutGlobalScope('tenant')->insert($chunk);
        }

        $this->assertSame(
            500,
            EvidenceRecord::withoutGlobalScope('tenant')->where('pupil_id', $pupil->id)->count(),
        );

        $started = hrtime(true);
        app(SreEvaluator::class)->evaluate($tenant->id, $pupil->id, 'perf-500');
        $elapsedSeconds = (hrtime(true) - $started) / 1e9;

        $this->assertLessThan(
            3.0,
            $elapsedSeconds,
            "SRE evaluator exceeded 3s for 500 Evidence Records ({$elapsedSeconds}s).",
        );

        $this->assertSame(
            4,
            Determination::withoutGlobalScope('tenant')->current()->forPupil($pupil->id)->count(),
        );
        $this->assertTrue(
            Determination::withoutGlobalScope('tenant')
                ->current()
                ->forPupil($pupil->id)
                ->where('dimension', SreDimension::SequentialCompliance->value)
                ->exists(),
        );
    }
}
