<?php

namespace App\Policies;

use App\Domain\Identity\AccessRole;
use App\Models\User;

class AccessRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, AccessRole $accessRole): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->isVisible($user, $accessRole);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, AccessRole $accessRole): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->isVisible($user, $accessRole);
    }

    public function delete(User $user, AccessRole $accessRole): bool
    {
        return $this->update($user, $accessRole);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function isVisible(User $user, AccessRole $accessRole): bool
    {
        if ($accessRole->tenant_id === null) {
            return true;
        }

        return $user->tenant_id !== null
            && $user->tenant_id === $accessRole->tenant_id;
    }
}
