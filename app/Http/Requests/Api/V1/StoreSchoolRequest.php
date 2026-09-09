<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\School;
use App\Http\Requests\Api\V1\Concerns\ValidatesSchoolAddressFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    use ValidatesSchoolAddressFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', School::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->blankAddressFieldsToNull();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->schoolAddressRules(),
        ];
    }
}
