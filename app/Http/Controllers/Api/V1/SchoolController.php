<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\School;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSchoolRequest;
use App\Http\Requests\Api\V1\UpdateSchoolRequest;
use App\Http\Resources\Api\V1\SchoolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SchoolController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', School::class);

        $user = $request->user();
        $query = School::query()->orderBy('name');

        if ($user !== null && ! $user->seesAllTenantSchools()) {
            $query->whereKey($user->schools()->allRelatedIds());
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        return SchoolResource::collection($query->get());
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $school = School::query()->create($request->validated());

        $this->audit->record(
            AuditEventType::SchoolCreated,
            $request,
            $request->user(),
            resourceType: 'school',
            resourceId: $school->id,
        );

        return (new SchoolResource($school))
            ->response()
            ->setStatusCode(201);
    }

    public function show(School $school): SchoolResource
    {
        $this->authorize('view', $school);

        return new SchoolResource($school);
    }

    public function update(UpdateSchoolRequest $request, School $school): SchoolResource
    {
        $school->update($request->validated());

        $this->audit->record(
            AuditEventType::SchoolUpdated,
            $request,
            $request->user(),
            resourceType: 'school',
            resourceId: $school->id,
        );

        return new SchoolResource($school->refresh());
    }

    public function destroy(Request $request, School $school): Response
    {
        $this->authorize('delete', $school);

        $schoolId = $school->id;
        $school->delete();

        $this->audit->record(
            AuditEventType::SchoolDeleted,
            $request,
            $request->user(),
            resourceType: 'school',
            resourceId: $schoolId,
        );

        return response()->noContent();
    }
}
