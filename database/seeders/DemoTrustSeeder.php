<?php

namespace Database\Seeders;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Minimal Trust Tenant so Trust Role shells and trust_dashboard flag can be exercised.
 *
 * Password for every seeded staff User: {@see self::PASSWORD}
 */
class DemoTrustSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const TENANT_NAME = 'Guidely Demo Trust';

    /**
     * @var array<string, string>
     */
    public const USERS = [
        'send_lead' => 'send.lead@demo.trust.uk',
        'executive' => 'executive@demo.trust.uk',
        'admin' => 'trust.admin@demo.trust.uk',
    ];

    public function run(): void
    {
        $tenant = Tenant::factory()->trust()->create([
            'name' => self::TENANT_NAME,
        ]);

        $school = School::factory()->forTenant($tenant)->create([
            'name' => 'Trust Hub Academy',
        ]);

        app(FeatureFlagResolver::class)->set($tenant, FeatureFlagKey::TrustDashboard, true);

        $sendLead = $this->user($tenant, Role::TrustSendLead, 'Demo Trust SEND Lead', self::USERS['send_lead']);
        $executive = $this->user($tenant, Role::TrustExecutive, 'Demo Trust Executive', self::USERS['executive']);
        $admin = $this->user($tenant, Role::TenantAdmin, 'Demo Trust Admin', self::USERS['admin']);

        $sendLead->schools()->sync([$school->id]);
        $executive->schools()->sync([$school->id]);
        $admin->schools()->sync([$school->id]);

        $this->command?->info('Demo Trust seeded (trust_dashboard on). Password "'.self::PASSWORD.'":');
        foreach (self::USERS as $label => $email) {
            $this->command?->line("  - {$label}: {$email}");
        }
    }

    private function user(Tenant $tenant, Role $role, string $name, string $email): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'tenant_id' => $tenant->id,
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
