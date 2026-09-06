<?php

namespace App\Providers;

use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
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
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('create-pilot-tenant', fn (User $user): bool => $user->isPlatformOperator() && ! $user->isDeactivated());
        Gate::define('view-pilot-toolkit', fn (User $user): bool => $user->isActiveTenantStaff() && $user->isTenantAdmin());

        // Future addendum rows — deny by default until those domains ship.
        Gate::define('capture-evidence', fn (): bool => false);
        Gate::define('view-evidence', fn (): bool => false);
        Gate::define('run-determinations', fn (): bool => false);
        Gate::define('override-determination', fn (): bool => false);
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
