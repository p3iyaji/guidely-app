<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Pupils\Pupil;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPupilNeedFields
{
    /**
     * @return array<string, mixed>
     */
    protected function needFieldRules(): array
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        $activeEffectiveTerm = Rule::exists('need_terms', 'id')->where(function ($query) use ($versionId): void {
            $query->where('is_active', true);

            if ($versionId === null) {
                $query->whereRaw('0 = 1');

                return;
            }

            $query->where('ontology_version_id', $versionId);
        });

        return [
            // Free-text Need / diagnosis labels as the category are never accepted.
            'primary_need' => ['prohibited'],
            'secondary_need' => ['prohibited'],
            'need' => ['prohibited'],
            'diagnosis' => ['prohibited'],
            'primary_need_term_id' => [
                'nullable',
                'ulid',
                $activeEffectiveTerm,
            ],
            'primary_need_notes' => ['nullable', 'string', 'max:5000'],
            'secondary_need_term_id' => [
                'nullable',
                'ulid',
                $activeEffectiveTerm,
            ],
            'secondary_need_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function needFieldMessages(): array
    {
        return [
            'primary_need.prohibited' => 'Need categories must use Ontology term ids, not free-text labels.',
            'secondary_need.prohibited' => 'Need categories must use Ontology term ids, not free-text labels.',
            'need.prohibited' => 'Need categories must use Ontology term ids, not free-text labels.',
            'diagnosis.prohibited' => 'Need categories must use Ontology term ids, not free-text labels.',
            'primary_need_term_id.exists' => 'The selected primary Need must be an active published Ontology term.',
            'secondary_need_term_id.exists' => 'The selected secondary Need must be an active published Ontology term.',
        ];
    }

    protected function prepareNeedFieldsForValidation(): void
    {
        $merge = [];

        foreach (['primary_need_notes', 'secondary_need_notes'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $trimmed = trim($this->input($field));
            $merge[$field] = $trimmed === '' ? null : $trimmed;
        }

        if ($this->exists('primary_need_term_id') && $this->input('primary_need_term_id') === null) {
            $merge['primary_need_notes'] = null;
            $merge['secondary_need_term_id'] = null;
            $merge['secondary_need_notes'] = null;
        }

        if ($this->exists('secondary_need_term_id') && $this->input('secondary_need_term_id') === null) {
            $merge['secondary_need_notes'] = null;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function validateNeedConsistency(Validator $validator, ?Pupil $pupil = null): void
    {
        if ($validator->errors()->hasAny([
            'primary_need_term_id',
            'secondary_need_term_id',
            'primary_need_notes',
            'secondary_need_notes',
        ])) {
            return;
        }

        $primaryTermId = $this->effectivePrimaryNeedTermId($pupil);
        $secondaryTermId = $this->effectiveSecondaryNeedTermId($pupil);
        $primaryNotes = $this->effectiveNeedNotes('primary_need_notes', $pupil?->primary_need_notes);
        $secondaryNotes = $this->effectiveNeedNotes('secondary_need_notes', $pupil?->secondary_need_notes);

        if ($secondaryTermId !== null && $primaryTermId === null) {
            $validator->errors()->add(
                'secondary_need_term_id',
                'Primary Need is required before a secondary Need can be set.'
            );
        }

        if ($primaryNotes !== null && $primaryTermId === null) {
            $validator->errors()->add(
                'primary_need_notes',
                'Need notes require a primary Need term.'
            );
        }

        if ($secondaryNotes !== null && $secondaryTermId === null) {
            $validator->errors()->add(
                'secondary_need_notes',
                'Need notes require a secondary Need term.'
            );
        }

        if ($primaryTermId !== null && $secondaryTermId !== null && $primaryTermId === $secondaryTermId) {
            $validator->errors()->add(
                'secondary_need_term_id',
                'Secondary Need must be different from the primary Need.'
            );
        }

        if ($primaryTermId !== null && $secondaryTermId !== null && $primaryTermId !== $secondaryTermId) {
            $primary = NeedTerm::query()->find($primaryTermId);
            $secondary = NeedTerm::query()->find($secondaryTermId);

            if (
                $primary !== null
                && $secondary !== null
                && $primary->ontology_version_id !== $secondary->ontology_version_id
            ) {
                $validator->errors()->add(
                    'secondary_need_term_id',
                    'Primary and secondary Need must use the same Ontology version.'
                );
            }
        }
    }

    protected function effectivePrimaryNeedTermId(?Pupil $pupil): ?string
    {
        if ($this->exists('primary_need_term_id')) {
            $value = $this->input('primary_need_term_id');

            return is_string($value) && $value !== '' ? $value : null;
        }

        return $pupil?->primary_need_term_id;
    }

    protected function effectiveSecondaryNeedTermId(?Pupil $pupil): ?string
    {
        if ($this->exists('secondary_need_term_id')) {
            $value = $this->input('secondary_need_term_id');

            return is_string($value) && $value !== '' ? $value : null;
        }

        return $pupil?->secondary_need_term_id;
    }

    protected function effectiveNeedNotes(string $field, ?string $existing): ?string
    {
        if ($this->exists($field)) {
            $value = $this->input($field);

            return is_string($value) && $value !== '' ? $value : null;
        }

        return $existing !== null && $existing !== '' ? $existing : null;
    }
}
