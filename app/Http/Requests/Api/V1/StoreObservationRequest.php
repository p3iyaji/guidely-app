<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Tenancy\CurrentTenant;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvidenceRecord::class) ?? false;
    }

    public function isDraftIntent(): bool
    {
        return $this->input('lifecycle') === 'draft';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();
        $draft = $this->isDraftIntent();

        $activePublishedStubTerm = Rule::exists('setting_terms', 'id')->where(function ($query): void {
            $query->where('is_active', true)
                ->whereIn('ontology_version_id', function ($versionQuery): void {
                    $versionQuery->select('id')
                        ->from('ontology_versions')
                        ->where('status', 'published')
                        ->where('code', SettingOntologySeeder::STUB_VERSION_CODE);
                });
        });

        return [
            // Free-text setting labels are never accepted — Ontology term ids only.
            'setting' => ['prohibited'],
            // Intervention / Response fields must not be submitted on Observation create.
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'lifecycle' => ['sometimes', 'string', Rule::in(['draft'])],
            'pupil_id' => [
                'required',
                'ulid',
                Rule::exists('pupils', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                }),
            ],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'setting_term_id' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'ulid',
                $activePublishedStubTerm,
            ],
            'body' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'string',
                'max:5000',
            ],
            'client_type' => ['sometimes', 'string', Rule::in(['web', 'hybrid'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'setting.prohibited' => 'Setting must use an Ontology term id, not a free-text label.',
            'provision.prohibited' => 'Provision is not used for Observations.',
            'provision_term_id.prohibited' => 'Provision is not used for Observations.',
            'related_intervention_id.prohibited' => 'Related Intervention is not used for Observations.',
            'setting_term_id.required' => 'A Setting Ontology term is required.',
            'setting_term_id.exists' => 'The selected Setting must be an active published Ontology term.',
            'body.required' => 'What was observed is required.',
            'pupil_id.exists' => 'The selected Pupil could not be found.',
            'occurred_at.before_or_equal' => 'Session date and time cannot be in the future.',
            'client_type.in' => 'Client type must be web or hybrid.',
            'lifecycle.in' => 'Lifecycle must be draft when saving a draft.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('body') && is_string($this->input('body'))) {
            $trimmed = Str::of($this->input('body'))->trim()->toString();
            $merge['body'] = $trimmed === '' ? null : $trimmed;
        }

        if ($this->exists('setting_term_id') && $this->input('setting_term_id') === '') {
            $merge['setting_term_id'] = null;
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
     * @return array{pupil_id: string, occurred_at: string, setting_term_id: ?string, body: ?string, client_type: string, lifecycle: string}
     */
    public function observationPayload(): array
    {
        /** @var array{pupil_id: string, occurred_at: string, setting_term_id?: ?string, body?: ?string, client_type?: string, lifecycle?: string} $validated */
        $validated = $this->validated();

        return [
            'pupil_id' => $validated['pupil_id'],
            'occurred_at' => $validated['occurred_at'],
            'setting_term_id' => $validated['setting_term_id'] ?? null,
            'body' => $validated['body'] ?? null,
            'client_type' => $validated['client_type'] ?? 'web',
            'lifecycle' => ($validated['lifecycle'] ?? null) === 'draft' ? 'draft' : 'submitted',
        ];
    }
}
