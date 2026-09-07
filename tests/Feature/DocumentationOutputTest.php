<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\SreDimension;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Determination;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Jobs\SreReevaluatePupil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DocumentationOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_senco_generates_review_summary_with_citations_and_disclaimer(): void
    {
        Bus::fake([SreReevaluatePupil::class]);
        $this->travelTo('2026-09-07 12:00:00');

        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $evidence = EvidenceRecord::factory()->forPupil($pupil)->authoredBy($senco)->create();
        $draft = EvidenceRecord::factory()->forPupil($pupil)->authoredBy($senco)->draft()->create();

        $determinationIds = [];

        foreach (SreDimension::cases() as $dimension) {
            $determination = Determination::factory()
                ->forPupil($pupil)
                ->forDimension($dimension)
                ->current()
                ->create([
                    'reasoning_pathway' => [
                        'dimension' => $dimension->value,
                        'evidence_ids' => $dimension === SreDimension::SequentialCompliance
                            ? [$evidence->id, $draft->id]
                            : [],
                    ],
                ]);
            $determinationIds[] = $determination->id;
        }

        $gap = Gap::factory()->forPupil($pupil)->open()->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.review_cycle_id', $cycle->id)
            ->assertJsonPath('data.type', DocumentationOutputType::ReviewSummary->value)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.confirmer_user_id', $senco->id)
            ->assertJsonPath('data.disclaimer_text', DocumentationOutput::DISCLAIMER_TEXT)
            ->assertJsonPath('data.payload.kind', DocumentationOutputType::ReviewSummary->value)
            ->assertJsonPath('data.payload.evidence_ids.0', $evidence->id)
            ->assertJsonPath('data.payload.gap_ids.0', $gap->id);

        $this->assertSame([$evidence->id], $response->json('data.payload.evidence_ids'));

        $this->assertSame($determinationIds, $response->json('data.payload.determination_ids'));
        $this->assertSame('2026-09-07T12:00:00+00:00', $response->json('data.confirmed_at'));
        $this->assertSame('2026-09-07T12:00:00+00:00', $response->json('data.pack_ready_at'));
        $this->assertNoDiagnosisCopy($response);

        $this->assertDatabaseHas('documentation_outputs', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'version' => 1,
            'confirmer_user_id' => $senco->id,
            'disclaimer_text' => DocumentationOutput::DISCLAIMER_TEXT,
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', AuditEventType::DocumentationOutputGenerated->value)
            ->where('resource_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(DocumentationOutputType::ReviewSummary->value, $audit->metadata['type'] ?? null);
        $this->assertSame(1, $audit->metadata['version'] ?? null);
        $this->assertSame($pupil->id, $audit->metadata['pupil_id'] ?? null);
        $this->assertSame($cycle->id, $audit->metadata['review_cycle_id'] ?? null);
        $this->assertSame($senco->id, $audit->metadata['confirmer_user_id'] ?? null);
        $this->assertArrayNotHasKey('evidence', $audit->metadata ?? []);

        Bus::assertNothingDispatched();
    }

    public function test_senco_generates_ehcp_pack_with_present_and_absent_domains(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $need = NeedTerm::factory()->create();
        $provision = ProvisionTerm::factory()->create();
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($need)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $intervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->intervention($provision)
            ->create();
        $gap = Gap::factory()->forPupil($pupil)->open()->create();
        Determination::factory()
            ->forPupil($pupil)
            ->forDimension(SreDimension::OutcomeProgression)
            ->current()
            ->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::EhcpPack->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', DocumentationOutputType::EhcpPack->value)
            ->assertJsonPath('data.payload.kind', DocumentationOutputType::EhcpPack->value)
            ->assertJsonPath('data.payload.present.need.0.id', $need->id)
            ->assertJsonPath('data.payload.present.provision.0.evidence_id', $intervention->id)
            ->assertJsonPath('data.payload.present.provision.0.provision.id', $provision->id)
            ->assertJsonPath('data.payload.present.outcome', [])
            ->assertJsonPath('data.payload.absent.0.domain', 'outcome')
            ->assertJsonPath('data.payload.absent.0.gap_ids.0', $gap->id)
            ->assertJsonPath('data.payload.gap_ids.0', $gap->id)
            ->assertJsonPath('data.payload.evidence_ids.0', $intervention->id);

        $this->assertSame([], $response->json('data.payload.present.outcome'));
        $this->assertNoDiagnosisCopy($response);

        Bus::assertNothingDispatched();
    }

    public function test_senco_generates_ehcp_pack_with_present_outcomes(): void
    {
        Bus::fake([SreReevaluatePupil::class]);

        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $need = NeedTerm::factory()->create();
        $provision = ProvisionTerm::factory()->create();
        $pupil = Pupil::factory()->forSchool($school)->withPrimaryNeed($need)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $intervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->intervention($provision)
            ->create();
        $draftIntervention = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->intervention($provision)
            ->draft()
            ->create();
        $outcome = EvidenceRecord::factory()
            ->forPupil($pupil)
            ->authoredBy($senco)
            ->response($draftIntervention)
            ->create();

        $response = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::EhcpPack->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payload.present.outcome.0.evidence_id', $outcome->id)
            ->assertJsonPath('data.payload.present.outcome.0.related_intervention_id', null)
            ->assertJsonPath('data.payload.present.provision.0.evidence_id', $intervention->id);

        $this->assertSame([], $response->json('data.payload.absent'));
        $this->assertNotContains($draftIntervention->id, $response->json('data.payload.evidence_ids'));

        Bus::assertNothingDispatched();
    }

    public function test_senco_generates_output_nested_under_pupil_and_cycle(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/pupils/'.$pupil->id.'/review-cycles/'.$cycle->id.'/outputs', [
                'type' => DocumentationOutputType::ReviewSummary->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.pupil_id', $pupil->id)
            ->assertJsonPath('data.review_cycle_id', $cycle->id)
            ->assertJsonPath('data.version', 1);
    }

    public function test_senco_generates_review_summary_for_a_closed_cycle(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->closed()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::ReviewSummary->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.review_cycle_id', $cycle->id)
            ->assertJsonPath('data.version', 1);
    }

    public function test_returns_422_when_confirmation_is_missing_or_wrong_confirmer(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create([
            'name' => 'Alex SENCO',
        ]);
        $senco->schools()->attach($school->id);
        $other = User::factory()->forTenant($tenant)->senco()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $base = [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
        ];

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmer_user_id', 'disclaimer_acknowledged']);

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                ...$base,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['disclaimer_acknowledged'])
            ->assertJsonPath('errors.disclaimer_acknowledged.0', 'Disclaimer acknowledgement is required.');

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                ...$base,
                'confirmer_user_id' => $other->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmer_user_id'])
            ->assertJsonPath('errors.confirmer_user_id.0', 'The confirmer must be the signed-in User.');

        $this->assertDatabaseCount('documentation_outputs', 0);
    }

    public function test_returns_422_when_review_cycle_belongs_to_another_pupil(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $otherPupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($otherPupil)->open()->create();

        $this->actingAs($senco)
            ->postJson('/api/v1/documentation-outputs', [
                'pupil_id' => $pupil->id,
                'review_cycle_id' => $cycle->id,
                'type' => DocumentationOutputType::ReviewSummary->value,
                'confirmer_user_id' => $senco->id,
                'disclaimer_acknowledged' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_cycle_id']);

        $this->assertDatabaseCount('documentation_outputs', 0);
    }

    public function test_returns_403_when_teacher_generates_an_output(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $this->assertForbidden($this->actingAs($teacher)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $teacher->id,
            'disclaimer_acknowledged' => true,
        ]));
        $this->assertForbidden($this->actingAs($teacher)->getJson('/api/v1/documentation-outputs'));

        $this->assertDatabaseCount('documentation_outputs', 0);
    }

    public function test_school_leader_lists_school_outputs_and_cannot_generate(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $leader = User::factory()->forTenant($tenant)->schoolLeader()->create();
        $leader->schools()->attach($school->id);
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->reviewSummary()
            ->create();

        $this->actingAs($leader)
            ->getJson('/api/v1/documentation-outputs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $output->id);

        $this->actingAs($leader)
            ->getJson('/api/v1/documentation-outputs/'.$output->id)
            ->assertOk()
            ->assertJsonPath('data.id', $output->id);

        $this->assertForbidden($this->actingAs($leader)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $leader->id,
            'disclaimer_acknowledged' => true,
        ]));
    }

    public function test_returns_403_when_other_school_senco_posts_or_gets(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $otherSchool = School::factory()->forTenant($tenant)->create();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($otherSchool->id);
        $owner = User::factory()->forTenant($tenant)->senco()->create();
        $owner->schools()->attach($school->id);
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($owner)
            ->create();

        $this->assertForbidden($this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]));
        $this->assertForbidden($this->actingAs($senco)->getJson('/api/v1/documentation-outputs/'.$output->id));

        $this->actingAs($senco)
            ->getJson('/api/v1/documentation-outputs')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->assertDatabaseCount('documentation_outputs', 1);
    }

    public function test_regeneration_creates_new_version_and_keeps_previous(): void
    {
        [, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $first = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $first->assertCreated()->assertJsonPath('data.version', 1);
        $firstId = $first->json('data.id');
        $firstConfirmedAt = $first->json('data.confirmed_at');

        $second = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertNotSame($firstId, $second->json('data.id'));
        $this->assertDatabaseCount('documentation_outputs', 2);
        $this->assertDatabaseHas('documentation_outputs', [
            'id' => $firstId,
            'version' => 1,
        ]);
        $this->assertSame($firstConfirmedAt, DocumentationOutput::query()->find($firstId)?->confirmed_at?->utc()->toIso8601String());

        $ehcp = $this->actingAs($senco)->postJson('/api/v1/documentation-outputs', [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::EhcpPack->value,
            'confirmer_user_id' => $senco->id,
            'disclaimer_acknowledged' => true,
        ]);

        $ehcp->assertCreated()->assertJsonPath('data.version', 1);
        $this->assertDatabaseHas('documentation_outputs', [
            'id' => $firstId,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'version' => 1,
        ]);
    }

    public function test_returns_403_when_support_or_tenant_admin_lists_or_generates(): void
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $support = User::factory()->forTenant($tenant)->supportStaff()->create();
        $support->schools()->attach($school->id);
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();

        $payload = [
            'pupil_id' => $pupil->id,
            'review_cycle_id' => $cycle->id,
            'type' => DocumentationOutputType::ReviewSummary->value,
            'confirmer_user_id' => $support->id,
            'disclaimer_acknowledged' => true,
        ];

        $this->assertForbidden($this->actingAs($support)->getJson('/api/v1/documentation-outputs'));
        $this->assertForbidden($this->actingAs($support)->postJson('/api/v1/documentation-outputs', $payload));
        $this->assertForbidden($this->actingAs($admin)->getJson('/api/v1/documentation-outputs'));
        $this->assertForbidden($this->actingAs($admin)->postJson('/api/v1/documentation-outputs', [
            ...$payload,
            'confirmer_user_id' => $admin->id,
        ]));
    }

    public function test_returns_404_when_another_tenant_requests_an_output(): void
    {
        [$tenant, $school, $senco] = $this->tenantSchoolAndSenco();
        $pupil = Pupil::factory()->forSchool($school)->create();
        $cycle = ReviewCycle::factory()->forPupil($pupil)->open()->create();
        $output = DocumentationOutput::factory()
            ->forCycle($cycle)
            ->confirmedBy($senco)
            ->create();

        $otherTenant = Tenant::factory()->create();
        $otherSchool = School::factory()->forTenant($otherTenant)->create();
        $otherSenco = User::factory()->forTenant($otherTenant)->senco()->create();
        $otherSenco->schools()->attach($otherSchool->id);

        $this->actingAs($otherSenco)
            ->getJson('/api/v1/documentation-outputs/'.$output->id)
            ->assertNotFound();

        $this->assertDatabaseHas('documentation_outputs', [
            'id' => $output->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_guest_cannot_list_or_generate_outputs(): void
    {
        $this->getJson('/api/v1/documentation-outputs')->assertUnauthorized();
        $this->postJson('/api/v1/documentation-outputs', [])->assertUnauthorized();
    }

    /**
     * FR-35 disclaimer may say “not diagnoses”; payload and other copy must not.
     */
    private function assertNoDiagnosisCopy(TestResponse $response): void
    {
        $body = strtolower($response->getContent());
        $withoutDisclaimer = str_replace(strtolower(DocumentationOutput::DISCLAIMER_TEXT), '', $body);
        $payload = strtolower((string) json_encode($response->json('data.payload')));

        $this->assertStringNotContainsString('confidence', $body);
        $this->assertStringNotContainsString('auto-approved', $body);
        $this->assertStringNotContainsString('diagnos', $withoutDisclaimer);
        $this->assertStringNotContainsString('diagnos', $payload);
        $this->assertStringNotContainsString('confidence', $payload);
        $this->assertStringNotContainsString('auto-approved', $payload);
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
        $senco = User::factory()->forTenant($tenant)->senco()->create([
            'name' => 'Alex SENCO',
        ]);
        $senco->schools()->attach($school->id);

        return [$tenant, $school, $senco];
    }
}
