<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Models\User;

class DocumentationOutputPolicy
{
    /**
     * SENCO or School Leader may list Documentation Outputs (school-scoped in the controller).
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
     * Whether the user may view a Documentation Output for a Pupil in an accessible School.
     */
    public function view(User $user, DocumentationOutput $documentationOutput): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $pupil = $this->pupilFor($documentationOutput);

        if ($pupil === null) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * SENCO with Pupil school access may generate a Documentation Output.
     */
    public function create(User $user, Pupil $pupil): bool
    {
        if (! $this->isSenco($user)) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    private function isSenco(User $user): bool
    {
        return $user->isActiveTenantStaff() && $user->role === Role::Senco;
    }

    private function pupilFor(DocumentationOutput $documentationOutput): ?Pupil
    {
        if ($documentationOutput->relationLoaded('pupil') && $documentationOutput->pupil !== null) {
            return $documentationOutput->pupil;
        }

        return Pupil::query()->find($documentationOutput->pupil_id);
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
