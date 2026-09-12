<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ontology\ThresholdTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThresholdTerm
 */
class ThresholdTermResource extends JsonResource
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
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'ontology_version_id' => $this->ontology_version_id,
        ];
    }
}
