<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Role;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewNoteRequest extends FormRequest
{
    use ValidatesEvidenceCapture;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isActiveTenantStaff()) {
            return false;
        }

        // Role gate only — Pupil school scope is checked in the controller.
        return $user->role === Role::Senco;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reviewNoteCreateRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->evidenceCaptureMessages(),
            'setting_term_id.prohibited' => 'Setting is not used for review notes.',
            'provision_term_id.prohibited' => 'Provision is not used for review notes.',
            'related_intervention_id.prohibited' => 'Related Intervention is not used for review notes.',
            'lifecycle.prohibited' => 'Review notes cannot be saved as drafts.',
            'body.required' => 'Review note commentary is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEvidenceCaptureForValidation();
    }

    /**
     * @return array{pupil_id: string, occurred_at: string, body: string, client_type: string}
     */
    public function reviewNotePayload(): array
    {
        /** @var array{pupil_id: string, occurred_at: string, body: string, client_type?: string} $validated */
        $validated = $this->validated();

        return [
            'pupil_id' => $validated['pupil_id'],
            'occurred_at' => $validated['occurred_at'],
            'body' => $validated['body'],
            'client_type' => $validated['client_type'] ?? 'web',
        ];
    }
}
