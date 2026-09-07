<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Pupils\Pupil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPupilEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Pupil|null $pupil */
        $pupil = $this->route('pupil');

        if (! $pupil instanceof Pupil) {
            return false;
        }

        return $this->user()?->can('listForPupil', [EvidenceRecord::class, $pupil]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'observation',
                    'intervention',
                    'response',
                    'review_note',
                    'import',
                ]),
            ],
        ];
    }

    public function filter(): ?string
    {
        $filter = $this->validated('filter');

        return is_string($filter) && $filter !== '' ? $filter : null;
    }
}
