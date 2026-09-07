<?php

namespace Database\Seeders;

use App\Domain\Identity\Role;
use App\Domain\Ontology\PilotRuleLibrary;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic credentials for Playwright browser smoke tests.
 */
class E2eSeeder extends Seeder
{
    public const EMAIL = 'e2e.admin@example.sch.uk';

    public const PASSWORD = 'password';

    public const SCHOOL_NAME = 'E2E Primary';

    public function run(): void
    {
        $this->call([
            RuleLibrarySeeder::class,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'E2E Pilot School',
            'type' => TenantType::School,
            'cohort_enabled' => false,
            'cohort_label' => null,
        ]);

        $school = new School;
        $school->forceFill([
            'tenant_id' => $tenant->id,
            'name' => self::SCHOOL_NAME,
            'is_active' => true,
        ])->save();

        $admin = new User;
        $admin->forceFill([
            'name' => 'E2E Admin',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'tenant_id' => $tenant->id,
            'role' => Role::TenantAdmin,
        ])->save();

        $admin->schools()->sync([$school->id]);

        $pilotRuleLibrary = PilotRuleLibrary::ensurePublishedVersion();
        $tenant->forceFill([
            'current_rule_library_version_id' => $pilotRuleLibrary->id,
        ])->save();
    }
}
