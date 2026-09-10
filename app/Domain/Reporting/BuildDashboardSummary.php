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
use Illuminate\Support\Str;

class BuildDashboardSummary
{
    private const ACTION_ITEM_LIMIT = 6;

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
            actionItems: $this->actionItems($user, $windowDays),
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
        return $this->draftQuery($user)->count();
    }

    /**
     * @return list<array{
     *     type: 'review_cycle'|'gap'|'draft',
     *     id: string,
     *     title: string,
     *     detail: string,
     *     href: string,
     *     priority: 'overdue'|'due'|'gap'|'draft',
     *     date: ?string
     * }>
     */
    private function actionItems(User $user, int $windowDays): array
    {
        $items = [];

        if ($user->can('viewReviewCycles', DashboardSummary::class)) {
            $items = array_merge($items, $this->reviewCycleActionItems($user, $windowDays));
        }

        if ($user->can('viewOpenGaps', DashboardSummary::class)) {
            $items = array_merge($items, $this->gapActionItems($user));
        }

        if ($user->can('viewDrafts', DashboardSummary::class)) {
            $items = array_merge($items, $this->draftActionItems($user));
        }

        return array_slice($items, 0, self::ACTION_ITEM_LIMIT);
    }

    /**
     * @return list<array{
     *     type: 'review_cycle',
     *     id: string,
     *     title: string,
     *     detail: string,
     *     href: string,
     *     priority: 'overdue'|'due',
     *     date: string
     * }>
     */
    private function reviewCycleActionItems(User $user, int $windowDays): array
    {
        $today = now('Europe/London')->toDateString();
        $horizon = now('Europe/London')->addDays($windowDays)->toDateString();

        return ReviewCycle::query()
            ->open()
            ->dueOnOrBefore($horizon)
            ->whereHas('pupil', fn (Builder $query) => $this->scopePupilsToUser($query, $user))
            ->with('pupil:id,given_name,family_name')
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(self::ACTION_ITEM_LIMIT)
            ->get()
            ->map(function (ReviewCycle $cycle) use ($today, $windowDays): array {
                $pupilName = $this->pupilName($cycle->pupil);
                $dueOn = $cycle->due_on->toDateString();
                $priority = $dueOn < $today ? 'overdue' : 'due';

                return [
                    'type' => 'review_cycle',
                    'id' => $cycle->id,
                    'title' => "{$cycle->type->label()} for {$pupilName}",
                    'detail' => ($priority === 'overdue' ? 'Overdue' : 'Due')." · {$dueOn}",
                    'href' => '/review-cycles?'.http_build_query([
                        'q' => $pupilName,
                        'window' => $windowDays,
                    ], encoding_type: PHP_QUERY_RFC3986),
                    'priority' => $priority,
                    'date' => $dueOn,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     type: 'gap',
     *     id: string,
     *     title: string,
     *     detail: string,
     *     href: string,
     *     priority: 'gap',
     *     date: ?string
     * }>
     */
    private function gapActionItems(User $user): array
    {
        return Gap::query()
            ->open()
            ->whereHas('pupil', fn (Builder $query) => $this->scopePupilsToUser($query, $user))
            ->with('pupil:id,given_name,family_name')
            ->oldest()
            ->orderBy('id')
            ->limit(self::ACTION_ITEM_LIMIT)
            ->get()
            ->map(function (Gap $gap): array {
                $query = array_filter([
                    'focus' => 'determination',
                    'determination' => $gap->determination_id,
                    'gap' => $gap->id,
                ]);

                return [
                    'type' => 'gap',
                    'id' => $gap->id,
                    'title' => "Open gap for {$this->pupilName($gap->pupil)}",
                    'detail' => $gap->dimension->value,
                    'href' => "/pupils/{$gap->pupil_id}?".http_build_query(
                        $query,
                        encoding_type: PHP_QUERY_RFC3986,
                    ),
                    'priority' => 'gap',
                    'date' => $gap->created_at?->toDateString(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     type: 'draft',
     *     id: string,
     *     title: string,
     *     detail: string,
     *     href: string,
     *     priority: 'draft',
     *     date: ?string
     * }>
     */
    private function draftActionItems(User $user): array
    {
        return $this->draftQuery($user)
            ->with('pupil:id,given_name,family_name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::ACTION_ITEM_LIMIT)
            ->get()
            ->map(fn (EvidenceRecord $draft): array => [
                'type' => 'draft',
                'id' => $draft->id,
                'title' => "Continue draft for {$this->pupilName($draft->pupil)}",
                'detail' => Str::headline($draft->type->value).' draft',
                'href' => '/capture?'.http_build_query(
                    ['draft' => $draft->id],
                    encoding_type: PHP_QUERY_RFC3986,
                ),
                'priority' => 'draft',
                'date' => $draft->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Builder<EvidenceRecord>
     */
    private function draftQuery(User $user): Builder
    {
        $query = EvidenceRecord::query()
            ->where('lifecycle', EvidenceLifecycle::Draft)
            ->whereHas(
                'pupil',
                fn (Builder $pupils) => $this->scopePupilsToUser($pupils, $user)
            );

        if ($user->role !== Role::Senco) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    private function pupilName(Pupil $pupil): string
    {
        return trim("{$pupil->given_name} {$pupil->family_name}");
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
