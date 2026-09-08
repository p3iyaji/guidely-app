<?php

namespace Tests\Feature;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleStatus;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_report_completes_within_five_seconds_for_five_hundred_pupils(): void
    {
        $this->travelTo('2026-09-08 12:00:00');

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        $now = now();
        $rows = [];

        for ($i = 0; $i < 500; $i++) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'given_name' => 'Pupil',
                'family_name' => sprintf('%04d', $i),
                'mis_key' => null,
                'date_of_birth' => null,
                'year_group' => 'Year 7',
                'send_status' => 'neither',
                'notes' => null,
                'documentation_status' => $i % 2 === 0
                    ? DocumentationStatus::Ready->value
                    : DocumentationStatus::Gaps->value,
                'primary_need_term_id' => null,
                'primary_need_notes' => null,
                'secondary_need_term_id' => null,
                'secondary_need_notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            Pupil::withoutGlobalScope('tenant')->insert($chunk);
        }

        $this->assertSame(
            500,
            Pupil::withoutGlobalScope('tenant')->where('school_id', $school->id)->count(),
        );

        $cycleRows = [];

        foreach (array_slice($rows, 0, 50) as $row) {
            $cycleRows[] = [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'pupil_id' => $row['id'],
                'type' => ReviewCycleType::AnnualReview->value,
                'due_on' => '2026-09-20',
                'ehcp_linked' => false,
                'status' => ReviewCycleStatus::Open->value,
                'closed_at' => null,
                'closed_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ReviewCycle::withoutGlobalScope('tenant')->insert($cycleRows);

        $started = hrtime(true);
        $response = $this->actingAs($senco)->getJson('/api/v1/school-report');
        $elapsedSeconds = (hrtime(true) - $started) / 1e9;

        $response->assertOk()
            ->assertJsonPath('data.pupils_in_scope', 500)
            ->assertJsonPath('data.review_cycles_due', 50);

        $this->assertSame(500, array_sum($response->json('data.by_status')));
        $this->assertLessThan(
            5.0,
            $elapsedSeconds,
            "School Report exceeded 5s for 500 Pupils ({$elapsedSeconds}s).",
        );
    }
}
