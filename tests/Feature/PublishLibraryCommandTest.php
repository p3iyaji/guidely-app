<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\OntologyVersionStatus;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\RuleLibraryVersionStatus;
use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PublishLibraryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ontology_roll_forward_publishes_pins_and_enqueues_without_rewriting_citations(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->freezeTime();

        $v1 = OntologyVersion::factory()->published()->create([
            'code' => 'ontology-roll-forward-v1',
            'label' => 'Ontology V1',
        ]);
        $v2 = OntologyVersion::factory()->create([
            'code' => 'ontology-roll-forward-v2',
            'label' => 'Ontology V2',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($v1, null);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $deleted = Pupil::factory()->forSchool($school)->create();
        $deleted->delete();
        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forOntologyVersion($v1)
            ->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => $v2->code,
        ]);

        $this->assertSame(0, $exit);

        $v2->refresh();
        $this->assertSame(OntologyVersionStatus::Published, $v2->status);
        $this->assertSame(now()->toDateTimeString(), $v2->published_at?->toDateTimeString());
        $this->assertSame($v2->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($v2->id, $tenant->fresh()->effectiveOntologyVersion()?->id);

        $this->assertDatabaseHas('determinations', [
            'id' => $determination->id,
            'ontology_version_id' => $v1->id,
        ]);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'ontology_published';
        });
        Bus::assertNotDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($deleted): bool {
            return $job->pupilId === $deleted->id;
        });

        $pupil->refresh();
        $this->assertSame(DocumentationStatus::Evaluating, $pupil->documentation_status);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::LibraryPublished->value)
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('guidely:publish-library', $audit->metadata['source'] ?? null);
        $this->assertSame($v2->code, $audit->metadata['ontology_code'] ?? null);
        $this->assertSame($v2->id, $audit->metadata['ontology_version_id'] ?? null);
        $this->assertSame($v1->id, $audit->metadata['prior_ontology_version_id'] ?? null);
        $this->assertSame($v2->id, $audit->metadata['new_ontology_version_id'] ?? null);
        $this->assertSame(true, $audit->metadata['ontology_published'] ?? null);
        $this->assertSame(true, $audit->metadata['ontology_pin_changed'] ?? null);
        $this->assertSame(1, $audit->metadata['queued_count'] ?? null);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
    }

    public function test_rule_library_roll_forward_pins_published_version_and_enqueues_without_rewriting_citations(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->freezeTime();

        $v1 = RuleLibraryVersion::factory()->published()->create([
            'code' => 'rule-library-roll-forward-v1',
            'label' => 'Rule Library V1',
        ]);
        $v2 = RuleLibraryVersion::factory()->create([
            'code' => 'rule-library-roll-forward-v2',
            'label' => 'Rule Library V2',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo(null, $v1);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $determination = Determination::factory()
            ->forPupil($pupil)
            ->forRuleLibraryVersion($v1)
            ->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--rule-library' => $v2->code,
        ]);

        $this->assertSame(0, $exit);

        $v2->refresh();
        $this->assertSame(RuleLibraryVersionStatus::Published, $v2->status);
        $this->assertSame(now()->toDateTimeString(), $v2->published_at?->toDateTimeString());
        $this->assertSame($v2->id, $tenant->fresh()->current_rule_library_version_id);
        $this->assertSame($v2->id, $tenant->fresh()->effectiveRuleLibraryVersion()?->id);
        $this->assertDatabaseHas('determinations', [
            'id' => $determination->id,
            'rule_library_version_id' => $v1->id,
        ]);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'rule_library_published';
        });

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::LibraryPublished->value)
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('guidely:publish-library', $audit->metadata['source'] ?? null);
        $this->assertSame($v2->code, $audit->metadata['rule_library_code'] ?? null);
        $this->assertSame($v2->id, $audit->metadata['rule_library_version_id'] ?? null);
        $this->assertSame($v1->id, $audit->metadata['prior_rule_library_version_id'] ?? null);
        $this->assertSame($v2->id, $audit->metadata['new_rule_library_version_id'] ?? null);
        $this->assertSame(true, $audit->metadata['rule_library_published'] ?? null);
        $this->assertSame(true, $audit->metadata['rule_library_pin_changed'] ?? null);
        $this->assertSame(1, $audit->metadata['queued_count'] ?? null);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);
    }

    public function test_both_libraries_enqueue_one_job_per_pupil_with_library_published_reason(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $ontologyV1 = OntologyVersion::factory()->published()->create([
            'code' => 'both-libraries-ontology-v1',
        ]);
        $ontologyV2 = OntologyVersion::factory()->create([
            'code' => 'both-libraries-ontology-v2',
        ]);
        $ruleV1 = RuleLibraryVersion::factory()->published()->create([
            'code' => 'both-libraries-rule-v1',
        ]);
        $ruleV2 = RuleLibraryVersion::factory()->published()->create([
            'code' => 'both-libraries-rule-v2',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($ontologyV1, $ruleV1);
        $first = Pupil::factory()->forSchool($school)->create();
        $second = Pupil::factory()->forSchool($school)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => $ontologyV2->code,
            '--rule-library' => $ruleV2->code,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(OntologyVersionStatus::Published, $ontologyV2->fresh()->status);
        $this->assertSame($ontologyV2->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($ruleV2->id, $tenant->fresh()->current_rule_library_version_id);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 2);
        foreach ([$first, $second] as $pupil) {
            Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
                return $job->tenantId === $tenant->id
                    && $job->pupilId === $pupil->id
                    && $job->reason === 'library_published';
            });
        }
    }

    public function test_idempotent_publish_of_already_pinned_version_succeeds_without_enqueueing(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $version = OntologyVersion::factory()->published()->create([
            'code' => 'already-pinned-ontology',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($version, null);
        Pupil::factory()->forSchool($school)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => $version->code,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($version->id, $tenant->fresh()->current_ontology_version_id);
        Bus::assertNothingDispatched();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::LibraryPublished->value)
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(false, $audit->metadata['ontology_pin_changed'] ?? null);
        $this->assertSame(0, $audit->metadata['queued_count'] ?? null);
    }

    public function test_idempotent_rule_library_publish_of_already_pinned_version_succeeds_without_enqueueing(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $version = RuleLibraryVersion::factory()->published()->create([
            'code' => 'already-pinned-rule-library',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo(null, $version);
        Pupil::factory()->forSchool($school)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--rule-library' => $version->code,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($version->id, $tenant->fresh()->current_rule_library_version_id);
        Bus::assertNothingDispatched();

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::LibraryPublished->value)
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(false, $audit->metadata['rule_library_pin_changed'] ?? null);
        $this->assertSame(0, $audit->metadata['queued_count'] ?? null);
    }

    public function test_publishing_already_pinned_draft_ontology_enqueues_because_effective_library_changes(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $draft = OntologyVersion::factory()->create([
            'code' => 'already-pinned-draft-ontology',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($draft, null);
        $pupil = Pupil::factory()->forSchool($school)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => $draft->code,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(OntologyVersionStatus::Published, $draft->fresh()->status);
        $this->assertSame($draft->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($draft->id, $tenant->fresh()->effectiveOntologyVersion()?->id);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'ontology_published';
        });
    }

    public function test_unknown_tenant_fails_without_pinning_or_enqueueing(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $version = OntologyVersion::factory()->published()->create([
            'code' => 'orphan-ontology-code',
        ]);
        $tenant = Tenant::factory()->school()->create();
        $tenant->forceFill([
            'current_ontology_version_id' => $version->id,
        ])->save();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => '01missingtenant000000000000',
            '--ontology' => $version->code,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('was not found', Artisan::output());
        $this->assertSame($version->id, $tenant->fresh()->current_ontology_version_id);
        Bus::assertNothingDispatched();
        $this->assertSame(0, AuditEvent::query()->where('event_type', AuditEventType::LibraryPublished->value)->count());
    }

    public function test_unknown_version_code_fails_without_pinning_or_enqueueing(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $version = OntologyVersion::factory()->published()->create([
            'code' => 'known-ontology-pin',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($version, null);
        Pupil::factory()->forSchool($school)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => 'not-a-real-ontology-code',
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame($version->id, $tenant->fresh()->current_ontology_version_id);
        Bus::assertNothingDispatched();
        $this->assertSame(0, AuditEvent::query()->where('event_type', AuditEventType::LibraryPublished->value)->count());
    }

    public function test_neither_library_flag_fails_without_pinning(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $version = OntologyVersion::factory()->published()->create([
            'code' => 'untouched-ontology-pin',
        ]);
        [$tenant] = $this->tenantPinnedTo($version, null);

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame($version->id, $tenant->fresh()->current_ontology_version_id);
        Bus::assertNothingDispatched();
        $this->assertSame(0, AuditEvent::query()->where('event_type', AuditEventType::LibraryPublished->value)->count());
    }

    public function test_sibling_tenant_pin_and_pupils_are_untouched(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        $v1 = OntologyVersion::factory()->published()->create([
            'code' => 'sibling-ontology-v1',
        ]);
        $v2 = OntologyVersion::factory()->create([
            'code' => 'sibling-ontology-v2',
        ]);
        [$tenant, $school] = $this->tenantPinnedTo($v1, null);
        $pupil = Pupil::factory()->forSchool($school)->create();

        [$sibling, $siblingSchool] = $this->tenantPinnedTo($v1, null);
        $siblingPupil = Pupil::factory()->forSchool($siblingSchool)->create();

        $exit = Artisan::call('guidely:publish-library', [
            'tenant_id' => $tenant->id,
            '--ontology' => $v2->code,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($v2->id, $tenant->fresh()->current_ontology_version_id);
        $this->assertSame($v1->id, $sibling->fresh()->current_ontology_version_id);
        $siblingPupil->refresh();
        $this->assertSame(DocumentationStatus::NotStarted, $siblingPupil->documentation_status);

        Bus::assertDispatchedTimes(SreReevaluatePupil::class, 1);
        Bus::assertDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($tenant, $pupil): bool {
            return $job->tenantId === $tenant->id
                && $job->pupilId === $pupil->id
                && $job->reason === 'ontology_published';
        });
        Bus::assertNotDispatched(SreReevaluatePupil::class, function (SreReevaluatePupil $job) use ($siblingPupil): bool {
            return $job->pupilId === $siblingPupil->id;
        });
    }

    /**
     * @return array{0: Tenant, 1: School}
     */
    private function tenantPinnedTo(?OntologyVersion $ontologyVersion, ?RuleLibraryVersion $ruleLibraryVersion): array
    {
        $tenant = Tenant::factory()->school()->create();
        $tenant->forceFill([
            'current_ontology_version_id' => $ontologyVersion?->id,
            'current_rule_library_version_id' => $ruleLibraryVersion?->id,
        ])->save();
        $school = School::factory()->forTenant($tenant)->create();

        return [$tenant, $school];
    }
}
