<?php

namespace App\Domain\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CurrentTenant
{
    private static ?string $boundId = null;

    /**
     * Resolve the current Tenant id from the authenticated User.
     * When no User is present, a job-scoped bind (see using()) is used.
     */
    public static function id(): ?string
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->tenant_id;
        }

        return static::$boundId;
    }

    /**
     * Bind a Tenant id for the duration of a callback (queue workers / sync jobs).
     * Auth still wins when a User is present.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function using(string $tenantId, callable $callback): mixed
    {
        $previous = static::$boundId;
        static::$boundId = $tenantId;

        try {
            return $callback();
        } finally {
            static::$boundId = $previous;
        }
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
