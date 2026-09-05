<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $model);
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
    public function update(User $user, User $model): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $model);
    }

    /**
     * Determine whether the user can reset the model's password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $model);
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, User $model): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $model);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function sameTenant(User $user, User $model): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $model->tenant_id;
    }
}
