<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Reporting\ComplianceAlert;
use App\Domain\Reporting\ComplianceAlertScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplianceAlertResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplianceAlertController extends Controller
{
    /**
     * List open Indicator alerts for the current Role's allowed scopes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ComplianceAlert::class);

        /** @var User $user */
        $user = $request->user();

        $query = ComplianceAlert::query()
            ->open()
            ->with('school:id,name')
            ->orderBy('scope')
            ->orderBy('school_id')
            ->orderBy('metric')
            ->orderBy('id');

        if ($user->role === Role::TrustExecutive) {
            $query->where('scope', ComplianceAlertScope::Trust)
                ->whereNull('school_id');
        } elseif ($user->role === Role::Senco) {
            $schoolIds = $user->schools()->allRelatedIds();
            $query->where('scope', ComplianceAlertScope::School)
                ->whereIn('school_id', $schoolIds);
        }

        return ComplianceAlertResource::collection($query->get());
    }
}
