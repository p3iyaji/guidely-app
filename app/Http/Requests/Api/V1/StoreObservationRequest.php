<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;

class StoreObservationRequest extends FormRequest
{
    use ValidatesEvidenceCapture;

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
        return $this->observationCreateRules($this->isDraftIntent());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->evidenceCaptureMessages(),
            'provision.prohibited' => 'Provision is not used for Observations.',
            'provision_term_id.prohibited' => 'Provision is not used for Observations.',
            'related_intervention_id.prohibited' => 'Related Intervention is not used for Observations.',
            'body.required' => 'What was observed is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEvidenceCaptureForValidation(['setting_term_id']);
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
