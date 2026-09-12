<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\ThresholdTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesThresholdTermFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreThresholdTermRequest extends FormRequest
{
    use ValidatesThresholdTermFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ThresholdTerm::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareThresholdTermFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->thresholdTermRules(partial: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->thresholdTermMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->thresholdTermAfterHooks();
    }
}
