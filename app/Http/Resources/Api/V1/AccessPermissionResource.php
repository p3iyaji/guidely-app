<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\AccessPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessPermission
 */
class AccessPermissionResource extends JsonResource
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
            'group' => $this->group,
            'is_system' => $this->is_system,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
