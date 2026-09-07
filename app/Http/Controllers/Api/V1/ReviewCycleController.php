<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleStatus;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReviewCyclesRequest;
use App\Http\Requests\Api\V1\StoreReviewCycleRequest;
use App\Http\Resources\Api\V1\ReviewCycleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewCycleController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    /**
     * List open Review Cycles due within the requested window (including past-due).
     */
    public function index(ListReviewCyclesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $horizon = now('Europe/London')->addDays($request->windowDays())->toDateString();
        $pupilNameQuery = $request->pupilNameQuery();

        $query = ReviewCycle::query()
            ->open()
            ->dueOnOrBefore($horizon)
            ->with('pupil')
            ->whereHas('pupil', function ($pupils) use ($user, $pupilNameQuery): void {
                if (! $user->seesAllTenantSchools()) {
                    $pupils->whereIn('school_id', $user->schools()->allRelatedIds());
                }

                if ($pupilNameQuery === '') {
                    return;
                }

                $needle = mb_strtolower(str_replace(['%', '_'], '', $pupilNameQuery));

                if ($needle === '') {
                    $pupils->whereRaw('0 = 1');

                    return;
                }

                $like = '%'.$needle.'%';
                $pupils->where(function ($names) use ($like): void {
                    $names->whereRaw('lower(given_name) like ?', [$like])
                        ->orWhereRaw('lower(family_name) like ?', [$like])
                        ->orWhereRaw("lower(trim(given_name || ' ' || family_name)) like ?", [$like]);
                });
            })
            ->orderBy('due_on')
            ->orderBy('id');

        return ReviewCycleResource::collection($query->get());
    }

    /**
     * List every Review Cycle for a Pupil, including closed Annual Reviews.
     */
    public function indexForPupil(Pupil $pupil): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ReviewCycle::class);
        $this->authorize('view', $pupil);

        $cycles = ReviewCycle::query()
            ->where('pupil_id', $pupil->id)
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->get();

        return ReviewCycleResource::collection($cycles);
    }

    public function store(StoreReviewCycleRequest $request, ?Pupil $pupil = null): JsonResponse
    {
        $resolvedPupil = $pupil instanceof Pupil ? $pupil : $request->pupil();

        if ($resolvedPupil === null) {
            throw ValidationException::withMessages([
                'pupil_id' => 'A Pupil is required.',
            ]);
        }

        $this->authorize('create', [ReviewCycle::class, $resolvedPupil]);

        $cycle = DB::transaction(function () use ($request, $resolvedPupil): ReviewCycle {
            $cycle = new ReviewCycle([
                'tenant_id' => $resolvedPupil->tenant_id,
                'pupil_id' => $resolvedPupil->id,
            ]);
            $cycle->forceFill([
                'type' => $request->type(),
                'due_on' => $request->dueOn(),
                'ehcp_linked' => $request->ehcpLinked(),
                'status' => ReviewCycleStatus::Open,
            ])->save();

            $this->audit->record(
                AuditEventType::ReviewCycleCreated,
                $request,
                $request->user(),
                resourceType: 'review_cycle',
                resourceId: $cycle->id,
                metadata: $this->cycleAuditMetadata($cycle, $resolvedPupil->id),
            );

            return $cycle;
        });

        $cycle->load('pupil');

        $this->enqueueSreReevaluation->handle($resolvedPupil, 'review_cycle_created', 'review_cycle');

        return (new ReviewCycleResource($cycle))
            ->response()
            ->setStatusCode(201);
    }

    public function close(Request $request, ReviewCycle $reviewCycle): ReviewCycleResource
    {
        $this->authorize('close', $reviewCycle);

        $closed = DB::transaction(function () use ($request, $reviewCycle): ReviewCycle {
            /** @var ReviewCycle $locked */
            $locked = ReviewCycle::query()
                ->whereKey($reviewCycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ReviewCycleStatus::Closed) {
                throw ValidationException::withMessages([
                    'status' => 'This Review Cycle is already closed.',
                ]);
            }

            $locked->forceFill([
                'status' => ReviewCycleStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $request->user()?->id,
            ])->save();

            $this->audit->record(
                AuditEventType::ReviewCycleClosed,
                $request,
                $request->user(),
                resourceType: 'review_cycle',
                resourceId: $locked->id,
                metadata: $this->cycleAuditMetadata($locked, $locked->pupil_id),
            );

            return $locked;
        });

        /** @var Pupil $pupil */
        $pupil = Pupil::query()->findOrFail($closed->pupil_id);

        $closed->load('pupil');

        $this->enqueueSreReevaluation->handle($pupil, 'review_cycle_closed', 'review_cycle');

        return new ReviewCycleResource($closed);
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function cycleAuditMetadata(ReviewCycle $cycle, string $pupilId): array
    {
        return [
            'type' => $cycle->type instanceof ReviewCycleType
                ? $cycle->type->value
                : (string) $cycle->type,
            'due_on' => $cycle->due_on?->toDateString(),
            'ehcp_link' => (bool) $cycle->ehcp_linked,
            'pupil_id' => $pupilId,
        ];
    }
}
