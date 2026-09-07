<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\School;
use App\Models\User;

class ReviewCyclePolicy
{
    /**
     * SENCO or School Leader may list Review Cycles (school-scoped in the controller).
     */
    public function viewAny(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Senco,
            Role::SchoolLeader,
        ], true);
    }

    /**
     * Whether the user may view a Review Cycle for a Pupil in an accessible School.
     */
    public function view(User $user, ReviewCycle $reviewCycle): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $pupil = $this->pupilFor($reviewCycle);

        if ($pupil === null) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * SENCO with Pupil school access may create a Review Cycle.
     */
    public function create(User $user, Pupil $pupil): bool
    {
        if (! $this->isSenco($user)) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * SENCO with Pupil school access may close a Review Cycle.
     */
    public function close(User $user, ReviewCycle $reviewCycle): bool
    {
        if (! $this->isSenco($user)) {
            return false;
        }

        $pupil = $this->pupilFor($reviewCycle);

        if ($pupil === null) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    private function isSenco(User $user): bool
    {
        return $user->isActiveTenantStaff() && $user->role === Role::Senco;
    }

    private function pupilFor(ReviewCycle $reviewCycle): ?Pupil
    {
        if ($reviewCycle->relationLoaded('pupil') && $reviewCycle->pupil !== null) {
            return $reviewCycle->pupil;
        }

        return Pupil::query()->find($reviewCycle->pupil_id);
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
