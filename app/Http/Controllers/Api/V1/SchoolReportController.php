<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\BuildSchoolReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowSchoolReportRequest;
use App\Http\Resources\Api\V1\SchoolReportResource;
use App\Models\User;

class SchoolReportController extends Controller
{
    /**
     * Read-only School Report of documentation readiness for accessible Schools.
     */
    public function show(ShowSchoolReportRequest $request, BuildSchoolReport $build): SchoolReportResource
    {
        /** @var User $user */
        $user = $request->user();

        return new SchoolReportResource(
            $build->handle($user, $request->windowDays()),
        );
    }
}
