<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSsoRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        foreach (['sso_provider', 'sso_entity_id', 'sso_client_id'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sso_enabled' => ['sometimes', 'boolean'],
            'sso_provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sso_entity_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sso_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
