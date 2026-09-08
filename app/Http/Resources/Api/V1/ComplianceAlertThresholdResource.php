<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reporting\ComplianceAlertThreshold;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ComplianceAlertThreshold
 */
class ComplianceAlertThresholdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'metric' => $this->metric?->value,
            'threshold' => $this->threshold,
            'snapshot_key' => $this->snapshot_key,
        ];
    }
}
