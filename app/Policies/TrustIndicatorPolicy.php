<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Models\User;

class TrustIndicatorPolicy
{
    /**
     * Trust SEND Lead or Trust Executive may view Trust Indicators when they are active Tenant staff.
     */
    public function view(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::TrustSendLead,
            Role::TrustExecutive,
        ], true);
    }
}
