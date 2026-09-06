<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Pupils\Pupil;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pupil
 */
class PupilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'school_id' => $this->school_id,
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'mis_key' => $this->mis_key,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'year_group' => $this->year_group,
            'send_status' => $this->send_status?->value,
            'documentation_status' => $this->documentation_status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
