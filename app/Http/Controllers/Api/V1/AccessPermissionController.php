<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\AccessPermission;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccessPermissionRequest;
use App\Http\Requests\Api\V1\UpdateAccessPermissionRequest;
use App\Http\Resources\Api\V1\AccessPermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AccessPermissionController extends Controller
{
    public const IN_USE_CODE = 'access_permission_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccessPermission::class);

        $permissions = AccessPermission::query()
            ->visibleToCurrentTenant()
            ->orderBy('group')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return AccessPermissionResource::collection($permissions);
    }

    public function store(StoreAccessPermissionRequest $request): JsonResponse
    {
        $permission = AccessPermission::query()->create([
            ...$request->safe()->only(['key', 'label', 'description', 'group']),
            'tenant_id' => CurrentTenant::id(),
            'is_system' => false,
        ]);

        $this->audit->record(
            AuditEventType::AccessPermissionCreated,
            $request,
            $request->user(),
            resourceType: 'access_permission',
            resourceId: $permission->id,
        );

        return (new AccessPermissionResource($permission))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AccessPermission $accessPermission): AccessPermissionResource
    {
        $this->authorize('view', $accessPermission);

        return new AccessPermissionResource($accessPermission);
    }

    public function update(
        UpdateAccessPermissionRequest $request,
        AccessPermission $accessPermission,
    ): AccessPermissionResource {
        $attributes = $request->safe()->only(['key', 'label', 'description', 'group']);

        if ($accessPermission->is_system) {
            unset($attributes['key']);
        }

        $accessPermission->update($attributes);

        $this->audit->record(
            AuditEventType::AccessPermissionUpdated,
            $request,
            $request->user(),
            resourceType: 'access_permission',
            resourceId: $accessPermission->id,
        );

        return new AccessPermissionResource($accessPermission->refresh());
    }

    public function destroy(Request $request, AccessPermission $accessPermission): Response|JsonResponse
    {
        $this->authorize('delete', $accessPermission);

        if ($accessPermission->isInUse()) {
            return response()->json([
                'message' => 'This Permission is assigned to a Role and cannot be deleted.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $permissionId = $accessPermission->id;
        $accessPermission->delete();

        $this->audit->record(
            AuditEventType::AccessPermissionDeleted,
            $request,
            $request->user(),
            resourceType: 'access_permission',
            resourceId: $permissionId,
        );

        return response()->noContent();
    }
}
