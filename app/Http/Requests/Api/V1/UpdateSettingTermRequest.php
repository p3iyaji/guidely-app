<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Ontology\SettingTerm;
use App\Http\Requests\Api\V1\Concerns\ValidatesSettingTermFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingTermRequest extends FormRequest
{
    use ValidatesSettingTermFields;

    public function authorize(): bool
    {
        $term = $this->route('settingTerm');

        return $term instanceof SettingTerm
            && ($this->user()?->can('update', $term) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSettingTermFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->settingTermRules(partial: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->settingTermMessages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return $this->settingTermAfterHooks();
    }
}
