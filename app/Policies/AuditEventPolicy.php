<?php

namespace App\Policies;

use App\Domain\Audit\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveTenantAdmin($user);
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $this->isActiveTenantAdmin($user)
            && $user->tenant_id !== null
            && $user->tenant_id === $auditEvent->tenant_id;
    }

    private function isActiveTenantAdmin(User $user): bool
    {
        return $user->isActiveTenantStaff()
            && $user->isTenantAdmin();
    }
}
