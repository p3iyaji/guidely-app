<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Models\User;

class SafeguardingSignalPolicy
{
    /**
     * SENCO and School Leader may list current context signals.
     */
    public function viewAny(User $user): bool
    {
        return $this->isContextRole($user);
    }

    /**
     * Upsert is limited to in-scope Pupils at active Schools.
     */
    public function upsert(User $user, Pupil $pupil): bool
    {
        if (! $this->isContextRole($user)) {
            return false;
        }

        $school = $pupil->relationLoaded('school')
            ? $pupil->school
            : School::query()->find($pupil->school_id);

        if ($school === null || ! $school->is_active) {
            return false;
        }

        return $user->canAccessSchool($school);
    }

    private function isContextRole(User $user): bool
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
