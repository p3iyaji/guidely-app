<?php

namespace App\Providers;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use App\Policies\DeterminationPolicy;
use App\Policies\EvidenceRecordPolicy;
use App\Policies\GapPolicy;
use App\Policies\PupilPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(Pupil::class, PupilPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(EvidenceRecord::class, EvidenceRecordPolicy::class);
        Gate::policy(Gap::class, GapPolicy::class);
        Gate::policy(Determination::class, DeterminationPolicy::class);

        Gate::define('create-pilot-tenant', fn (User $user): bool => $user->isPlatformOperator() && ! $user->isDeactivated());
        Gate::define('view-pilot-toolkit', fn (User $user): bool => $user->isActiveTenantStaff() && $user->isTenantAdmin());
        Gate::define('import-pupils', function (User $user): bool {
            if (! $user->isActiveTenantStaff()) {
                return false;
            }

            return $user->role === Role::Senco
                || $user->role === Role::TenantAdmin;
        });

        Gate::define('capture-evidence', function (User $user): bool {
            return $user->can('create', EvidenceRecord::class);
        });

        Gate::define('view-evidence', function (User $user): bool {
            return $user->can('viewEvidenceBase', EvidenceRecord::class);
        });

        // Future addendum rows — deny by default until those domains ship.
        Gate::define('run-determinations', fn (): bool => false);
        Gate::define('override-determination', function (User $user, mixed $determination = null): bool {
            if ($determination instanceof Determination) {
                return $user->can('override', $determination);
            }

            if (! $user->isActiveTenantStaff()) {
                return false;
            }

            return in_array($user->role, [
                Role::Senco,
                Role::SchoolLeader,
            ], true);
        });
        Gate::define('documentation-output', fn (): bool => false);

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $emailKey = is_string($email) ? Str::lower($email) : '';

            return Limit::perMinute(5)->by(Str::transliterate(
                $emailKey.'|'.$request->ip()
            ));
        });
    }
}
