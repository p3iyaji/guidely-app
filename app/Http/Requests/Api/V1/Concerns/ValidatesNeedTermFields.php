<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesNeedTermFields
{
    protected function prepareNeedTermFields(): void
    {
        if ($this->exists('code')) {
            $this->merge([
                'code' => Str::upper(trim((string) $this->input('code'))),
            ]);
        }

        if ($this->exists('label')) {
            $this->merge([
                'label' => trim((string) $this->input('label')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function needTermRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $versionId = app(EffectiveOntologyVersion::class)->id();
        $ignore = $this->route('needTerm');
        $ignoreId = $ignore instanceof NeedTerm ? $ignore->id : null;

        $unique = Rule::unique('need_terms', 'code')
            ->where(fn ($query) => $query->where('ontology_version_id', $versionId ?? ''));

        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        return [
            'code' => [
                $presence,
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                $unique,
            ],
            'label' => [$presence, 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function needTermMessages(): array
    {
        return [
            'code.regex' => 'The code may contain only letters, numbers, hyphens, and underscores.',
            'code.unique' => 'A Need term with this code already exists on this Ontology version.',
        ];
    }

    /**
     * @return list<\Closure>
     */
    protected function needTermAfterHooks(): array
    {
        return [
            function (Validator $validator): void {
                if (app(EffectiveOntologyVersion::class)->id() !== null) {
                    return;
                }

                $validator->errors()->add(
                    'code',
                    'No published Ontology version is available for this Tenant.',
                );
            },
        ];
    }
}
