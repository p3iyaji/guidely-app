<?php

namespace App\Http\Controllers\Api\V1;

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
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', School::class);

        $query = School::query()->orderBy('name');

        if ($request->boolean('active')) {
            $query->active();
        }

        return SchoolResource::collection($query->get());
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $school = School::query()->create($request->validated());

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

        return new SchoolResource($school->refresh());
    }

    public function destroy(School $school): Response
    {
        $this->authorize('delete', $school);

        $school->delete();

        return response()->noContent();
    }
}
