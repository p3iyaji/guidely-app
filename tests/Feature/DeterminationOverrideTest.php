<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\DeterminationResult;
use App\Domain\Sre\DocumentationStatusDeriver;
use App\Domain\Sre\Gap;
use App\Domain\Sre\GapMaterialiser;
use App\Domain\Sre\Override;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeterminationOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_override_appends_record_enqueues_job_and_closes_gap_after_recompute(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);
        $evidenceCount = EvidenceRecord::query()->count();

        Gap::factory()->forDetermination($determination)->open()->create();

        $rationale = 'Professional judgement that this dimension should not keep a Gap open.';

        $response = $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => $rationale,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.determination_id', $determination->id)
            ->assertJsonPath('data.dimension', SreDimension::SequentialCompliance->value)
            ->assertJsonPath('data.user_id', $senco->id)
            ->assertJsonPath('data.rationale', $rationale);

        $this->assertStringNotContainsString('confidence', strtolower($response->getContent()));
        $this->assertStringNotContainsString('diagnos', strtolower($response->getContent()));

        $this->assertDatabaseCount('overrides', 1);
        $this->assertDatabaseHas('overrides', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'determination_id' => $determination->id,
            'dimension' => SreDimension::SequentialCompliance->value,
            'user_id' => $senco->id,
            'rationale' => $rationale,
        ]);

        $determination->refresh();
        $this->assertTrue($determination->is_current);
        $this->assertSame(DeterminationResult::Unmet, $determination->result);
        $this->assertSame($evidenceCount, EvidenceRecord::query()->count());

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::SreOverrideCreated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($determination->id, $audit->metadata['determination_id'] ?? null);
        $this->assertSame(SreDimension::SequentialCompliance->value, $audit->metadata['dimension'] ?? null);
        $this->assertArrayNotHasKey('rationale', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->documentation_status);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'override';
        });

        $currents = Determination::query()->current()->forPupil($pupil->id)->get();
        app(GapMaterialiser::class)->materialise($pupil, $currents);
        app(DocumentationStatusDeriver::class)->apply($pupil, $currents);

        $this->assertSame(0, Gap::query()->forPupil($pupil->id)->open()->count());
        $this->assertNotSame(DocumentationStatus::Gaps, $pupil->fresh()->documentation_status);
        $this->assertTrue($determination->fresh()->is_current);
        $this->assertSame(DeterminationResult::Unmet, $determination->fresh()->result);
    }

    public function test_recompute_keeps_sibling_gap_when_only_one_dimension_is_overridden(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $overridden = $this->currentDetermination($pupil, DeterminationResult::Unmet);
        $sibling = $this->currentDetermination(
            $pupil,
            DeterminationResult::Insufficient,
            SreDimension::EvidentialSufficiency,
        );

        $override = new Override([
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
        ]);
        $override->forceFill([
            'determination_id' => $overridden->id,
            'dimension' => $overridden->dimension,
            'user_id' => $senco->id,
            'rationale' => 'Professional judgement that this Sequential Gap should close.',
        ])->save();

        $this->assertSame(SreDimension::SequentialCompliance, $overridden->dimension);
        $this->assertSame(SreDimension::EvidentialSufficiency, $sibling->dimension);
        $this->assertSame(DeterminationResult::Insufficient, $sibling->result);
        $this->assertTrue(app(GapMaterialiser::class)->opensGap($sibling));
        $this->assertFalse(app(GapMaterialiser::class)->opensGap($overridden));

        $currents = Determination::withoutGlobalScope('tenant')
            ->current()
            ->forPupil($pupil->id)
            ->orderBy('dimension')
            ->get();

        $this->assertCount(2, $currents, $currents->pluck('dimension')->implode(','));

        $gaps = app(GapMaterialiser::class)->materialise($pupil, $currents);
        app(DocumentationStatusDeriver::class)->apply($pupil, $currents);

        $this->assertCount(1, $gaps);
        $this->assertSame(1, Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->count());
        $this->assertTrue(Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->where('determination_id', $sibling->id)->exists());
        $this->assertFalse(Gap::withoutGlobalScope('tenant')->forPupil($pupil->id)->open()->where('determination_id', $overridden->id)->exists());
        $this->assertSame(DocumentationStatus::Gaps, $pupil->fresh()->documentation_status);
    }

    public function test_school_leader_can_override_insufficient_determination(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = $this->currentDetermination(
            $pupil,
            DeterminationResult::Insufficient,
            SreDimension::EvidentialSufficiency,
        );

        $response = $this->actingAs($leader)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => 'School Leader professional judgement to close this Gap.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $leader->id)
            ->assertJsonPath('data.dimension', SreDimension::EvidentialSufficiency->value);

        Queue::assertPushed(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'override';
        });
    }

    public function test_returns_422_when_rationale_is_shorter_than_20_characters(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        $response = $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => str_repeat('a', 19),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rationale'])
            ->assertJsonPath('errors.rationale.0', 'A rationale of at least 20 characters is required.');

        $this->assertSame(0, Override::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_returns_422_when_trimmed_rationale_is_shorter_than_20_characters(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        $response = $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => '  '.str_repeat('a', 19).'  ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rationale']);

        $this->assertSame(0, Override::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_returns_403_when_teacher_posts_override(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->assignedTo($teacher)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        $response = $this->actingAs($teacher)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => 'Teachers must not override Determinations in this product.',
            ]);

        $this->assertForbidden($response);
        $this->assertSame(0, Override::query()->count());
        Queue::assertNothingPushed();
    }

    /**
     * @param  array{result: DeterminationResult, current: bool}  $state
     */
    #[DataProvider('notOverridableDeterminations')]
    public function test_returns_422_when_determination_is_not_overridable(DeterminationResult $result, bool $current): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $factory = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult($result);

        $determination = $current
            ? $factory->current()->create()
            : $factory->create(['is_current' => false]);

        $response = $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => 'Professional judgement that should still be rejected.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['determination'])
            ->assertJsonPath('errors.determination.0', 'This Determination is not overridable.');

        $this->assertSame(0, Override::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_returns_403_when_other_school_senco_posts_override(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        $tenant = Tenant::factory()->create();
        $accessible = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($accessible->id);
        $pupil = Pupil::factory()->forSchool($otherSchool)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        $response = $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => 'Should not be allowed for this School.',
            ]);

        $this->assertForbidden($response);
        $this->assertSame(0, Override::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_returns_404_when_determination_belongs_to_another_tenant(): void
    {
        Queue::fake([SreReevaluatePupil::class]);

        [, , $senco] = $this->tenantSchoolAndSenco();
        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        $otherPupil = Pupil::factory()->forSchool($otherSchool)->create();
        $determination = $this->currentDetermination($otherPupil, DeterminationResult::Unmet);

        $this->actingAs($senco)
            ->postJson("/api/v1/determinations/{$determination->id}/overrides", [
                'rationale' => 'Cross-tenant Override must not confirm the record.',
            ])
            ->assertNotFound();

        $this->assertSame(0, Override::withoutGlobalScope('tenant')->count());
        Queue::assertNothingPushed();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        $this->postJson("/api/v1/determinations/{$determination->id}/overrides", [
            'rationale' => 'Unauthenticated Override must be rejected.',
        ])->assertUnauthorized();
    }

    public function test_recompute_does_not_reopen_gap_when_a_new_current_shares_the_overridden_dimension(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $original = $this->currentDetermination($pupil, DeterminationResult::Unmet);

        Override::factory()
            ->forDetermination($original)
            ->create([
                'user_id' => $senco->id,
            ]);

        $original->forceFill(['is_current' => false])->save();

        $replacement = Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::SequentialCompliance)
            ->withResult(DeterminationResult::Unmet)
            ->current()
            ->create();

        $gaps = app(GapMaterialiser::class)->materialise($pupil, collect([$replacement]));

        $this->assertCount(0, $gaps);
        $this->assertSame(0, Gap::query()->forPupil($pupil->id)->open()->count());
        $this->assertSame(DeterminationResult::Unmet, $replacement->result);
    }

    /**
     * @return array<string, array{0: DeterminationResult, 1: bool}>
     */
    public static function notOverridableDeterminations(): array
    {
        return [
            'met current' => [DeterminationResult::Met, true],
            'uncovered current' => [DeterminationResult::Uncovered, true],
            'unmet not current' => [DeterminationResult::Unmet, false],
        ];
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

    private function currentDetermination(
        Pupil $pupil,
        DeterminationResult $result,
        SreDimension $dimension = SreDimension::SequentialCompliance,
    ): Determination {
        return Determination::factory()
            ->forPupil($pupil)
            ->forDimension($dimension)
            ->withResult($result)
            ->current()
            ->create();
    }
}
