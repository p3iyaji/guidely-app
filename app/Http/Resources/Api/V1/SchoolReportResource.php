<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reporting\SchoolReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolReport
 */
class SchoolReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pupils_in_scope' => $this->pupilsInScope,
            'by_status' => $this->byStatus,
            'ready' => $this->ready,
            'gaps' => $this->gaps,
            'review_cycles_due' => $this->reviewCyclesDue,
            'window_days' => $this->windowDays,
            'pupils' => $this->pupils,
            'cycles_due' => $this->cyclesDue,
        ];
    }
}
