<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\School;
use App\Http\Requests\Api\V1\Concerns\ValidatesSchoolAddressFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    use ValidatesSchoolAddressFields;

    public function authorize(): bool
    {
        /** @var School $school */
        $school = $this->route('school');

        return $this->user()?->can('update', $school) ?? false;
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
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->schoolAddressRules(),
        ];
    }
}
