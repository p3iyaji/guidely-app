<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->user()?->tenant;

        if ($tenant === null) {
            return false;
        }

        return $this->user()->can('update', $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cohort_enabled' => ['sometimes', 'boolean'],
            'cohort_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
