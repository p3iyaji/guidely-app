<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reporting\DashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DashboardSummary
 */
class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pupils_in_scope' => $this->pupilsInScope,
            'open_gaps' => $this->openGaps,
            'review_cycles_due' => $this->reviewCyclesDue,
            'drafts' => $this->drafts,
            'window_days' => $this->windowDays,
        ];
    }
}
