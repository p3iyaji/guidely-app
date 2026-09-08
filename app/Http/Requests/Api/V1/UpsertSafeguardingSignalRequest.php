<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SafeguardingSeverity;
use App\Domain\Pupils\SafeguardingSignal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSafeguardingSignalRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_KEYS = ['present', 'severity'];

    public function authorize(): bool
    {
        $pupil = $this->route('pupil');

        if (! $pupil instanceof Pupil) {
            return false;
        }

        return $this->user()?->can('upsert', [SafeguardingSignal::class, $pupil]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'present' => ['required', 'boolean'],
            'severity' => [
                'prohibited_if:present,false',
                'required_if:present,true',
                'nullable',
                Rule::enum(SafeguardingSeverity::class),
            ],
            'notes' => ['prohibited'],
            'body' => ['prohibited'],
            'text' => ['prohibited'],
            'narrative' => ['prohibited'],
            'case_note' => ['prohibited'],
            'evidence' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, self::ALLOWED_KEYS, true)) {
                    $validator->errors()->add($key, 'Only present and severity may be sent.');
                }
            }
        });
    }
}
