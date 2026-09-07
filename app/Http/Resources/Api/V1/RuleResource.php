<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ontology\Rule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rule
 */
class RuleResource extends JsonResource
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
            'dimension' => $this->dimension?->value,
            'category' => $this->category?->value,
            'condition' => $this->condition,
            'evaluation' => $this->evaluation,
            'outcome' => $this->outcome,
            'sort_order' => $this->sort_order,
            'rule_library_version_id' => $this->rule_library_version_id,
        ];
    }
}
