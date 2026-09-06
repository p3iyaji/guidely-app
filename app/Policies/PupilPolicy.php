<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Models\User;

class PupilPolicy
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
    public function view(User $user, Pupil $pupil): bool
    {
        return $this->canViewPupils($user)
            && $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canMutatePupils($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pupil $pupil): bool
    {
        return $this->canMutatePupils($user)
            && $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * Determine whether the user can soft-delete the model.
     */
    public function delete(User $user, Pupil $pupil): bool
    {
        return $this->canMutatePupils($user)
            && $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * Whether the user may include soft-deleted (left) Pupils in listings.
     */
    public function viewLeft(User $user): bool
    {
        return $this->canMutatePupils($user);
    }

    private function canMutatePupils(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return $user->role === Role::Senco
            || $user->role === Role::TenantAdmin;
    }

    private function canViewPupils(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Senco,
            Role::TenantAdmin,
            Role::SchoolLeader,
        ], true);
    }

    private function canAccessPupilSchool(User $user, Pupil $pupil): bool
    {
        $school = $pupil->relationLoaded('school')
            ? $pupil->school
            : School::query()->find($pupil->school_id);

        if ($school === null) {
            return false;
        }

        return $user->canAccessSchool($school);
    }
}
