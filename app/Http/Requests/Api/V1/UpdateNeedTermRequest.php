<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\NeedTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesNeedTermFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNeedTermRequest extends FormRequest
{
    use ValidatesNeedTermFields;

    public function authorize(): bool
    {
        $term = $this->route('needTerm');

        return $term instanceof NeedTerm
            && ($this->user()?->can('update', $term) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareNeedTermFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->needTermRules(partial: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->needTermMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->needTermAfterHooks();
    }
}
