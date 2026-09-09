<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfilePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    /**
     * Authenticated session bootstrap for the Vue app shell.
     */
    public function show(Request $request): UserResource
    {
        abort_unless($request->user(), 401);

        return new UserResource($request->user()->loadMissing('schools'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $user->update($request->safe()->only(['name', 'email']));

        $this->audit->record(
            AuditEventType::UserUpdated,
            $request,
            $user,
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        return new UserResource($user->refresh()->loadMissing('schools'));
    }

    public function updatePassword(UpdateProfilePasswordRequest $request): UserResource
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->audit->record(
            AuditEventType::UserPasswordReset,
            $request,
            $user,
            resourceType: 'user',
            resourceId: (string) $user->id,
        );

        return new UserResource($user->refresh()->loadMissing('schools'));
    }
}
