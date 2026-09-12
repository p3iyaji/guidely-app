<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\OutcomeTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesOutcomeTermFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreOutcomeTermRequest extends FormRequest
{
    use ValidatesOutcomeTermFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', OutcomeTerm::class) ?? false;
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
        return $this->outcomeTermRules(partial: false);
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
