<?php

namespace App\Domain\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
}
