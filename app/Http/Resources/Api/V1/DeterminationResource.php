<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ontology\Rule;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Sre\Determination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Determination
 */
class DeterminationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'pupil_id' => $this->pupil_id,
            'dimension' => $this->dimension?->value,
            'result' => $this->result?->value,
            'result_label' => $this->result?->label(),
            'rule_id' => $this->rule_id,
            'rule' => $this->whenLoaded('rule', fn () => $this->rulePayload($this->rule)),
            'rule_library_version_id' => $this->rule_library_version_id,
            'rule_library_version' => $this->whenLoaded(
                'ruleLibraryVersion',
                fn () => $this->ruleLibraryVersionPayload($this->ruleLibraryVersion),
            ),
            'ontology_version_id' => $this->ontology_version_id,
            'reasoning_pathway' => $this->reasoning_pathway,
            'is_current' => $this->is_current,
            'evaluated_at' => $this->evaluated_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, code: string, label: string}|null
     */
    private function rulePayload(?Rule $rule): ?array
    {
        if ($rule === null) {
            return null;
        }

        return [
            'id' => $rule->id,
            'code' => $rule->code,
            'label' => $rule->label,
        ];
    }

    /**
     * @return array{id: string, code: string, label: string}|null
     */
    private function ruleLibraryVersionPayload(?RuleLibraryVersion $version): ?array
    {
        if ($version === null) {
            return null;
        }

        return [
            'id' => $version->id,
            'code' => $version->code,
            'label' => $version->label,
        ];
    }
}
