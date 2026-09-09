<?php

namespace App\Policies;

use App\Domain\Identity\AccessPermission;
use App\Models\User;

class AccessPermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, AccessPermission $accessPermission): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->isVisible($user, $accessPermission);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, AccessPermission $accessPermission): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->isVisible($user, $accessPermission);
    }

    public function delete(User $user, AccessPermission $accessPermission): bool
    {
        return $this->update($user, $accessPermission);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function isVisible(User $user, AccessPermission $accessPermission): bool
    {
        if ($accessPermission->tenant_id === null) {
            return true;
        }

        return $user->tenant_id !== null
            && $user->tenant_id === $accessPermission->tenant_id;
    }
}
