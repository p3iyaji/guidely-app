<?php

namespace App\Policies;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
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

    /**
     * Draft list for capture Roles (scope applied in the controller).
     */
    public function listDrafts(User $user): bool
    {
        return $this->canCaptureEvidence($user);
    }

    /**
     * Author or School SENCO may view a draft; submitted records are Evidence Base (later).
     */
    public function view(User $user, EvidenceRecord $record): bool
    {
        if ($record->lifecycle !== EvidenceLifecycle::Draft) {
            return false;
        }

        if (! $this->canCaptureEvidence($user)) {
            return false;
        }

        $pupil = $this->pupilFor($record);

        if ($pupil === null || ! $this->canAccessPupilSchool($user, $pupil)) {
            return false;
        }

        if ((string) $record->author_id === (string) $user->id) {
            return true;
        }

        return $user->role === Role::Senco;
    }

    /**
     * Draft update is author-only in Pilot (SENCO is view-only for others' drafts).
     */
    public function update(User $user, EvidenceRecord $record): bool
    {
        return $this->authorOwnsDraft($user, $record);
    }

    /**
     * Draft submit is author-only; same Pilot rule as update.
     */
    public function submit(User $user, EvidenceRecord $record): bool
    {
        return $this->authorOwnsDraft($user, $record);
    }

    private function authorOwnsDraft(User $user, EvidenceRecord $record): bool
    {
        if ($record->lifecycle !== EvidenceLifecycle::Draft) {
            return false;
        }

        if ((string) $record->author_id !== (string) $user->id) {
            return false;
        }

        $pupil = $this->pupilFor($record);

        if ($pupil === null) {
            return false;
        }

        return $this->createForPupil($user, $pupil);
    }

    private function pupilFor(EvidenceRecord $record): ?Pupil
    {
        if ($record->relationLoaded('pupil')) {
            return $record->pupil;
        }

        return Pupil::query()->find($record->pupil_id);
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
