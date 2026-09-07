<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;

class StoreResponseRequest extends FormRequest
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
        return $this->responseCreateRules($this->isDraftIntent());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->evidenceCaptureMessages(),
            'setting.prohibited' => 'Setting is not used for Pupil Responses.',
            'setting_term_id.prohibited' => 'Setting is not used for Pupil Responses.',
            'provision.prohibited' => 'Provision is not used for Pupil Responses.',
            'provision_term_id.prohibited' => 'Provision is not used for Pupil Responses.',
            'body.required' => 'Pupil Response notes are required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEvidenceCaptureForValidation(['related_intervention_id']);
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
