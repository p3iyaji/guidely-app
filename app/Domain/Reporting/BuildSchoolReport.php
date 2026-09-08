<?php

namespace App\Domain\Reporting;

use App\Domain\Pupils\DocumentationStatus;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BuildSchoolReport
{
    public function handle(User $user, int $windowDays, ?string $schoolId = null): SchoolReport
    {
        $byStatus = $this->emptyStatusCounts();

        foreach ($this->statusCounts($user, $schoolId) as $status => $count) {
            if (! array_key_exists($status, $byStatus)) {
                continue;
            }

            $byStatus[$status] = (int) $count;
        }

        $pupils = $this->inScopePupils($user, $schoolId);
        $cyclesDue = $this->cyclesDue($user, $windowDays, $schoolId);

        return new SchoolReport(
            pupilsInScope: $pupils->count(),
            byStatus: $byStatus,
            ready: $byStatus[DocumentationStatus::Ready->value],
            gaps: $byStatus[DocumentationStatus::Gaps->value],
            reviewCyclesDue: $cyclesDue->count(),
            windowDays: $windowDays,
            pupils: $pupils
                ->map(fn (Pupil $pupil): array => [
                    'id' => $pupil->id,
                    'given_name' => $pupil->given_name,
                    'family_name' => $pupil->family_name,
                    'documentation_status' => $pupil->documentation_status instanceof DocumentationStatus
                        ? $pupil->documentation_status->value
                        : (string) $pupil->documentation_status,
                ])
                ->values()
                ->all(),
            cyclesDue: $cyclesDue
                ->map(fn (ReviewCycle $cycle): array => [
                    'id' => $cycle->id,
                    'given_name' => $cycle->pupil?->given_name ?? '',
                    'family_name' => $cycle->pupil?->family_name ?? '',
                    'due_on' => $cycle->due_on?->toDateString() ?? '',
                    'type_label' => $cycle->type?->label() ?? '',
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{ready: int, gaps: int, uncovered: int, not-started: int, evaluating: int}
     */
    private function emptyStatusCounts(): array
    {
        return [
            DocumentationStatus::Ready->value => 0,
            DocumentationStatus::Gaps->value => 0,
            DocumentationStatus::Uncovered->value => 0,
            DocumentationStatus::NotStarted->value => 0,
            DocumentationStatus::Evaluating->value => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(User $user, ?string $schoolId): array
    {
        return Pupil::query()
            ->tap(fn (Builder $query) => $this->scopePupilsToUser($query, $user, $schoolId))
            ->toBase()
            ->selectRaw('documentation_status as status, count(*) as aggregate')
            ->groupBy('documentation_status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return Collection<int, Pupil>
     */
    private function inScopePupils(User $user, ?string $schoolId): Collection
    {
        return Pupil::query()
            ->tap(fn (Builder $query) => $this->scopePupilsToUser($query, $user, $schoolId))
            ->select(['id', 'given_name', 'family_name', 'documentation_status'])
            ->orderBy('family_name')
            ->orderBy('given_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ReviewCycle>
     */
    private function cyclesDue(User $user, int $windowDays, ?string $schoolId): Collection
    {
        $horizon = now('Europe/London')->addDays($windowDays)->toDateString();

        return ReviewCycle::query()
            ->open()
            ->dueOnOrBefore($horizon)
            ->with('pupil:id,given_name,family_name')
            ->whereHas('pupil', function (Builder $pupils) use ($user, $schoolId): void {
                $this->scopePupilsToUser($pupils, $user, $schoolId);
            })
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * School-scope Pupils the same way as PupilController@index.
     *
     * @param  Builder<Pupil>  $query
     */
    private function scopePupilsToUser(Builder $query, User $user, ?string $schoolId = null): void
    {
        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

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
