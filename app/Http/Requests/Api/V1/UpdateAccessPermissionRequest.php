<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\AccessPermission;
use App\Http\Requests\Api\V1\Concerns\ValidatesAccessCatalogueFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessPermissionRequest extends FormRequest
{
    use ValidatesAccessCatalogueFields;

    public function authorize(): bool
    {
        $permission = $this->route('accessPermission');

        return $permission instanceof AccessPermission
            && ($this->user()?->can('update', $permission) ?? false);
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
        return $this->accessPermissionRules(partial: true);
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
