<?php

namespace Tests\Feature;

use App\Domain\Identity\Role;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Seeders\DemoPilotSeeder;
use Database\Seeders\DemoTrustSeeder;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_pilot_seeder_covers_roles_pupils_assignments_and_left_pupil(): void
    {
        $this->seed(DemoPilotSeeder::class);

        $tenant = Tenant::query()->where('name', DemoPilotSeeder::TENANT_NAME)->first();
        $this->assertNotNull($tenant);

        foreach (DemoPilotSeeder::USERS as $email) {
            $this->assertDatabaseHas('users', ['email' => $email]);
        }

        $this->assertDatabaseHas('users', [
            'email' => DemoPilotSeeder::USERS['operator'],
            'role' => Role::PlatformOperator->value,
            'tenant_id' => null,
        ]);

        $teacher = User::query()->where('email', DemoPilotSeeder::USERS['teacher'])->firstOrFail();
        $alex = Pupil::withoutGlobalScopes()->where('mis_key', 'MIS-OAK-001')->firstOrFail();
        $blake = Pupil::withoutGlobalScopes()->where('mis_key', 'MIS-OAK-002')->firstOrFail();
        $casey = Pupil::withoutGlobalScopes()->where('family_name', 'Patel')->firstOrFail();

        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $alex->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('pupil_user', [
            'pupil_id' => $blake->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('pupil_user', [
            'pupil_id' => $casey->id,
            'user_id' => $teacher->id,
        ]);

        $this->assertSame(4, Pupil::withoutGlobalScopes()->whereNull('deleted_at')->count());
        $this->assertSame(1, Pupil::withoutGlobalScopes()->onlyTrashed()->count());
        $this->assertDatabaseHas('pupils', ['mis_key' => 'MIS-OAK-001']);

        $senco = User::query()->where('email', DemoPilotSeeder::USERS['senco'])->firstOrFail();
        $this->actingAs($senco)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($teacher)->getJson('/api/v1/pupils')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertTrue(
            SettingTerm::query()->fromPublishedStub()->exists(),
            'DemoPilotSeeder should seed the Setting Ontology stub.',
        );
        $this->assertDatabaseHas('ontology_versions', [
            'code' => SettingOntologySeeder::STUB_VERSION_CODE,
            'status' => 'published',
        ]);
    }

    public function test_demo_trust_seeder_enables_trust_dashboard_for_trust_roles(): void
    {
        $this->seed(DemoTrustSeeder::class);

        $tenant = Tenant::query()->where('name', DemoTrustSeeder::TENANT_NAME)->firstOrFail();
        $this->assertTrue(
            app(FeatureFlagResolver::class)->isEnabled(FeatureFlagKey::TrustDashboard, $tenant),
        );

        $lead = User::query()->where('email', DemoTrustSeeder::USERS['send_lead'])->firstOrFail();
        $this->assertTrue($lead->isActiveTenantStaff());
    }

    public function test_database_seeder_runs_pilot_and_trust_demos(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => DemoPilotSeeder::USERS['senco']]);
        $this->assertDatabaseHas('users', ['email' => DemoTrustSeeder::USERS['send_lead']]);
        $this->assertTrue(
            SettingTerm::query()->fromPublishedStub()->exists(),
            'DatabaseSeeder should leave the Setting Ontology stub available.',
        );
    }
}
