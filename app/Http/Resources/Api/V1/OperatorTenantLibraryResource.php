<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class OperatorTenantLibraryResource extends JsonResource
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
            'current_ontology_version' => $this->whenLoaded(
                'currentOntologyVersion',
                fn (): ?array => $this->currentOntologyVersion === null
                    ? null
                    : (new LibraryVersionResource($this->currentOntologyVersion))->resolve($request),
            ),
            'current_rule_library_version' => $this->whenLoaded(
                'currentRuleLibraryVersion',
                fn (): ?array => $this->currentRuleLibraryVersion === null
                    ? null
                    : (new LibraryVersionResource($this->currentRuleLibraryVersion))->resolve($request),
            ),
        ];
    }
}
