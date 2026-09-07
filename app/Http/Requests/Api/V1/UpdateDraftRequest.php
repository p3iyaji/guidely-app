<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Tenancy\CurrentTenant;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EvidenceRecord|null $draft */
        $draft = $this->route('draft');

        if (is_string($draft) && $draft !== '') {
            $draft = EvidenceRecord::query()->find($draft);
        }

        return $draft instanceof EvidenceRecord
            && ($this->user()?->can('update', $draft) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $draft = $this->route('draft');

        if (is_string($draft) && $draft !== '') {
            $draft = EvidenceRecord::query()->findOrFail($draft);
        }

        /** @var EvidenceRecord $draft */
        $tenantId = CurrentTenant::id();

        $base = [
            'lifecycle' => ['prohibited'],
            'type' => ['prohibited'],
            'pupil_id' => [
                'sometimes',
                'required',
                'ulid',
                Rule::exists('pupils', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                }),
            ],
            'occurred_at' => ['sometimes', 'required', 'date', 'before_or_equal:now'],
            'client_type' => ['sometimes', 'string', Rule::in(['web', 'hybrid'])],
        ];

        return match ($draft->type) {
            EvidenceType::Observation => [
                ...$base,
                'setting' => ['prohibited'],
                'provision' => ['prohibited'],
                'provision_term_id' => ['prohibited'],
                'related_intervention_id' => ['prohibited'],
                'setting_term_id' => ['sometimes', 'nullable', 'ulid', $this->activeSettingTermRule()],
                'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
            EvidenceType::Intervention => [
                ...$base,
                'provision' => ['prohibited'],
                'setting' => ['prohibited'],
                'setting_term_id' => ['prohibited'],
                'related_intervention_id' => ['prohibited'],
                'provision_term_id' => ['sometimes', 'nullable', 'ulid', $this->activeProvisionTermRule()],
                'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
            EvidenceType::Response => [
                ...$base,
                'setting' => ['prohibited'],
                'setting_term_id' => ['prohibited'],
                'provision' => ['prohibited'],
                'provision_term_id' => ['prohibited'],
                'related_intervention_id' => [
                    'sometimes',
                    'nullable',
                    'ulid',
                    $this->sameTenantPupilInterventionRule(),
                ],
                'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'setting.prohibited' => 'Setting must use an Ontology term id, not a free-text label.',
            'provision.prohibited' => 'Provision must use an Ontology term id, not a free-text label.',
            'setting_term_id.exists' => 'The selected Setting must be an active published Ontology term.',
            'provision_term_id.exists' => 'The selected Provision must be an active published Ontology term.',
            'related_intervention_id.exists' => 'The selected Intervention must belong to the same Pupil and be a submitted Intervention record.',
            'pupil_id.exists' => 'The selected Pupil could not be found.',
            'occurred_at.before_or_equal' => 'Session date and time cannot be in the future.',
            'client_type.in' => 'Client type must be web or hybrid.',
            'lifecycle.prohibited' => 'Lifecycle cannot be changed on draft update.',
            'type.prohibited' => 'Evidence type cannot be changed on draft update.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('body') && is_string($this->input('body'))) {
            $trimmed = Str::of($this->input('body'))->trim()->toString();
            $merge['body'] = $trimmed === '' ? null : $trimmed;
        }

        foreach (['setting_term_id', 'provision_term_id', 'related_intervention_id'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        $clientType = $this->input('client_type')
            ?? $this->header('X-Client-Type')
            ?? $this->header('X-Guidely-Client-Type');

        if (is_string($clientType) && $clientType !== '') {
            $merge['client_type'] = Str::lower(trim($clientType));
        } elseif (! $this->exists('client_type')) {
            $merge['client_type'] = 'web';
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array{client_type: string, pupil_id?: string, occurred_at?: string, setting_term_id?: ?string, provision_term_id?: ?string, related_intervention_id?: ?string, body?: ?string}
     */
    public function draftPayload(): array
    {
        /** @var array{pupil_id?: string, occurred_at?: string, setting_term_id?: ?string, provision_term_id?: ?string, related_intervention_id?: ?string, body?: ?string, client_type?: string} $validated */
        $validated = $this->validated();

        $payload = [
            'client_type' => $validated['client_type'] ?? 'web',
        ];

        foreach (['pupil_id', 'occurred_at', 'setting_term_id', 'provision_term_id', 'related_intervention_id', 'body'] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $payload;
    }

    private function activeSettingTermRule(): mixed
    {
        return Rule::exists('setting_terms', 'id')->where(function ($query): void {
            $query->where('is_active', true)
                ->whereIn('ontology_version_id', function ($versionQuery): void {
                    $versionQuery->select('id')
                        ->from('ontology_versions')
                        ->where('status', 'published')
                        ->where('code', SettingOntologySeeder::STUB_VERSION_CODE);
                });
        });
    }

    private function activeProvisionTermRule(): mixed
    {
        return Rule::exists('provision_terms', 'id')->where(function ($query): void {
            $query->where('is_active', true)
                ->whereIn('ontology_version_id', function ($versionQuery): void {
                    $versionQuery->select('id')
                        ->from('ontology_versions')
                        ->where('status', 'published')
                        ->where('code', ProvisionOntologySeeder::STUB_VERSION_CODE);
                });
        });
    }

    private function sameTenantPupilInterventionRule(): mixed
    {
        $tenantId = CurrentTenant::id();
        $pupilId = $this->input('pupil_id');

        return Rule::exists('evidence_records', 'id')->where(function ($query) use ($tenantId, $pupilId): void {
            $query->where('tenant_id', $tenantId)
                ->where('type', EvidenceType::Intervention->value)
                ->where('lifecycle', EvidenceLifecycle::Submitted->value);

            if (is_string($pupilId) && $pupilId !== '') {
                $query->where('pupil_id', $pupilId);
            }
        });
    }
}
