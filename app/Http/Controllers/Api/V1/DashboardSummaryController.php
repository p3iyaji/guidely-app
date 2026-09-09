<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\BuildDashboardSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowDashboardSummaryRequest;
use App\Http\Resources\Api\V1\DashboardSummaryResource;
use App\Models\User;

class DashboardSummaryController extends Controller
{
    public function show(
        ShowDashboardSummaryRequest $request,
        BuildDashboardSummary $build,
    ): DashboardSummaryResource {
        /** @var User $user */
        $user = $request->user();

        return new DashboardSummaryResource(
            $build->handle($user, $request->windowDays()),
        );
    }
}
