<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SubmitDraftRequest extends FormRequest
{
    use ValidatesEvidenceCapture;

    public function authorize(): bool
    {
        $draft = $this->route('draft');

        if (is_string($draft) && $draft !== '') {
            $draft = EvidenceRecord::query()->find($draft);
        }

        if (! $draft instanceof EvidenceRecord || $draft->type === EvidenceType::ReviewNote) {
            return false;
        }

        return $this->user()?->can('submit', $draft) ?? false;
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
        if ($draft->type === EvidenceType::ReviewNote) {
            abort(403);
        }

        return match ($draft->type) {
            EvidenceType::Observation => $this->observationDraftMutationRules(submit: true),
            EvidenceType::Intervention => $this->interventionDraftMutationRules(submit: true),
            EvidenceType::Response => $this->responseDraftMutationRules(submit: true),
            EvidenceType::ReviewNote => abort(403),
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->evidenceCaptureMessages(),
            'body.required' => 'Notes are required to submit this draft.',
            'lifecycle.prohibited' => 'Lifecycle is set by submit.',
            'type.prohibited' => 'Evidence type cannot be changed on submit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('body') && is_string($this->input('body'))) {
            $trimmed = Str::of($this->input('body'))->trim()->toString();
            /** @var EvidenceRecord $draft */
            $draft = $this->route('draft');
            $merge['body'] = ($trimmed === '' && $draft->type === EvidenceType::Intervention)
                ? null
                : $trimmed;
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
     * @return array{pupil_id: string, occurred_at: string, setting_term_id: ?string, provision_term_id: ?string, related_intervention_id: ?string, body: ?string, client_type: string}
     */
    public function submitPayload(): array
    {
        /** @var array{pupil_id: string, occurred_at: string, setting_term_id?: ?string, provision_term_id?: ?string, related_intervention_id?: ?string, body?: ?string, client_type?: string} $validated */
        $validated = $this->validated();

        return [
            'pupil_id' => $validated['pupil_id'],
            'occurred_at' => $validated['occurred_at'],
            'setting_term_id' => $validated['setting_term_id'] ?? null,
            'provision_term_id' => $validated['provision_term_id'] ?? null,
            'related_intervention_id' => $validated['related_intervention_id'] ?? null,
            'body' => $validated['body'] ?? null,
            'client_type' => $validated['client_type'] ?? 'web',
        ];
    }
}
