<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreResponseRequest extends FormRequest
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
        $pupilId = $this->input('pupil_id');
        $draft = $this->isDraftIntent();

        $sameTenantPupilIntervention = Rule::exists('evidence_records', 'id')->where(function ($query) use ($tenantId, $pupilId): void {
            $query->where('tenant_id', $tenantId)
                ->where('type', EvidenceType::Intervention->value)
                ->where('lifecycle', EvidenceLifecycle::Submitted->value);

            if (is_string($pupilId) && $pupilId !== '') {
                $query->where('pupil_id', $pupilId);
            }
        });

        return [
            // Setting/Provision FKs and free-text labels are prohibited on Response create.
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
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
            'related_intervention_id' => [
                'nullable',
                'ulid',
                $sameTenantPupilIntervention,
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
            'setting.prohibited' => 'Setting is not used for Pupil Responses.',
            'setting_term_id.prohibited' => 'Setting is not used for Pupil Responses.',
            'provision.prohibited' => 'Provision is not used for Pupil Responses.',
            'provision_term_id.prohibited' => 'Provision is not used for Pupil Responses.',
            'body.required' => 'Pupil Response notes are required.',
            'pupil_id.exists' => 'The selected Pupil could not be found.',
            'related_intervention_id.exists' => 'The selected Intervention must belong to the same Pupil and be a submitted Intervention record.',
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

        if ($this->exists('related_intervention_id') && $this->input('related_intervention_id') === '') {
            $merge['related_intervention_id'] = null;
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
     * @return array{pupil_id: string, occurred_at: string, related_intervention_id: ?string, body: ?string, client_type: string, lifecycle: string}
     */
    public function responsePayload(): array
    {
        /** @var array{pupil_id: string, occurred_at: string, related_intervention_id?: ?string, body?: ?string, client_type?: string, lifecycle?: string} $validated */
        $validated = $this->validated();

        return [
            'pupil_id' => $validated['pupil_id'],
            'occurred_at' => $validated['occurred_at'],
            'related_intervention_id' => $validated['related_intervention_id'] ?? null,
            'body' => $validated['body'] ?? null,
            'client_type' => $validated['client_type'] ?? 'web',
            'lifecycle' => ($validated['lifecycle'] ?? null) === 'draft' ? 'draft' : 'submitted',
        ];
    }
}
