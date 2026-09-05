<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'cohort_enabled' => $this->cohort_enabled,
            'cohort_label' => $this->cohort_label,
            'feature_flags' => app(FeatureFlagResolver::class)->mapFor($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
