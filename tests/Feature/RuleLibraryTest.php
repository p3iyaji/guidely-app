<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessMessages;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\RuleCategory;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\RuleLibraryVersionStatus;
use App\Domain\Ontology\SreDimension;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\RuleLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RuleLibrarySeeder::class);
    }

    public function test_pilot_seed_covers_fr26_dimensions_plus_escalation_and_review_threshold(): void
    {
        $version = PilotRuleLibrary::ensurePublishedVersion();

        $this->assertSame(PilotRuleLibrary::VERSION_CODE, $version->code);
        $this->assertSame(RuleLibraryVersionStatus::Published, $version->status);
        $this->assertStringContainsString('partial', strtolower($version->label));

        $rules = Rule::query()->forVersion($version->id)->orderBy('sort_order')->get();
        $this->assertSame(count(RuleLibrarySeeder::RULES), $rules->count());

        foreach (SreDimension::cases() as $dimension) {
            $this->assertTrue(
                $rules->contains(fn (Rule $rule): bool => $rule->dimension === $dimension),
                "Expected at least one Rule for dimension [{$dimension->value}]",
            );
        }

        $categories = $rules->pluck('category')->unique()->all();
        $this->assertContains(RuleCategory::Documentation, $categories);
        $this->assertContains(RuleCategory::Threshold, $categories);
        $this->assertContains(RuleCategory::Escalation, $categories);
        $this->assertContains(RuleCategory::ReviewThreshold, $categories);

        foreach ($rules as $rule) {
            $this->assertSame($version->id, $rule->rule_library_version_id);
            $this->assertIsArray($rule->condition);
            $this->assertIsArray($rule->evaluation);
            $this->assertIsArray($rule->outcome);
        }
    }

    public function test_capture_roles_can_list_rules_for_effective_version(): void
    {
        [$tenant, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);
        $version = PilotRuleLibrary::ensurePublishedVersion();
        $expectedCodes = array_column(RuleLibrarySeeder::RULES, 'code');
        $expectedCount = count(RuleLibrarySeeder::RULES);

        foreach ([$teacher, $senco] as $user) {
            $response = $this->actingAs($user)->getJson('/api/v1/ontology/rules');

            $response->assertOk()
                ->assertJsonPath('data.0.rule_library_version_id', $version->id)
                ->assertJsonCount($expectedCount, 'data');

            $codes = collect($response->json('data'))->pluck('code')->all();
            foreach ($expectedCodes as $expectedCode) {
                $this->assertContains($expectedCode, $codes);
            }

            $versionIds = collect($response->json('data'))->pluck('rule_library_version_id')->unique()->all();
            $this->assertSame([$version->id], $versionIds);
        }
    }

    public function test_list_endpoint_omits_inactive_rules_on_effective_version(): void
    {
        [, , $teacher] = $this->tenantSchoolAndTeacher();
        $version = PilotRuleLibrary::ensurePublishedVersion();

        Rule::factory()->forVersion($version)->inactive()->create([
            'code' => 'INACTIVE_PILOT_RULE',
            'label' => 'Inactive pilot rule',
            'dimension' => SreDimension::Proportionality,
            'category' => RuleCategory::Documentation,
            'sort_order' => 99,
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/rules');

        $response->assertOk()
            ->assertJsonCount(count(RuleLibrarySeeder::RULES), 'data');

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertNotContains('INACTIVE_PILOT_RULE', $codes);
        $this->assertContains('SEQ_DOC_INITIAL', $codes);
    }

    public function test_list_endpoint_returns_pinned_published_version_rules_only(): void
    {
        [$tenant, , $teacher] = $this->tenantSchoolAndTeacher();

        $pinned = RuleLibraryVersion::factory()->published()->create([
            'code' => 'pinned-rule-library-v2',
            'label' => 'Pinned Rule Library v2',
        ]);
        Rule::factory()->forVersion($pinned)->create([
            'code' => 'PINNED_RULE',
            'label' => 'Pinned rule',
            'dimension' => SreDimension::Proportionality,
            'category' => RuleCategory::Documentation,
            'sort_order' => 1,
        ]);
        Rule::factory()->forVersion($pinned)->inactive()->create([
            'code' => 'PINNED_INACTIVE',
            'label' => 'Pinned inactive',
            'sort_order' => 2,
        ]);

        $tenant->forceFill(['current_rule_library_version_id' => $pinned->id])->save();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/rules');

        $response->assertOk()
            ->assertJsonPath('data.0.rule_library_version_id', $pinned->id)
            ->assertJsonPath('data.0.code', 'PINNED_RULE')
            ->assertJsonCount(1, 'data');

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertNotContains('SEQ_DOC_INITIAL', $codes);
        $this->assertNotContains('PINNED_INACTIVE', $codes);
    }

    public function test_draft_pin_falls_back_to_pilot_published_version(): void
    {
        [$tenant, , $teacher] = $this->tenantSchoolAndTeacher();
        $pilot = PilotRuleLibrary::ensurePublishedVersion();

        $draft = RuleLibraryVersion::factory()->create([
            'code' => 'draft-rule-library-pin',
            'status' => RuleLibraryVersionStatus::Draft,
        ]);
        Rule::factory()->forVersion($draft)->create([
            'code' => 'DRAFT_ONLY_RULE',
            'label' => 'Draft only',
        ]);

        $tenant->forceFill(['current_rule_library_version_id' => $draft->id])->save();

        $response = $this->actingAs($teacher)->getJson('/api/v1/ontology/rules');

        $response->assertOk()
            ->assertJsonPath('data.0.rule_library_version_id', $pilot->id);

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('SEQ_DOC_INITIAL', $codes);
        $this->assertNotContains('DRAFT_ONLY_RULE', $codes);
        $this->assertSame($pilot->id, $tenant->fresh()->effectiveRuleLibraryVersion()?->id);
    }

    public function test_school_roles_have_no_rule_library_publish_endpoint(): void
    {
        [$tenant, $school, $teacher] = $this->tenantSchoolAndTeacher();
        $senco = User::factory()->forTenant($tenant)->senco()->create();
        $senco->schools()->attach($school->id);

        foreach ([$teacher, $senco] as $user) {
            $this->actingAs($user)->postJson('/api/v1/ontology/rules', [
                'code' => 'should-not-publish',
                'label' => 'Forbidden publish',
            ])->assertMethodNotAllowed();

            $this->actingAs($user)->postJson('/api/v1/ontology/rule-libraries/publish', [])
                ->assertNotFound();

            $this->actingAs($user)->postJson('/api/v1/ontology/rules/publish', [])
                ->assertNotFound();
        }
    }

    public function test_tenant_admin_cannot_list_rules(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->tenantAdmin()->create();

        $this->actingAs($admin)->getJson('/api/v1/ontology/rules')
            ->assertForbidden()
            ->assertJson([
                'message' => AccessMessages::FORBIDDEN,
                'code' => 'forbidden',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: School, 2: User}
     */
    private function tenantSchoolAndTeacher(): array
    {
        $tenant = Tenant::factory()->create();
        $school = School::factory()->forTenant($tenant)->create();
        $teacher = User::factory()->forTenant($tenant)->teacher()->create();
        $teacher->schools()->attach($school->id);

        return [$tenant, $school, $teacher];
    }
}
