<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Models\User;

class SchoolReportPolicy
{
    /**
     * SENCO or School Leader may view the School Report (school-scoped in the builder).
     */
    public function view(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Senco,
            Role::SchoolLeader,
        ], true);
    }
}
