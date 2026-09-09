<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\AccessRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessRole
 */
class AccessRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'tenant_id' => $this->tenant_id,
            'permission_ids' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('id')->values()->all(),
            ),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => AccessPermissionResource::collection($this->permissions)->resolve(),
            ),
        ];
    }
}
