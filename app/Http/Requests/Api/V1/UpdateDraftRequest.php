<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftRequest extends FormRequest
{
    use ValidatesEvidenceCapture;

    public function authorize(): bool
    {
        /** @var EvidenceRecord|null $draft */
        $draft = $this->route('draft');

        if (is_string($draft) && $draft !== '') {
            $draft = EvidenceRecord::query()->find($draft);
        }

        if (! $draft instanceof EvidenceRecord || $draft->type === EvidenceType::ReviewNote) {
            return false;
        }

        return $this->user()?->can('update', $draft) ?? false;
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
            EvidenceType::Observation => $this->observationDraftMutationRules(submit: false),
            EvidenceType::Intervention => $this->interventionDraftMutationRules(submit: false),
            EvidenceType::Response => $this->responseDraftMutationRules(submit: false),
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
            'lifecycle.prohibited' => 'Lifecycle cannot be changed on draft update.',
            'type.prohibited' => 'Evidence type cannot be changed on draft update.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEvidenceCaptureForValidation([
            'setting_term_id',
            'provision_term_id',
            'related_intervention_id',
        ]);
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
}
