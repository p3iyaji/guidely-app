<?php

namespace Tests\Feature;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EnqueueSreReevaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sets_evaluating_and_dispatches_job(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'documentation_status' => DocumentationStatus::NotStarted,
        ]);

        app(EnqueueSreReevaluation::class)->handle($pupil, 'evidence_submitted', 'test');

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->documentation_status);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'evidence_submitted';
        });
    }

    public function test_dispatch_failure_restores_prior_documentation_status(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create([
            'documentation_status' => DocumentationStatus::Ready,
        ]);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('dispatch boom'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        app(EnqueueSreReevaluation::class)->handle($pupil, 'evidence_submitted', 'test');

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Ready, $pupil->documentation_status);
        Log::shouldHaveReceived('warning')->once();
    }
}
