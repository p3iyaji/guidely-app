<?php

namespace App\Domain\Reporting;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Sre\Gap;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuildDashboardSummary
{
    public function handle(User $user, int $windowDays): DashboardSummary
    {
        return new DashboardSummary(
            pupilsInScope: $user->can('viewPupils', DashboardSummary::class)
                ? $this->pupilsInScope($user)
                : null,
            openGaps: $user->can('viewOpenGaps', DashboardSummary::class)
                ? $this->openGaps($user)
                : null,
            reviewCyclesDue: $user->can('viewReviewCycles', DashboardSummary::class)
                ? $this->reviewCyclesDue($user, $windowDays)
                : null,
            drafts: $user->can('viewDrafts', DashboardSummary::class)
                ? $this->drafts($user)
                : null,
            windowDays: $windowDays,
        );
    }

    private function pupilsInScope(User $user): int
    {
        if ($user->role?->isTrustRole()) {
            return 0;
        }

        return Pupil::query()
            ->tap(fn (Builder $query) => $this->scopePupilsToUser($query, $user))
            ->count();
    }

    private function openGaps(User $user): int
    {
        return Gap::query()
            ->open()
            ->whereHas('pupil', fn (Builder $query) => $this->scopePupilsToUser($query, $user))
            ->count();
    }

    private function reviewCyclesDue(User $user, int $windowDays): int
    {
        $horizon = now('Europe/London')->addDays($windowDays)->toDateString();

        return ReviewCycle::query()
            ->open()
            ->dueOnOrBefore($horizon)
            ->whereHas('pupil', fn (Builder $query) => $this->scopePupilsToUser($query, $user))
            ->count();
    }

    private function drafts(User $user): int
    {
        $query = EvidenceRecord::query()
            ->where('lifecycle', EvidenceLifecycle::Draft)
            ->whereHas('pupil');

        if ($user->role === Role::Senco) {
            $query->whereHas(
                'pupil',
                fn (Builder $pupils) => $this->scopePupilsToUser($pupils, $user)
            );
        } else {
            $query->where('author_id', $user->id);
        }

        return $query->count();
    }

    /**
     * Scope Pupils exactly as the live list endpoints do for supported dashboard Roles.
     *
     * @param  Builder<Pupil>  $query
     */
    private function scopePupilsToUser(Builder $query, User $user): void
    {
        if ($user->isAssignmentScopedForPupils()) {
            $query->whereIn('school_id', $user->schools()->allRelatedIds())
                ->whereHas(
                    'assignedUsers',
                    fn (Builder $assignees) => $assignees->whereKey($user->id)
                );

            return;
        }

        if (! $user->seesAllTenantSchools()) {
            $query->whereIn('school_id', $user->schools()->allRelatedIds());
        }
    }
}
