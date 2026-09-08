<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\ComplianceAlert;
use App\Domain\Reporting\ComplianceAlertMetric;
use App\Domain\Reporting\ComplianceAlertThreshold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateComplianceAlertThresholdRequest;
use App\Http\Resources\Api\V1\ComplianceAlertThresholdResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplianceAlertThresholdController extends Controller
{
    /**
     * List configured Indicator alert thresholds for the current Tenant.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('updateThresholds', ComplianceAlert::class);

        $thresholds = ComplianceAlertThreshold::query()
            ->orderBy('snapshot_key')
            ->get();

        return ComplianceAlertThresholdResource::collection($thresholds);
    }

    /**
     * Create or update a Trust or School Indicator threshold.
     */
    public function update(UpdateComplianceAlertThresholdRequest $request): ComplianceAlertThresholdResource
    {
        $metric = ComplianceAlertMetric::from($request->validated('metric'));
        $schoolId = $request->validated('school_id');
        $schoolId = is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
        $key = ComplianceAlertThreshold::snapshotKey($metric, $schoolId);

        $threshold = ComplianceAlertThreshold::query()
            ->where('snapshot_key', $key)
            ->first() ?? new ComplianceAlertThreshold;

        $threshold->forceFill([
            'school_id' => $schoolId,
            'metric' => $metric,
            'threshold' => (float) $request->validated('threshold'),
            'snapshot_key' => $key,
        ])->save();

        return new ComplianceAlertThresholdResource($threshold);
    }
}
