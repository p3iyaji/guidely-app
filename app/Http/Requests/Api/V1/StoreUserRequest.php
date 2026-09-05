<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\CurrentTenant;
use App\Http\Requests\Api\V1\Concerns\ValidatesTenantAssignableRoles;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidatesTenantAssignableRoles;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('external_id') && $this->input('external_id') === '') {
            $this->merge(['external_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', Password::defaults()],
            'external_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'external_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'role' => ['required', 'string', Rule::in($this->tenantAssignableRoleValues())],
            'school_ids' => ['sometimes', 'array'],
            'school_ids.*' => [
                'required',
                'string',
                Rule::exists('schools', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
