<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tenant-libraries') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ontology_version_id' => [
                'nullable',
                'required_without:rule_library_version_id',
                'ulid',
                Rule::exists('ontology_versions', 'id'),
            ],
            'rule_library_version_id' => [
                'nullable',
                'required_without:ontology_version_id',
                'ulid',
                Rule::exists('rule_library_versions', 'id'),
            ],
        ];
    }
}
