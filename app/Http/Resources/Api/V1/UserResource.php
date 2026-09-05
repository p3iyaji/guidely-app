<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'external_id' => $this->external_id,
            'role' => $this->role?->value,
            'tenant_id' => $this->tenant_id,
            'school_ids' => $this->whenLoaded(
                'schools',
                fn () => $this->schools->pluck('id')->values()->all(),
            ),
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
        ];
    }
}
