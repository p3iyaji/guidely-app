<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OnboardSchoolCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboard_school_creates_school_admin_and_toggles_flags(): void
    {
        $tenant = Tenant::factory()->school()->create();

        $exit = Artisan::call('guidely:onboard-school', [
            'tenant_id' => $tenant->id,
            '--school-name' => 'Riverside Primary',
            '--admin-name' => 'Alex Admin',
            '--admin-email' => 'alex.admin@example.sch.uk',
            '--admin-password' => 'password123',
            '--enable-flag' => [FeatureFlagKey::Connectors->value],
            '--disable-flag' => [FeatureFlagKey::TrustDashboard->value],
        ]);

        $this->assertSame(0, $exit);

        $school = School::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($school);
        $this->assertSame('Riverside Primary', $school->name);
        $this->assertTrue($school->is_active);

        $admin = User::query()->where('email', 'alex.admin@example.sch.uk')->first();
        $this->assertNotNull($admin);
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertSame(Role::TenantAdmin, $admin->role);
        $this->assertDatabaseHas('school_user', [
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);

        $resolver = app(FeatureFlagResolver::class);
        $this->assertTrue($resolver->isEnabled(FeatureFlagKey::Connectors, $tenant->refresh()));
        $this->assertFalse($resolver->isEnabled(FeatureFlagKey::TrustDashboard, $tenant));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::SchoolCreated->value,
            'tenant_id' => $tenant->id,
            'resource_type' => 'school',
            'resource_id' => $school->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::UserCreated->value,
            'tenant_id' => $tenant->id,
            'resource_type' => 'user',
            'resource_id' => (string) $admin->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::FeatureFlagUpdated->value,
            'tenant_id' => $tenant->id,
            'resource_type' => 'tenant',
            'resource_id' => $tenant->id,
        ]);
        $this->assertSame(
            2,
            AuditEvent::query()
                ->where('event_type', AuditEventType::FeatureFlagUpdated->value)
                ->where('tenant_id', $tenant->id)
                ->count()
        );
    }

    public function test_onboard_school_fails_for_missing_tenant(): void
    {
        $exit = Artisan::call('guidely:onboard-school', [
            'tenant_id' => '01missingtenant000000000000',
            '--school-name' => 'Nowhere',
            '--admin-name' => 'Alex Admin',
            '--admin-email' => 'alex.admin@example.sch.uk',
            '--admin-password' => 'password123',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('was not found', Artisan::output());
    }

    public function test_onboard_school_fails_validation_without_required_options(): void
    {
        $tenant = Tenant::factory()->school()->create();

        $exit = Artisan::call('guidely:onboard-school', [
            'tenant_id' => $tenant->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame(0, School::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_onboard_school_fails_for_duplicate_admin_email(): void
    {
        $tenant = Tenant::factory()->school()->create();
        User::factory()->forTenant($tenant)->tenantAdmin()->create([
            'email' => 'taken@example.sch.uk',
        ]);

        $exit = Artisan::call('guidely:onboard-school', [
            'tenant_id' => $tenant->id,
            '--school-name' => 'Second School',
            '--admin-name' => 'Another Admin',
            '--admin-email' => 'taken@example.sch.uk',
            '--admin-password' => 'password123',
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame(0, School::query()->withoutGlobalScopes()->where('name', 'Second School')->count());
    }

    public function test_onboard_school_fails_when_same_flag_enabled_and_disabled(): void
    {
        $tenant = Tenant::factory()->school()->create();

        $exit = Artisan::call('guidely:onboard-school', [
            'tenant_id' => $tenant->id,
            '--school-name' => 'Conflict School',
            '--admin-name' => 'Alex Admin',
            '--admin-email' => 'alex.conflict@example.sch.uk',
            '--admin-password' => 'password123',
            '--enable-flag' => [FeatureFlagKey::Connectors->value],
            '--disable-flag' => [FeatureFlagKey::Connectors->value],
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Cannot both enable and disable', Artisan::output());
        $this->assertSame(0, School::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
