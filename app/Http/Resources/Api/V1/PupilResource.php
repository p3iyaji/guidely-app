<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\Pupil;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pupil
 */
class PupilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'school_id' => $this->school_id,
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'mis_key' => $this->mis_key,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'year_group' => $this->year_group,
            'send_status' => $this->send_status?->value,
            'notes' => $this->notes,
            'documentation_status' => $this->documentation_status?->value,
            'next_review_at' => $this->nextOpenReviewDueOn(),
            'primary_need' => $this->needPayload($this->primaryNeedTerm, $this->primary_need_notes),
            'secondary_need' => $this->needPayload($this->secondaryNeedTerm, $this->secondary_need_notes),
            'assigned_staff' => $this->when(
                $this->relationLoaded('assignedUsers'),
                fn () => $this->assignedUsers->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role?->value,
                    'class_label' => $user->pivot?->class_label,
                    'cohort_label' => $user->pivot?->cohort_label,
                ])->values()->all(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, code: string, label: string, notes: ?string}|null
     */
    private function needPayload(?NeedTerm $term, ?string $notes): ?array
    {
        if ($term === null) {
            return null;
        }

        return [
            'id' => $term->id,
            'code' => $term->code,
            'label' => $term->label,
            'notes' => $notes,
        ];
    }
}
