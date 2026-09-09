<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Models\User;

class DashboardSummaryPolicy
{
    public function view(User $user): bool
    {
        return $user->isActiveTenantStaff();
    }

    public function viewPupils(User $user): bool
    {
        return $this->view($user) && in_array($user->role, [
            Role::Teacher,
            Role::SupportStaff,
            Role::Senco,
            Role::SchoolLeader,
            Role::TenantAdmin,
            Role::TrustSendLead,
            Role::TrustExecutive,
        ], true);
    }

    public function viewOpenGaps(User $user): bool
    {
        return $this->view($user) && $user->role === Role::Senco;
    }

    public function viewReviewCycles(User $user): bool
    {
        return $this->view($user) && in_array($user->role, [
            Role::Senco,
            Role::SchoolLeader,
        ], true);
    }

    public function viewDrafts(User $user): bool
    {
        return $this->view($user) && in_array($user->role, [
            Role::Teacher,
            Role::SupportStaff,
            Role::Senco,
        ], true);
    }
}
