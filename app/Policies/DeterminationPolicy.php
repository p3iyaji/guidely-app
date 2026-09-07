<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\Determination;
use App\Domain\Tenancy\School;
use App\Models\User;

class DeterminationPolicy
{
    /**
     * Role-level access to Determinations / Reasoning Pathway surfaces.
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
     * Whether the user may list Determinations for a Pupil (Evidence Base pathway).
     */
    public function listForPupil(User $user, Pupil $pupil): bool
    {
        if (! $this->viewAny($user) || ! $this->canAccessPupilSchool($user, $pupil)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the user may view a Determination for a Pupil in an accessible School.
     */
    public function view(User $user, Determination $determination): bool
    {
        $pupil = $this->pupilFor($determination);

        if ($pupil === null) {
            return false;
        }

        return $this->listForPupil($user, $pupil);
    }

    /**
     * SENCO or School Leader with Pupil school access may append an Override.
     */
    public function override(User $user, Determination $determination): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        if (! in_array($user->role, [
            Role::Senco,
            Role::SchoolLeader,
        ], true)) {
            return false;
        }

        $pupil = $this->pupilFor($determination);

        if ($pupil === null) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    private function pupilFor(Determination $determination): ?Pupil
    {
        if ($determination->relationLoaded('pupil') && $determination->pupil !== null) {
            return $determination->pupil;
        }

        return Pupil::query()->find($determination->pupil_id);
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
