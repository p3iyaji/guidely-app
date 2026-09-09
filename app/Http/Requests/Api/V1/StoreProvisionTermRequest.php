<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\ProvisionTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesProvisionTermFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreProvisionTermRequest extends FormRequest
{
    use ValidatesProvisionTermFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProvisionTerm::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProvisionTermFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->provisionTermRules(partial: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->provisionTermMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->provisionTermAfterHooks();
    }
}
