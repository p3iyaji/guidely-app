<?php

namespace App\Policies;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
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
     * SENCO-only create of submitted review notes for Pupils in accessible Schools.
     */
    public function createReviewNote(User $user, Pupil $pupil): bool
    {
        if (! $user->isActiveTenantStaff() || $user->role !== Role::Senco) {
            return false;
        }

        return $this->canAccessPupilSchool($user, $pupil);
    }

    /**
     * Draft list for capture Roles (scope applied in the controller).
     */
    public function listDrafts(User $user): bool
    {
        return $this->canCaptureEvidence($user);
    }

    /**
     * Role-level Evidence Base access (Teachers, Support, SENCO, School Leader).
     */
    public function viewEvidenceBase(User $user): bool
    {
        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        return in_array($user->role, [
            Role::Teacher,
            Role::SupportStaff,
            Role::Senco,
            Role::SchoolLeader,
        ], true);
    }

    /**
     * Whether the user may list submitted Evidence for a Pupil (Evidence Base).
     */
    public function listForPupil(User $user, Pupil $pupil): bool
    {
        if (! $this->viewEvidenceBase($user) || ! $this->canAccessPupilSchool($user, $pupil)) {
            return false;
        }

        if (in_array($user->role, [Role::Senco, Role::SchoolLeader], true)) {
            return true;
        }

        if ($user->isAssignmentScopedForPupils()) {
            return $pupil->isAssignedTo($user);
        }

        return false;
    }

    /**
     * Draft: author or School SENCO. Submitted: Evidence Base Role + Pupil scope.
     */
    public function view(User $user, EvidenceRecord $record): bool
    {
        if ($record->lifecycle === EvidenceLifecycle::Draft) {
            return $this->viewDraft($user, $record);
        }

        if ($record->lifecycle !== EvidenceLifecycle::Submitted) {
            return false;
        }

        $pupil = $this->pupilFor($record);

        if ($pupil === null) {
            return false;
        }

        return $this->listForPupil($user, $pupil);
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

    /**
     * Amend submitted Observation/Intervention/Response: author (assignment-scoped)
     * or School SENCO. School Leader and non-capture Roles are denied.
     */
    public function amend(User $user, EvidenceRecord $record): bool
    {
        if ($record->lifecycle !== EvidenceLifecycle::Submitted) {
            return false;
        }

        if (! in_array($record->type, [
            EvidenceType::Observation,
            EvidenceType::Intervention,
            EvidenceType::Response,
        ], true)) {
            return false;
        }

        if (! $user->isActiveTenantStaff()) {
            return false;
        }

        $pupil = $this->pupilFor($record);

        if ($pupil === null || ! $this->canAccessPupilSchool($user, $pupil)) {
            return false;
        }

        if ($user->role === Role::Senco) {
            return true;
        }

        if (! in_array($user->role, [Role::Teacher, Role::SupportStaff], true)) {
            return false;
        }

        if ((string) $record->author_id !== (string) $user->id) {
            return false;
        }

        return $pupil->isAssignedTo($user);
    }

    private function viewDraft(User $user, EvidenceRecord $record): bool
    {
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
        if ($record->relationLoaded('pupil') && $record->pupil !== null) {
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
