<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight Intervention row for Capture Pupil Response picker.
 *
 * @mixin EvidenceRecord
 */
class InterventionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            'provision' => $this->provisionTerm === null ? null : [
                'id' => $this->provisionTerm->id,
                'code' => $this->provisionTerm->code,
                'label' => $this->provisionTerm->label,
            ],
        ];
    }
}
