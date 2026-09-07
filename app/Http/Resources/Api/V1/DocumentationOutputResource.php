<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Outputs\DocumentationOutput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentationOutput
 */
class DocumentationOutputResource extends JsonResource
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
            'review_cycle_id' => $this->review_cycle_id,
            'review_cycle' => $this->whenLoaded('reviewCycle', fn () => $this->reviewCycle === null ? null : [
                'id' => $this->reviewCycle->id,
                'type' => $this->reviewCycle->type?->value,
                'due_on' => $this->reviewCycle->due_on?->toDateString(),
                'status' => $this->reviewCycle->status?->value,
            ]),
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'version' => $this->version,
            'confirmer_user_id' => $this->confirmer_user_id,
            'confirmer' => $this->whenLoaded('confirmer', fn () => $this->confirmer === null ? null : [
                'id' => $this->confirmer->id,
                'name' => $this->confirmer->name,
            ]),
            'disclaimer_text' => $this->disclaimer_text,
            'confirmed_at' => $this->confirmed_at?->utc()->toIso8601String(),
            'pack_ready_at' => $this->pack_ready_at?->utc()->toIso8601String(),
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
