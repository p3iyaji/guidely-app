<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantSsoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sso_enabled' => $this->sso_enabled,
            'sso_provider' => $this->sso_provider,
            'sso_entity_id' => $this->sso_entity_id,
            'sso_client_id' => $this->sso_client_id,
        ];
    }
}
