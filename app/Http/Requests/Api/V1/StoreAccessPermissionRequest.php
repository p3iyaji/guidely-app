<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\AccessPermission;
use App\Http\Requests\Api\V1\Concerns\ValidatesAccessCatalogueFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessPermissionRequest extends FormRequest
{
    use ValidatesAccessCatalogueFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', AccessPermission::class) ?? false;
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
        return $this->accessPermissionRules(partial: false);
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
