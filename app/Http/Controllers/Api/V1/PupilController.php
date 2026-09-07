<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\Role;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePupilRequest;
use App\Http\Requests\Api\V1\UpdatePupilRequest;
use App\Http\Resources\Api\V1\PupilResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PupilController extends Controller
{
    /**
     * @var list<string>
     */
    private const NEED_ATTRIBUTE_KEYS = [
        'primary_need_term_id',
        'primary_need_notes',
        'secondary_need_term_id',
        'secondary_need_notes',
    ];

    public function __construct(
        private AuditWriter $audit,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pupil::class);

        /** @var User $user */
        $user = $request->user();

        $query = Pupil::query()
            ->with(['primaryNeedTerm', 'secondaryNeedTerm'])
            ->orderBy('family_name')
            ->orderBy('given_name')
            ->orderBy('id');

        if ($user->isAssignmentScopedForPupils()) {
            $schoolIds = $user->schools()->allRelatedIds();

            $query->whereIn('school_id', $schoolIds)
                ->whereHas(
                    'assignedUsers',
                    fn ($assignees) => $assignees->whereKey($user->id)
                );
        } elseif ($this->returnsEmptyCohort($user)) {
            // Non-viewer Roles without assignment scope stay empty until a later story.
            return PupilResource::collection(collect());
        } elseif (! $user->seesAllTenantSchools()) {
            $query->whereIn('school_id', $user->schools()->allRelatedIds());
        }

        if ($this->shouldIncludeLeft($request, $user)) {
            $query->withTrashed();
        }

        return PupilResource::collection($query->get());
    }

    public function store(StorePupilRequest $request): JsonResponse
    {
        try {
            $pupil = Pupil::query()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'mis_key' => 'A Pupil with this MIS key already exists in this School.',
            ]);
        }

        $pupil->load(['primaryNeedTerm', 'secondaryNeedTerm']);

        $hasNeedValues = $this->pupilHasNeedValues($pupil);

        $this->audit->record(
            AuditEventType::PupilCreated,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: $hasNeedValues ? $this->needAuditMetadata($pupil) : [],
        );

        $this->enqueueSreReevaluationIfNeeded($pupil, $hasNeedValues);

        return (new PupilResource($pupil))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pupil $pupil): PupilResource
    {
        $this->authorize('view', $pupil);

        $pupil->loadMissing(['primaryNeedTerm', 'secondaryNeedTerm']);

        return new PupilResource($pupil);
    }

    public function update(UpdatePupilRequest $request, Pupil $pupil): PupilResource
    {
        $needChanged = $this->needAttributesChanged($pupil, $request->validated());

        try {
            $pupil->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'mis_key' => 'A Pupil with this MIS key already exists in this School.',
            ]);
        }

        $pupil->refresh()->load(['primaryNeedTerm', 'secondaryNeedTerm']);

        $this->audit->record(
            AuditEventType::PupilUpdated,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: $needChanged ? $this->needAuditMetadata($pupil) : [],
        );

        $this->enqueueSreReevaluationIfNeeded($pupil, $needChanged);

        return new PupilResource($pupil);
    }

    public function destroy(Request $request, Pupil $pupil): Response
    {
        $this->authorize('delete', $pupil);

        $pupilId = $pupil->id;
        $pupil->delete();

        $this->audit->record(
            AuditEventType::PupilDeleted,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupilId,
        );

        return response()->noContent();
    }

    private function returnsEmptyCohort(User $user): bool
    {
        return ! in_array($user->role, [
            Role::Senco,
            Role::TenantAdmin,
            Role::SchoolLeader,
            Role::Teacher,
            Role::SupportStaff,
        ], true);
    }

    private function shouldIncludeLeft(Request $request, User $user): bool
    {
        $wantsLeft = $request->boolean('include_left') || $request->boolean('with_trashed');

        if (! $wantsLeft) {
            return false;
        }

        return $user->can('viewLeft', Pupil::class);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function needAttributesChanged(Pupil $pupil, array $validated): bool
    {
        foreach (self::NEED_ATTRIBUTE_KEYS as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            if ($pupil->getAttribute($key) !== $validated[$key]) {
                return true;
            }
        }

        return false;
    }

    private function pupilHasNeedValues(Pupil $pupil): bool
    {
        return $pupil->primary_need_term_id !== null
            || $pupil->secondary_need_term_id !== null
            || filled($pupil->primary_need_notes)
            || filled($pupil->secondary_need_notes);
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function needAuditMetadata(Pupil $pupil): array
    {
        return [
            'primary_need_term_id' => $pupil->primary_need_term_id,
            'primary_need_term_code' => $this->termCode($pupil->primaryNeedTerm),
            'primary_need_notes' => $pupil->primary_need_notes,
            'secondary_need_term_id' => $pupil->secondary_need_term_id,
            'secondary_need_term_code' => $this->termCode($pupil->secondaryNeedTerm),
            'secondary_need_notes' => $pupil->secondary_need_notes,
        ];
    }

    private function termCode(?NeedTerm $term): ?string
    {
        return $term?->code;
    }

    private function enqueueSreReevaluationIfNeeded(Pupil $pupil, bool $needChanged): void
    {
        if (! $needChanged) {
            return;
        }

        $this->enqueueSreReevaluation->handle($pupil, 'need_changed', 'pupil');
    }
}
