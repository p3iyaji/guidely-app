<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class OnboardSchoolCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'guidely:onboard-school
                            {tenant_id : Existing Tenant ULID}
                            {--school-name= : School display name}
                            {--admin-name= : Tenant Admin display name}
                            {--admin-email= : Tenant Admin email}
                            {--admin-password= : Tenant Admin password}
                            {--enable-flag=* : Feature flag key to enable (repeatable)}
                            {--disable-flag=* : Feature flag key to disable (repeatable)}';

    /**
     * @var string
     */
    protected $description = 'Create a School under an existing Tenant, optionally toggle flags, and provision a Tenant Admin User. Warning: --admin-password may appear in shell history.';

    public function handle(FeatureFlagResolver $flags, AuditWriter $audit): int
    {
        $tenantId = (string) $this->argument('tenant_id');
        $schoolName = (string) ($this->option('school-name') ?? '');
        $adminName = (string) ($this->option('admin-name') ?? '');
        $adminEmail = Str::lower(trim((string) ($this->option('admin-email') ?? '')));
        $adminPassword = (string) ($this->option('admin-password') ?? '');
        /** @var list<string> $enableFlags */
        $enableFlags = array_values(array_filter((array) $this->option('enable-flag')));
        /** @var list<string> $disableFlags */
        $disableFlags = array_values(array_filter((array) $this->option('disable-flag')));

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $this->error("Tenant [{$tenantId}] was not found.");

            return self::FAILURE;
        }

        $validator = Validator::make(
            [
                'school_name' => $schoolName,
                'admin_name' => $adminName,
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
                'enable_flag' => $enableFlags,
                'disable_flag' => $disableFlags,
            ],
            [
                'school_name' => ['required', 'string', 'max:255', 'regex:/\S/'],
                'admin_name' => ['required', 'string', 'max:255', 'regex:/\S/'],
                'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'admin_password' => ['required', 'string', 'max:72', Password::defaults()],
                'enable_flag' => ['sometimes', 'array'],
                'enable_flag.*' => ['string', 'in:'.implode(',', FeatureFlagKey::values())],
                'disable_flag' => ['sometimes', 'array'],
                'disable_flag.*' => ['string', 'in:'.implode(',', FeatureFlagKey::values())],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $conflictingFlags = array_values(array_intersect($enableFlags, $disableFlags));

        if ($conflictingFlags !== []) {
            $this->error(
                'Cannot both enable and disable the same flag(s): '.implode(', ', $conflictingFlags).'.'
            );

            return self::FAILURE;
        }

        $cliRequest = Request::create('/', 'CONSOLE');

        [$school, $admin] = DB::transaction(function () use (
            $tenant,
            $schoolName,
            $adminName,
            $adminEmail,
            $adminPassword,
            $enableFlags,
            $disableFlags,
            $flags,
            $audit,
            $cliRequest,
        ): array {
            $school = new School;
            $school->forceFill([
                'tenant_id' => $tenant->id,
                'name' => $schoolName,
                'is_active' => true,
            ])->save();

            $audit->record(
                AuditEventType::SchoolCreated,
                $cliRequest,
                tenantId: $tenant->id,
                resourceType: 'school',
                resourceId: $school->id,
                metadata: [
                    'source' => 'guidely:onboard-school',
                ],
            );

            foreach ($enableFlags as $key) {
                $flags->set($tenant, FeatureFlagKey::from($key), true);
                $audit->record(
                    AuditEventType::FeatureFlagUpdated,
                    $cliRequest,
                    tenantId: $tenant->id,
                    resourceType: 'tenant',
                    resourceId: $tenant->id,
                    metadata: [
                        'source' => 'guidely:onboard-school',
                        'flag_key' => $key,
                        'enabled' => true,
                    ],
                );
            }

            foreach ($disableFlags as $key) {
                $flags->set($tenant, FeatureFlagKey::from($key), false);
                $audit->record(
                    AuditEventType::FeatureFlagUpdated,
                    $cliRequest,
                    tenantId: $tenant->id,
                    resourceType: 'tenant',
                    resourceId: $tenant->id,
                    metadata: [
                        'source' => 'guidely:onboard-school',
                        'flag_key' => $key,
                        'enabled' => false,
                    ],
                );
            }

            $admin = new User;
            $admin->forceFill([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
                'tenant_id' => $tenant->id,
                'role' => Role::TenantAdmin,
            ])->save();

            $admin->schools()->sync([$school->id]);

            $audit->record(
                AuditEventType::UserCreated,
                $cliRequest,
                tenantId: $tenant->id,
                resourceType: 'user',
                resourceId: (string) $admin->id,
                metadata: [
                    'source' => 'guidely:onboard-school',
                ],
            );

            return [$school, $admin];
        });

        $this->info("School [{$school->id}] created under Tenant [{$tenant->id}].");
        $this->info("Tenant Admin [{$admin->id}] provisioned ({$admin->email}).");

        if ($enableFlags !== [] || $disableFlags !== []) {
            $this->info('Feature flags updated: '.json_encode($flags->mapFor($tenant->refresh())));
        }

        return self::SUCCESS;
    }
}
