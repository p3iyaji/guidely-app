<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\NeedTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesNeedTermFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreNeedTermRequest extends FormRequest
{
    use ValidatesNeedTermFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', NeedTerm::class) ?? false;
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
        return $this->needTermRules(partial: false);
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
