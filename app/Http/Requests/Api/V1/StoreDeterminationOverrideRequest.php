<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Sre\Determination;
use App\Domain\Sre\GapMaterialiser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeterminationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $determination = $this->route('determination');

        if ($user === null || ! $determination instanceof Determination) {
            return false;
        }

        return $user->can('override', $determination);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rationale' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rationale.required' => 'A rationale of at least 20 characters is required.',
            'rationale.min' => 'A rationale of at least 20 characters is required.',
            'rationale.max' => 'A rationale may not be greater than 5000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rationale = $this->input('rationale');

        if (is_string($rationale)) {
            $this->merge([
                'rationale' => trim($rationale),
            ]);
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $determination = $this->route('determination');

                if (! $determination instanceof Determination) {
                    return;
                }

                if ($this->isOverridable($determination)) {
                    return;
                }

                $validator->errors()->add('determination', 'This Determination is not overridable.');
            },
        ];
    }

    public function rationale(): string
    {
        return $this->string('rationale')->toString();
    }

    private function isOverridable(Determination $determination): bool
    {
        if ($determination->is_current !== true || $determination->dimension === null) {
            return false;
        }

        return in_array($determination->result, GapMaterialiser::GAP_OPENING_RESULTS, true);
    }
}
