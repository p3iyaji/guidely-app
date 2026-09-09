<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\AccessRole;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccessRoleRequest;
use App\Http\Requests\Api\V1\UpdateAccessRoleRequest;
use App\Http\Resources\Api\V1\AccessRoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AccessRoleController extends Controller
{
    public const IN_USE_CODE = 'access_role_in_use';

    public function __construct(private AuditWriter $audit) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccessRole::class);

        $roles = AccessRole::query()
            ->visibleToCurrentTenant()
            ->with(['permissions' => fn ($query) => $query->orderBy('group')->orderBy('label')->orderBy('id')])
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return AccessRoleResource::collection($roles);
    }

    public function store(StoreAccessRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $role = DB::transaction(function () use ($validated, $permissionIds): AccessRole {
            $role = AccessRole::query()->create([
                ...$validated,
                'tenant_id' => CurrentTenant::id(),
                'is_system' => false,
            ]);

            $role->permissions()->sync($permissionIds);

            return $role;
        });

        $this->audit->record(
            AuditEventType::AccessRoleCreated,
            $request,
            $request->user(),
            resourceType: 'access_role',
            resourceId: $role->id,
        );

        $role->load(['permissions' => fn ($query) => $query->orderBy('group')->orderBy('label')->orderBy('id')]);

        return (new AccessRoleResource($role))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AccessRole $accessRole): AccessRoleResource
    {
        $this->authorize('view', $accessRole);

        $accessRole->load(['permissions' => fn ($query) => $query->orderBy('group')->orderBy('label')->orderBy('id')]);

        return new AccessRoleResource($accessRole);
    }

    public function update(UpdateAccessRoleRequest $request, AccessRole $accessRole): AccessRoleResource
    {
        $validated = $request->validated();
        $permissionIds = array_key_exists('permission_ids', $validated)
            ? $validated['permission_ids']
            : null;
        unset($validated['permission_ids']);

        if ($accessRole->is_system) {
            unset($validated['key']);
        }

        DB::transaction(function () use ($accessRole, $validated, $permissionIds): void {
            if ($validated !== []) {
                $accessRole->update($validated);
            }

            if ($permissionIds !== null) {
                $accessRole->permissions()->sync($permissionIds);
            }
        });

        $this->audit->record(
            AuditEventType::AccessRoleUpdated,
            $request,
            $request->user(),
            resourceType: 'access_role',
            resourceId: $accessRole->id,
        );

        $accessRole->refresh()
            ->load(['permissions' => fn ($query) => $query->orderBy('group')->orderBy('label')->orderBy('id')]);

        return new AccessRoleResource($accessRole);
    }

    public function destroy(Request $request, AccessRole $accessRole): Response|JsonResponse
    {
        $this->authorize('delete', $accessRole);

        if ($accessRole->isInUse()) {
            return response()->json([
                'message' => 'This Role is assigned to Users and cannot be deleted.',
                'code' => self::IN_USE_CODE,
            ], 409);
        }

        $roleId = $accessRole->id;
        $accessRole->delete();

        $this->audit->record(
            AuditEventType::AccessRoleDeleted,
            $request,
            $request->user(),
            resourceType: 'access_role',
            resourceId: $roleId,
        );

        return response()->noContent();
    }
}
