<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\Import\ImportedInterventionEvidenceUpserter;
use App\Domain\Pupils\SendStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $existing = Connector::query()->first();

        if ($existing === null) {
            return $user->can('create', Connector::class);
        }

        return $user->can('sync', $existing);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school_id' => [
                'required',
                'string',
                Rule::exists('schools', 'id')->where(function ($query): void {
                    $query->where('tenant_id', $this->user()?->tenant_id);
                }),
            ],
            'pupils' => ['required', 'array', 'min:1', 'max:100'],
            'pupils.*' => ['required', 'array'],
            'pupils.*.mis_key' => ['required', 'string', 'max:255'],
            'pupils.*.given_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pupils.*.family_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pupils.*.date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'pupils.*.year_group' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pupils.*.send_status' => ['sometimes', 'nullable', 'string', Rule::in(SendStatus::values())],
            'pupils.*.evidence_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pupils.*.evidence_provision_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pupils.*.evidence_occurred_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
            'pupils.*.evidence_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
            'pupils.*.evidence_external_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pupils.*.evidence_body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pupils.*.evidence_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pupils.*.evidence_provision' => ['sometimes', 'nullable', 'string'],
            'pupils.*.evidence_provision_label' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $pupils = $this->input('pupils');

                if (! is_array($pupils)) {
                    return;
                }

                $allowed = array_merge(
                    ConnectorField::values(),
                    ImportedInterventionEvidenceUpserter::EVIDENCE_FIELD_HEADERS,
                );

                foreach ($pupils as $index => $pupil) {
                    if (! is_array($pupil)) {
                        continue;
                    }

                    foreach (array_keys($pupil) as $key) {
                        if (! is_string($key) || ! in_array($key, $allowed, true)) {
                            $validator->errors()->add(
                                "pupils.{$index}.{$key}",
                                'This field is not allowed on a Connector sync pupil.',
                            );
                        }
                    }

                    if (! $this->pupilHasEvidenceField($pupil)) {
                        continue;
                    }

                    $externalId = is_string($pupil['evidence_external_id'] ?? null)
                        ? trim($pupil['evidence_external_id'])
                        : '';

                    if ($externalId === '') {
                        $validator->errors()->add(
                            "pupils.{$index}.evidence_external_id",
                            'Evidence external id is required when Evidence fields are present.',
                        );
                    }

                    $provisionCode = is_string($pupil['evidence_provision_code'] ?? null)
                        ? trim($pupil['evidence_provision_code'])
                        : '';

                    if ($provisionCode === '') {
                        $validator->errors()->add(
                            "pupils.{$index}.evidence_provision_code",
                            'Evidence Provision code is required when Evidence fields are present.',
                        );
                    }

                    $occurredAt = $pupil['evidence_occurred_at'] ?? null;
                    $evidenceDate = $pupil['evidence_date'] ?? null;

                    if (($occurredAt === null || $occurredAt === '') && ($evidenceDate === null || $evidenceDate === '')) {
                        $validator->errors()->add(
                            "pupils.{$index}.evidence_occurred_at",
                            'Evidence occurred_at is required when Evidence fields are present.',
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $pupils = $this->input('pupils');

        if (! is_array($pupils)) {
            return;
        }

        foreach ($pupils as $index => $pupil) {
            if (! is_array($pupil)) {
                continue;
            }

            if (isset($pupil['mis_key']) && is_string($pupil['mis_key'])) {
                $pupils[$index]['mis_key'] = trim($pupil['mis_key']);
            }

            if (isset($pupil['evidence_external_id']) && is_string($pupil['evidence_external_id'])) {
                $pupils[$index]['evidence_external_id'] = trim($pupil['evidence_external_id']);
            }
        }

        $this->merge(['pupils' => $pupils]);
    }

    /**
     * @param  array<string, mixed>  $pupil
     */
    private function pupilHasEvidenceField(array $pupil): bool
    {
        foreach (ImportedInterventionEvidenceUpserter::EVIDENCE_FIELD_HEADERS as $header) {
            if (! array_key_exists($header, $pupil)) {
                continue;
            }

            $value = $pupil[$header];

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
