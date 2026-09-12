<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\RelationshipMapping;
use App\Http\Requests\Api\V1\Concerns\ValidatesRelationshipMappingFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreRelationshipMappingRequest extends FormRequest
{
    use ValidatesRelationshipMappingFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', RelationshipMapping::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRelationshipMappingFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->relationshipMappingRules(partial: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->relationshipMappingMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->relationshipMappingAfterHooks();
    }
}
