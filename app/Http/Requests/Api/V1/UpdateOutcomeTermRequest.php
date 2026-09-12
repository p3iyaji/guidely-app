<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\OutcomeTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesOutcomeTermFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOutcomeTermRequest extends FormRequest
{
    use ValidatesOutcomeTermFields;

    public function authorize(): bool
    {
        $term = $this->route('outcomeTerm');

        return $term instanceof OutcomeTerm
            && ($this->user()?->can('update', $term) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareOutcomeTermFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->outcomeTermRules(partial: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->outcomeTermMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->outcomeTermAfterHooks();
    }
}
