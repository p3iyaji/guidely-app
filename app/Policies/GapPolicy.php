<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Gap;
use App\Domain\Tenancy\School;
use App\Models\User;

class GapPolicy
{
    /**
     * SENCO-only open Gaps list (school-scoped in the controller).
     */
    public function viewAny(User $user): bool
    {
        return $user->isActiveTenantStaff() && $user->role === Role::Senco;
    }

    /**
     * Whether the user may view a Gap for a Pupil in an accessible School.
     */
    public function view(User $user, Gap $gap): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $pupil = $this->pupilFor($gap);

        if ($pupil === null) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    private function pupilFor(Gap $gap): ?Pupil
    {
        if ($gap->relationLoaded('pupil') && $gap->pupil !== null) {
            return $gap->pupil;
        }

        return Pupil::query()->find($gap->pupil_id);
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
