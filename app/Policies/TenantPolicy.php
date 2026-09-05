<?php

namespace App\Policies;

use App\Domain\Tenancy\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isActiveTenantStaff()
            && $user->tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user can update the model (cohort / flags).
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin()
            && $user->tenant_id === $tenant->id;
    }
}
