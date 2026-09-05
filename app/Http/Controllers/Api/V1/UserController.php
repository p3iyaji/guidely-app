<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeactivateUserRequest;
use App\Http\Requests\Api\V1\ResetUserPasswordRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public const LAST_TENANT_ADMIN_CODE = 'last_tenant_admin';

    public const USER_DEACTIVATED_CODE = 'user_deactivated';

    public function __construct(private AuditWriter $audit) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->forCurrentTenant()
            ->with('schools')
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            abort(403);
        }

        $validated = $request->validated();
        $schoolIds = $validated['school_ids'] ?? null;
        $role = $validated['role'];
        unset($validated['school_ids'], $validated['role']);

        $user = DB::transaction(function () use ($validated, $schoolIds, $role, $tenantId): User {
            $user = new User;
            $user->forceFill([
                ...$validated,
                'tenant_id' => $tenantId,
                'role' => $role,
            ])->save();

            if (is_array($schoolIds)) {
                $user->schools()->sync($schoolIds);
            }

            return $user;
        });

        $this->audit->record(
            AuditEventType::UserCreated,
            $request,
            $request->user(),
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        $user->load('schools');

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load('schools');

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|UserResource
    {
        if ($user->isDeactivated()) {
            return $this->userDeactivatedResponse(
                'Cannot update a deactivated User.'
            );
        }

        $validated = $request->validated();

        if ($this->wouldOrphanTenantAdminsOnDemote($user, $validated['role'] ?? null)) {
            return $this->lastTenantAdminResponse(
                'Cannot demote the last active Tenant Admin for this Tenant.'
            );
        }

        $schoolIds = $validated['school_ids'] ?? null;
        $role = $validated['role'] ?? null;
        unset($validated['school_ids'], $validated['role']);

        $user = DB::transaction(function () use ($user, $validated, $schoolIds, $role): User {
            if ($validated !== []) {
                $user->update($validated);
            }

            if ($role !== null) {
                $user->forceFill([
                    'role' => $role,
                ])->save();
            }

            if (is_array($schoolIds)) {
                $user->schools()->sync($schoolIds);
            }

            return $user;
        });

        $this->audit->record(
            AuditEventType::UserUpdated,
            $request,
            $request->user(),
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        $user->refresh()->load('schools');

        return new UserResource($user);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse|UserResource
    {
        if ($user->isDeactivated()) {
            return $this->userDeactivatedResponse(
                'Cannot reset password for a deactivated User.'
            );
        }

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $user->tokens()->delete();

        $this->audit->record(
            AuditEventType::UserPasswordReset,
            $request,
            $request->user(),
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        $user->load('schools');

        return new UserResource($user);
    }

    public function deactivate(DeactivateUserRequest $request, User $user): JsonResponse|UserResource
    {
        if ($user->isDeactivated()) {
            return $this->userDeactivatedResponse(
                'User is already deactivated.'
            );
        }

        if ($user->isLastActiveTenantAdmin()) {
            return $this->lastTenantAdminResponse(
                'Cannot deactivate the last active Tenant Admin for this Tenant.'
            );
        }

        $user->deactivate();

        $this->audit->record(
            AuditEventType::UserDeactivated,
            $request,
            $request->user(),
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        $user->refresh()->load('schools');

        return new UserResource($user);
    }

    private function wouldOrphanTenantAdminsOnDemote(User $user, ?string $newRole): bool
    {
        if ($newRole === null || ! $user->isTenantAdmin() || $user->isDeactivated()) {
            return false;
        }

        if ($newRole === Role::TenantAdmin->value) {
            return false;
        }

        return $user->isLastActiveTenantAdmin();
    }

    private function lastTenantAdminResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => self::LAST_TENANT_ADMIN_CODE,
        ], 422);
    }

    private function userDeactivatedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => self::USER_DEACTIVATED_CODE,
        ], 422);
    }
}
