<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Tenancy\CurrentTenant;
use Database\Seeders\ProvisionOntologySeeder;
use Database\Seeders\SettingOntologySeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared Ontology / XOR / client_type rules for Evidence create, draft update, and submit.
 */
trait ValidatesEvidenceCapture
{
    protected function activeSettingTermRule(): mixed
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

    protected function activeProvisionTermRule(): mixed
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

    protected function sameTenantPupilInterventionRule(?string $pupilId = null): mixed
    {
        $tenantId = CurrentTenant::id();
        $resolvedPupilId = $pupilId ?? $this->input('pupil_id');

        return Rule::exists('evidence_records', 'id')->where(function ($query) use ($tenantId, $resolvedPupilId): void {
            $query->where('tenant_id', $tenantId)
                ->where('type', EvidenceType::Intervention->value)
                ->where('lifecycle', EvidenceLifecycle::Submitted->value);

            if (is_string($resolvedPupilId) && $resolvedPupilId !== '') {
                $query->where('pupil_id', $resolvedPupilId);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function evidencePupilIdRule(bool $sometimes = false): array
    {
        $tenantId = CurrentTenant::id();

        $rule = [
            'required',
            'ulid',
            Rule::exists('pupils', 'id')->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at');
            }),
        ];

        return $sometimes ? ['sometimes', ...$rule] : $rule;
    }

    /**
     * @return list<string|object>
     */
    protected function evidenceOccurredAtRule(bool $sometimes = false): array
    {
        $rule = ['required', 'date', 'before_or_equal:now'];

        return $sometimes ? ['sometimes', ...$rule] : $rule;
    }

    /**
     * @return list<string|object>
     */
    protected function evidenceClientTypeRule(): array
    {
        return ['sometimes', 'string', Rule::in(['web', 'hybrid'])];
    }

    /**
     * @return array<string, mixed>
     */
    protected function observationCreateRules(bool $draft): array
    {
        return [
            'setting' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'lifecycle' => ['sometimes', 'string', Rule::in(['draft'])],
            'pupil_id' => $this->evidencePupilIdRule(),
            'occurred_at' => $this->evidenceOccurredAtRule(),
            'setting_term_id' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'ulid',
                $this->activeSettingTermRule(),
            ],
            'body' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'string',
                'max:5000',
            ],
            'client_type' => $this->evidenceClientTypeRule(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function interventionCreateRules(bool $draft): array
    {
        return [
            'provision' => ['prohibited'],
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'lifecycle' => ['sometimes', 'string', Rule::in(['draft'])],
            'pupil_id' => $this->evidencePupilIdRule(),
            'occurred_at' => $this->evidenceOccurredAtRule(),
            'provision_term_id' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'ulid',
                $this->activeProvisionTermRule(),
            ],
            'body' => ['nullable', 'string', 'max:5000'],
            'client_type' => $this->evidenceClientTypeRule(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseCreateRules(bool $draft): array
    {
        return [
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'lifecycle' => ['sometimes', 'string', Rule::in(['draft'])],
            'pupil_id' => $this->evidencePupilIdRule(),
            'occurred_at' => $this->evidenceOccurredAtRule(),
            'related_intervention_id' => [
                'nullable',
                'ulid',
                $this->sameTenantPupilInterventionRule(),
            ],
            'body' => [
                Rule::requiredIf(! $draft),
                'nullable',
                'string',
                'max:5000',
            ],
            'client_type' => $this->evidenceClientTypeRule(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function draftMutationBaseRules(bool $submit): array
    {
        return [
            'lifecycle' => ['prohibited'],
            'type' => ['prohibited'],
            'pupil_id' => $this->evidencePupilIdRule(sometimes: ! $submit),
            'occurred_at' => $this->evidenceOccurredAtRule(sometimes: ! $submit),
            'client_type' => $this->evidenceClientTypeRule(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function observationDraftMutationRules(bool $submit): array
    {
        return [
            ...$this->draftMutationBaseRules($submit),
            'setting' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'setting_term_id' => $submit
                ? ['required', 'ulid', $this->activeSettingTermRule()]
                : ['sometimes', 'nullable', 'ulid', $this->activeSettingTermRule()],
            'body' => $submit
                ? ['required', 'string', 'max:5000']
                : ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function interventionDraftMutationRules(bool $submit): array
    {
        return [
            ...$this->draftMutationBaseRules($submit),
            'provision' => ['prohibited'],
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'provision_term_id' => $submit
                ? ['required', 'ulid', $this->activeProvisionTermRule()]
                : ['sometimes', 'nullable', 'ulid', $this->activeProvisionTermRule()],
            'body' => $submit
                ? ['nullable', 'string', 'max:5000']
                : ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseDraftMutationRules(bool $submit): array
    {
        return [
            ...$this->draftMutationBaseRules($submit),
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => $submit
                ? [
                    'nullable',
                    'ulid',
                    $this->sameTenantPupilInterventionRule(),
                ]
                : [
                    'sometimes',
                    'nullable',
                    'ulid',
                    $this->sameTenantPupilInterventionRule(),
                ],
            'body' => $submit
                ? ['required', 'string', 'max:5000']
                : ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function amendBaseRules(): array
    {
        return [
            'lifecycle' => ['prohibited'],
            'type' => ['prohibited'],
            'pupil_id' => ['prohibited'],
            'author_id' => ['prohibited'],
            'source' => ['prohibited'],
            'external_id' => ['prohibited'],
            'occurred_at' => $this->evidenceOccurredAtRule(),
            'client_type' => $this->evidenceClientTypeRule(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function observationAmendRules(): array
    {
        return [
            ...$this->amendBaseRules(),
            'setting' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'setting_term_id' => ['required', 'ulid', $this->activeSettingTermRule()],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function interventionAmendRules(): array
    {
        return [
            ...$this->amendBaseRules(),
            'provision' => ['prohibited'],
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'related_intervention_id' => ['prohibited'],
            'provision_term_id' => ['required', 'ulid', $this->activeProvisionTermRule()],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseAmendRules(string $pupilId): array
    {
        return [
            ...$this->amendBaseRules(),
            'setting' => ['prohibited'],
            'setting_term_id' => ['prohibited'],
            'provision' => ['prohibited'],
            'provision_term_id' => ['prohibited'],
            'related_intervention_id' => [
                'nullable',
                'ulid',
                $this->sameTenantPupilInterventionRule($pupilId),
            ],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function evidenceCaptureMessages(): array
    {
        return [
            'setting.prohibited' => 'Setting must use an Ontology term id, not a free-text label.',
            'provision.prohibited' => 'Provision must use an Ontology term id, not a free-text label.',
            'setting_term_id.required' => 'A Setting Ontology term is required.',
            'setting_term_id.exists' => 'The selected Setting must be an active published Ontology term.',
            'provision_term_id.required' => 'A Provision Ontology term is required.',
            'provision_term_id.exists' => 'The selected Provision must be an active published Ontology term.',
            'related_intervention_id.exists' => 'The selected Intervention must belong to the same Pupil and be a submitted Intervention record.',
            'pupil_id.exists' => 'The selected Pupil could not be found.',
            'occurred_at.before_or_equal' => 'Session date and time cannot be in the future.',
            'client_type.in' => 'Client type must be web or hybrid.',
            'lifecycle.in' => 'Lifecycle must be draft when saving a draft.',
        ];
    }

    /**
     * @param  list<string>  $emptyableFields
     */
    protected function prepareEvidenceCaptureForValidation(array $emptyableFields = []): void
    {
        $merge = [];

        if ($this->exists('body') && is_string($this->input('body'))) {
            $trimmed = Str::of($this->input('body'))->trim()->toString();
            $merge['body'] = $trimmed === '' ? null : $trimmed;
        }

        foreach ($emptyableFields as $field) {
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
}
