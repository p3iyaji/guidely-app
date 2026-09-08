<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Models\User;

class ComplianceAlertPolicy
{
    /**
     * SENCO, Trust SEND Lead, and Trust Executive may list open Indicator alerts.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Senco,
            Role::TrustSendLead,
            Role::TrustExecutive,
        ], true);
    }

    /**
     * Tenant Admin may read and write Indicator alert thresholds.
     */
    public function updateThresholds(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return $user->role === Role::TenantAdmin;
    }
}
