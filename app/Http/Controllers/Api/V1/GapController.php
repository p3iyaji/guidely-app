<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sre\Gap;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GapResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GapController extends Controller
{
    /**
     * List open Gaps for Pupils in the SENCO's accessible Schools.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Gap::class);

        /** @var User $user */
        $user = $request->user();

        $query = Gap::query()
            ->open()
            ->with([
                'pupil',
                'determination.rule',
                'determination.ruleLibraryVersion',
            ])
            ->whereHas('pupil', function ($pupils) use ($user): void {
                if ($user->seesAllTenantSchools()) {
                    return;
                }

                $pupils->whereIn('school_id', $user->schools()->allRelatedIds());
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return GapResource::collection($query->get());
    }
}
