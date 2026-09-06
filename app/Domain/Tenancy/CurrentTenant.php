<?php

namespace App\Domain\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CurrentTenant
{
    /**
     * Resolve the current Tenant id from the authenticated User.
     */
    public static function id(): ?string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->tenant_id;
    }

    /**
     * Whether a current Tenant context is available.
     */
    public static function check(): bool
    {
        return static::id() !== null;
    }

    /**
     * Resolve the current Tenant model or fail with 404 (no / missing Tenant).
     */
    public static function require(): Tenant
    {
        $tenantId = static::id();

        if ($tenantId === null) {
            throw new NotFoundHttpException;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            throw new NotFoundHttpException;
        }

        return $tenant;
    }
}
