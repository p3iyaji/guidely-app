<?php

namespace App\Policies;

use App\Domain\Tenancy\School;
use App\Models\User;

class SchoolPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActiveTenantStaff();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, School $school): bool
    {
        return $user->canAccessSchool($school);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, School $school): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $school);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, School $school): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $school);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function sameTenant(User $user, School $school): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $school->tenant_id;
    }
}
