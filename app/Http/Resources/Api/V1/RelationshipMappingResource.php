<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ontology\RelationshipMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RelationshipMapping
 */
class RelationshipMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'relationship_type' => $this->relationship_type,
            'from_domain' => $this->from_domain,
            'from_term_id' => $this->from_term_id,
            'from_term' => [
                'id' => $this->from_term_id,
                'type' => $this->from_domain,
                'label' => $this->from_term_label,
            ],
            'to_domain' => $this->to_domain,
            'to_term_id' => $this->to_term_id,
            'to_term' => [
                'id' => $this->to_term_id,
                'type' => $this->to_domain,
                'label' => $this->to_term_label,
            ],
            'sort_order' => $this->sort_order,
            'ontology_version_id' => $this->ontology_version_id,
        ];
    }
}
