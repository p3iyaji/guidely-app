<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Models\User;

class EvidenceRecordPolicy
{
    /**
     * Whether the user may capture Evidence (role gate — Pupil scope checked separately).
     */
    public function create(User $user): bool
    {
        return $this->canCaptureEvidence($user);
    }

    /**
     * Whether the user may create Evidence against a specific Pupil.
     */
    public function createForPupil(User $user, Pupil $pupil): bool
    {
        if (! $this->canCaptureEvidence($user) || ! $this->canAccessPupilSchool($user, $pupil)) {
            return false;
        }

        if ($user->role === Role::Senco) {
            return true;
        }

        if ($user->isAssignmentScopedForPupils()) {
            return $pupil->isAssignedTo($user);
        }

        return false;
    }

    private function canCaptureEvidence(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Teacher,
            Role::SupportStaff,
            Role::Senco,
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
