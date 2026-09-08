<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Reporting\BuildTrustIndicators;
use App\Domain\Reporting\TrustIndicator;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrustBenchmarkResource;
use App\Models\User;
use Illuminate\Http\Request;

class TrustBenchmarkController extends Controller
{
    /**
     * Flagged portfolio ranking and month-over-month Trust Indicators.
     */
    public function __invoke(Request $request, BuildTrustIndicators $build): TrustBenchmarkResource
    {
        $this->authorize('view', TrustIndicator::class);

        /** @var User $user */
        $user = $request->user();
        $includeSchools = $user->role === Role::TrustSendLead;
        $indicator = $build->handle($includeSchools, includeBenchmark: true);

        return new TrustBenchmarkResource($indicator->benchmark ?? [
            'ranked_by' => 'gap_density',
            'trends' => [],
        ]);
    }
}
