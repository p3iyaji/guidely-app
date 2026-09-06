<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\SettingTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EvidenceRecord
 */
class EvidenceRecordResource extends JsonResource
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
            'author_id' => $this->author_id,
            'type' => $this->type?->value,
            'lifecycle' => $this->lifecycle?->value,
            'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            'setting' => $this->termPayload($this->settingTerm),
            'provision' => $this->termPayload($this->provisionTerm),
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, code: string, label: string}|null
     */
    private function termPayload(SettingTerm|ProvisionTerm|null $term): ?array
    {
        if ($term === null) {
            return null;
        }

        return [
            'id' => $term->id,
            'code' => $term->code,
            'label' => $term->label,
        ];
    }
}
