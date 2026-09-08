<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reporting\ComplianceAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ComplianceAlert
 */
class ComplianceAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metricLabel = $this->metric?->label() ?? '';
        $thresholdPercent = $this->formatPercent((float) $this->threshold_value);
        $observedPercent = $this->formatPercent((float) $this->observed_value);

        return [
            'id' => $this->id,
            'scope' => $this->scope?->value,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'metric' => $this->metric?->value,
            'metric_label' => $metricLabel,
            'threshold_value' => $this->threshold_value,
            'observed_value' => $this->observed_value,
            'citation' => sprintf(
                '%s Indicator: observed %s meets configured threshold %s.',
                $metricLabel,
                $observedPercent,
                $thresholdPercent,
            ),
            'is_open' => $this->is_open,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function formatPercent(float $rate): string
    {
        return round($rate * 100).'%';
    }
}
