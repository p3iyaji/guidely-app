<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\OutcomeTerm;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Ontology\RelationshipMapping;
use App\Domain\Ontology\SettingTerm;
use App\Domain\Ontology\ThresholdTerm;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesRelationshipMappingFields
{
    /**
     * @return array<string, class-string>
     */
    protected function relationshipMappingDomainModels(): array
    {
        return [
            'need' => NeedTerm::class,
            'setting' => SettingTerm::class,
            'provision' => ProvisionTerm::class,
            'outcome' => OutcomeTerm::class,
            'threshold' => ThresholdTerm::class,
        ];
    }

    protected function prepareRelationshipMappingFields(): void
    {
        if ($this->exists('code')) {
            $this->merge([
                'code' => Str::upper(trim((string) $this->input('code'))),
            ]);
        }

        foreach (['label', 'relationship_type', 'from_domain', 'to_domain'] as $field) {
            if ($this->exists($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function relationshipMappingRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $versionId = app(EffectiveOntologyVersion::class)->id();
        $ignore = $this->route('relationshipMapping');
        $ignoreId = $ignore instanceof RelationshipMapping ? $ignore->id : null;

        $unique = Rule::unique('relationship_mappings', 'code')
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
            'relationship_type' => [
                $presence,
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'from_domain' => [$presence, 'string', Rule::in(array_keys($this->relationshipMappingDomainModels()))],
            'from_term_id' => [
                $presence,
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $model = $this->relationshipMappingTermModel('from_domain');

                    if ($model === null) {
                        return;
                    }

                    $exists = $versionId !== null
                        ? $model::query()
                            ->forVersion($versionId)
                            ->whereKey($value)
                            ->exists()
                        : false;

                    if (! $exists) {
                        $fail('The selected source term does not exist on this Ontology version.');
                    }
                },
            ],
            'to_domain' => [$presence, 'string', Rule::in(array_keys($this->relationshipMappingDomainModels()))],
            'to_term_id' => [
                $presence,
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $model = $this->relationshipMappingTermModel('to_domain');

                    if ($model === null) {
                        return;
                    }

                    $exists = $versionId !== null
                        ? $model::query()
                            ->forVersion($versionId)
                            ->whereKey($value)
                            ->exists()
                        : false;

                    if (! $exists) {
                        $fail('The selected target term does not exist on this Ontology version.');
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function relationshipMappingMessages(): array
    {
        return [
            'code.regex' => 'The code may contain only letters, numbers, hyphens, and underscores.',
            'code.unique' => 'A Relationship mapping with this code already exists on this Ontology version.',
            'relationship_type.regex' => 'The relationship type must be lowercase snake_case (for example need_to_provision).',
        ];
    }

    /**
     * @return list<\Closure>
     */
    protected function relationshipMappingAfterHooks(): array
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

    /**
     * @return class-string|null
     */
    private function relationshipMappingTermModel(string $domainField): ?string
    {
        $domain = (string) $this->input($domainField);

        return $this->relationshipMappingDomainModels()[$domain] ?? null;
    }
}
