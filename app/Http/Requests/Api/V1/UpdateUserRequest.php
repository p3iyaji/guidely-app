<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\CurrentTenant;
use App\Http\Requests\Api\V1\Concerns\ValidatesTenantAssignableRoles;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ValidatesTenantAssignableRoles;

    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $tenantId = CurrentTenant::id();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['sometimes', 'string', Rule::in($this->tenantAssignableRoleValues())],
            'school_ids' => ['sometimes', 'array'],
            'school_ids.*' => [
                'required',
                'string',
                Rule::exists('schools', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
