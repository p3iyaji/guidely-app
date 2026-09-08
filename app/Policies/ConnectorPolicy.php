<?php

namespace App\Policies;

use App\Domain\Connectors\Connector;
use App\Models\User;

class ConnectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, Connector $connector): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $this->sameTenant($user, $connector);
    }

    public function create(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function update(User $user, Connector $connector): bool
    {
        return $this->view($user, $connector);
    }

    public function sync(User $user, Connector $connector): bool
    {
        return $this->update($user, $connector);
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }

    private function sameTenant(User $user, Connector $connector): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $connector->tenant_id;
    }
}
