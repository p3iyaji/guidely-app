<?php

namespace App\Http\Requests\Api\V1\Concerns;

trait ValidatesSchoolAddressFields
{
    /**
     * @return array<string, mixed>
     */
    protected function schoolAddressRules(): array
    {
        return [
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:16'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function blankAddressFieldsToNull(): void
    {
        foreach (['address', 'postcode', 'city', 'county', 'country'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
