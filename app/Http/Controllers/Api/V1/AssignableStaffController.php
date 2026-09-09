<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\School;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssignableStaffController extends Controller
{
    /**
     * Teachers and Support Staff who may be assigned to Pupils in this School.
     */
    public function index(School $school): AnonymousResourceCollection
    {
        $this->authorize('view', $school);
        $this->authorize('create', Pupil::class);

        $staff = User::query()
            ->forCurrentTenant()
            ->whereNull('deactivated_at')
            ->whereIn('role', [
                Role::Teacher->value,
                Role::SupportStaff->value,
            ])
            ->whereHas(
                'schools',
                fn ($schools) => $schools->whereKey($school->id),
            )
            ->with('schools')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return UserResource::collection($staff);
    }
}
