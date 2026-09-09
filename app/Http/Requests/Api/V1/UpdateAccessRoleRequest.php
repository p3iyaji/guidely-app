<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\AccessRole;
use App\Http\Requests\Api\V1\Concerns\ValidatesAccessCatalogueFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessRoleRequest extends FormRequest
{
    use ValidatesAccessCatalogueFields;

    public function authorize(): bool
    {
        $role = $this->route('accessRole');

        return $role instanceof AccessRole
            && ($this->user()?->can('update', $role) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareAccessCatalogueFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->accessRoleRules(partial: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->accessCatalogueMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->accessCatalogueAfterHooks();
    }
}
