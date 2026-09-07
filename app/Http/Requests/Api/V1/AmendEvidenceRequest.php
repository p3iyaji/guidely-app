<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;
use App\Http\Requests\Api\V1\Concerns\ValidatesEvidenceCapture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AmendEvidenceRequest extends FormRequest
{
    use ValidatesEvidenceCapture;

    public function authorize(): bool
    {
        $evidence = $this->route('evidence');

        if (is_string($evidence) && $evidence !== '') {
            $evidence = EvidenceRecord::query()->find($evidence);
        }

        if (! $evidence instanceof EvidenceRecord || $evidence->type === EvidenceType::ReviewNote) {
            return false;
        }

        return $this->user()?->can('amend', $evidence) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $evidence = $this->route('evidence');

        if (is_string($evidence) && $evidence !== '') {
            $evidence = EvidenceRecord::query()->findOrFail($evidence);
        }

        /** @var EvidenceRecord $evidence */
        if ($evidence->type === EvidenceType::ReviewNote) {
            abort(403);
        }

        return match ($evidence->type) {
            EvidenceType::Observation => $this->observationAmendRules(),
            EvidenceType::Intervention => $this->interventionAmendRules(),
            EvidenceType::Response => $this->responseAmendRules((string) $evidence->pupil_id),
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
            'body.required' => 'Notes are required to amend this Evidence Record.',
            'lifecycle.prohibited' => 'Lifecycle cannot be changed on amend.',
            'type.prohibited' => 'Evidence type cannot be changed on amend.',
            'pupil_id.prohibited' => 'Pupil cannot be changed on amend.',
            'author_id.prohibited' => 'Author cannot be changed on amend.',
            'source.prohibited' => 'Source cannot be changed on amend.',
            'external_id.prohibited' => 'External id cannot be changed on amend.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('body') && is_string($this->input('body'))) {
            $trimmed = Str::of($this->input('body'))->trim()->toString();
            $evidence = $this->route('evidence');

            if (is_string($evidence) && $evidence !== '') {
                $evidence = EvidenceRecord::query()->find($evidence);
            }

            $merge['body'] = ($trimmed === ''
                && $evidence instanceof EvidenceRecord
                && $evidence->type === EvidenceType::Intervention)
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
     * @return array{occurred_at: string, setting_term_id: ?string, provision_term_id: ?string, related_intervention_id: ?string, body: ?string, client_type: string}
     */
    public function amendPayload(): array
    {
        /** @var array{occurred_at: string, setting_term_id?: ?string, provision_term_id?: ?string, related_intervention_id?: ?string, body?: ?string, client_type?: string} $validated */
        $validated = $this->validated();

        return [
            'occurred_at' => $validated['occurred_at'],
            'setting_term_id' => $validated['setting_term_id'] ?? null,
            'provision_term_id' => $validated['provision_term_id'] ?? null,
            'related_intervention_id' => $validated['related_intervention_id'] ?? null,
            'body' => $validated['body'] ?? null,
            'client_type' => $validated['client_type'] ?? 'web',
        ];
    }
}
