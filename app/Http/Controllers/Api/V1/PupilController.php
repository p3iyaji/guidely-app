<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
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
    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pupil::class);

        /** @var User $user */
        $user = $request->user();

        // Teachers/Support Staff (and other non-viewer Roles) stay empty until Story 2.3.
        if ($this->returnsEmptyCohort($user)) {
            return PupilResource::collection(collect());
        }

        $query = Pupil::query()
            ->orderBy('family_name')
            ->orderBy('given_name')
            ->orderBy('id');

        if (! $user->seesAllTenantSchools()) {
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

        $this->audit->record(
            AuditEventType::PupilCreated,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
        );

        return (new PupilResource($pupil))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pupil $pupil): PupilResource
    {
        $this->authorize('view', $pupil);

        return new PupilResource($pupil);
    }

    public function update(UpdatePupilRequest $request, Pupil $pupil): PupilResource
    {
        try {
            $pupil->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'mis_key' => 'A Pupil with this MIS key already exists in this School.',
            ]);
        }

        $this->audit->record(
            AuditEventType::PupilUpdated,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
        );

        return new PupilResource($pupil->refresh());
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
}
