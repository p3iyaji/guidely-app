<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\TenantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePilotTenantRequest extends FormRequest
{
    public const COHORT_MODE_SAMPLE = 'sample';

    public const COHORT_MODE_EMPTY = 'empty';

    public function authorize(): bool
    {
        return $this->user()?->can('create-pilot-tenant') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'type' => ['required', 'string', Rule::in(array_column(TenantType::cases(), 'value'))],
            'cohort_mode' => [
                'required',
                'string',
                Rule::in([self::COHORT_MODE_SAMPLE, self::COHORT_MODE_EMPTY]),
            ],
        ];
    }
}
