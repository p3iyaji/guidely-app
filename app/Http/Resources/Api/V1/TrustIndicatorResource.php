<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reporting\TrustIndicator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrustIndicator
 */
class TrustIndicatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'pupils_in_scope' => $this->pupilsInScope,
            'by_status' => $this->byStatus,
            'gap_density' => $this->gapDensity,
            'gaps' => $this->gaps,
            'lateness_rate' => $this->latenessRate,
            'overdue_open_cycles' => $this->overdueOpenCycles,
            'open_cycles' => $this->openCycles,
            'escalated_pupils' => $this->escalatedPupils,
            'flagged_schools' => $this->flaggedSchools,
            'escalations' => $this->escalations,
        ];

        if ($this->schools !== null) {
            $payload['schools'] = $this->schools;
        }

        if ($this->benchmark !== null) {
            $payload['benchmark'] = $this->benchmark;
        }

        return $payload;
    }
}
