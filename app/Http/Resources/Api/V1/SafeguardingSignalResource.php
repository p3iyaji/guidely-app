<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Pupils\SafeguardingSignal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SafeguardingSignal
 */
class SafeguardingSignalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pupil = $this->pupil;

        return [
            'id' => $this->id,
            'pupil_id' => $this->pupil_id,
            'given_name' => $pupil?->given_name,
            'family_name' => $pupil?->family_name,
            'school_id' => $pupil?->school_id,
            'present' => $this->present,
            'severity' => $this->severity?->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
