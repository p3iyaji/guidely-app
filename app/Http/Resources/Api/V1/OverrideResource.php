<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Sre\Override;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Override
 */
class OverrideResource extends JsonResource
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
            'determination_id' => $this->determination_id,
            'dimension' => $this->dimension?->value,
            'user_id' => $this->user_id,
            'rationale' => $this->rationale,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
