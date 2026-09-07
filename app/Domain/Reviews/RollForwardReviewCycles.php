<?php

namespace App\Domain\Reviews;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RollForwardReviewCycles
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    /**
     * Create the next open Annual Review for Pupils whose latest Annual Review anniversary is due.
     *
     * @return Collection<int, ReviewCycle>
     */
    public function handle(Tenant $tenant, Request $request, ?string $source = null): Collection
    {
        $today = now('Europe/London')->toDateString();
        $pupils = Pupil::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($pupils->isEmpty()) {
            return collect();
        }

        $annualsByPupilId = ReviewCycle::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('type', ReviewCycleType::AnnualReview)
            ->whereIn('pupil_id', $pupils->modelKeys())
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy('pupil_id');

        $created = collect();

        foreach ($pupils as $pupil) {
            $cycle = $this->rollForwardForPupil(
                $tenant,
                $pupil,
                $annualsByPupilId->get($pupil->id, collect()),
                $today,
                $request,
                $source,
            );

            if ($cycle instanceof ReviewCycle) {
                $created->push($cycle);
            }
        }

        return $created;
    }

    /**
     * @param  Collection<int, ReviewCycle>  $annuals
     */
    private function rollForwardForPupil(
        Tenant $tenant,
        Pupil $pupil,
        Collection $annuals,
        string $today,
        Request $request,
        ?string $source,
    ): ?ReviewCycle {
        if ($annuals->isEmpty()) {
            return null;
        }

        if ($annuals->contains(
            fn (ReviewCycle $cycle): bool => $cycle->status === ReviewCycleStatus::Open,
        )) {
            return null;
        }

        $latest = $annuals->first();

        if ($latest === null || $latest->due_on === null) {
            return null;
        }

        $anniversary = Carbon::parse($latest->due_on->toDateString(), 'Europe/London')
            ->addYearNoOverflow()
            ->toDateString();

        if ($anniversary > $today) {
            return null;
        }

        if ($annuals->contains(
            fn (ReviewCycle $cycle): bool => $cycle->due_on?->toDateString() === $anniversary,
        )) {
            return null;
        }

        $cycle = DB::transaction(function () use ($tenant, $pupil, $today, $request, $source): ?ReviewCycle {
            $locked = ReviewCycle::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('pupil_id', $pupil->id)
                ->where('type', ReviewCycleType::AnnualReview)
                ->orderByDesc('due_on')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($locked->contains(
                fn (ReviewCycle $cycle): bool => $cycle->status === ReviewCycleStatus::Open,
            )) {
                return null;
            }

            $latest = $locked->first(fn (ReviewCycle $cycle): bool => $cycle->due_on !== null);

            if ($latest === null || $latest->due_on === null) {
                return null;
            }

            $anniversary = Carbon::parse($latest->due_on->toDateString(), 'Europe/London')
                ->addYearNoOverflow()
                ->toDateString();

            if ($anniversary > $today) {
                return null;
            }

            $alreadyExists = $locked->contains(
                fn (ReviewCycle $cycle): bool => $cycle->due_on?->toDateString() === $anniversary,
            );

            if ($alreadyExists) {
                return null;
            }

            $cycle = new ReviewCycle([
                'tenant_id' => $tenant->id,
                'pupil_id' => $pupil->id,
            ]);

            try {
                $cycle->forceFill([
                    'type' => ReviewCycleType::AnnualReview,
                    'due_on' => $anniversary,
                    'ehcp_linked' => (bool) $latest->ehcp_linked,
                    'status' => ReviewCycleStatus::Open,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            $metadata = [
                'actor' => 'system',
                'type' => ReviewCycleType::AnnualReview->value,
                'due_on' => $anniversary,
                'ehcp_link' => (bool) $cycle->ehcp_linked,
                'pupil_id' => $pupil->id,
            ];

            if ($source !== null) {
                $metadata['source'] = $source;
            }

            $this->audit->record(
                AuditEventType::ReviewCycleCreated,
                $request,
                user: null,
                tenantId: $tenant->id,
                resourceType: 'review_cycle',
                resourceId: $cycle->id,
                metadata: $metadata,
            );

            return $cycle;
        });

        if ($cycle instanceof ReviewCycle) {
            $cycle->load('pupil');
            $this->enqueueSreReevaluation->handle($pupil, 'review_cycle_created', 'review_cycle');
        }

        return $cycle;
    }
}
