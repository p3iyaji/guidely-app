<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reviews\ReviewCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReviewCycle
 */
class ReviewCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'pupil_id' => $this->pupil_id,
            'pupil' => $this->whenLoaded('pupil', fn () => $this->pupil === null ? null : [
                'id' => $this->pupil->id,
                'given_name' => $this->pupil->given_name,
                'family_name' => $this->pupil->family_name,
                'year_group' => $this->pupil->year_group,
            ]),
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'due_on' => $this->due_on?->toDateString(),
            'ehcp_linked' => $this->ehcp_linked,
            'status' => $this->status?->value,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
