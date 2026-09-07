<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Sre\Gap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Gap
 */
class GapResource extends JsonResource
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
            'determination_id' => $this->determination_id,
            'determination' => $this->whenLoaded(
                'determination',
                fn () => $this->determination === null
                    ? null
                    : (new DeterminationResource($this->determination))->resolve(),
            ),
            'dimension' => $this->dimension?->value,
            'result' => $this->result?->value,
            'result_label' => $this->result?->label(),
            'is_open' => $this->is_open,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
