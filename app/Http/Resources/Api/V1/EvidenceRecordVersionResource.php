<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Evidence\EvidenceRecordVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EvidenceRecordVersion
 */
class EvidenceRecordVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'evidence_record_id' => $this->evidence_record_id,
            'version' => (int) $this->version,
            'snapshot' => $this->snapshot,
            'superseded_at' => $this->superseded_at?->utc()->toIso8601String(),
            'superseded_by' => $this->whenLoaded('supersededByUser', fn () => $this->supersededByUser === null ? null : [
                'id' => $this->supersededByUser->id,
                'name' => $this->supersededByUser->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
