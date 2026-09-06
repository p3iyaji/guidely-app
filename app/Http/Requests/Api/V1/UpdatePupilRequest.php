<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\School;
use App\Http\Requests\Api\V1\Concerns\ValidatesPupilNeedFields;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePupilRequest extends FormRequest
{
    use ValidatesPupilNeedFields;

    public function authorize(): bool
    {
        /** @var Pupil $pupil */
        $pupil = $this->route('pupil');

        return $this->user()?->can('update', $pupil) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Pupil $pupil */
        $pupil = $this->route('pupil');
        $tenantId = CurrentTenant::id();
        $schoolId = $this->input('school_id', $pupil->school_id);

        return array_merge([
            'school_id' => [
                'sometimes',
                'ulid',
                Rule::exists('schools', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('is_active', true);
                }),
            ],
            'given_name' => ['sometimes', 'filled', 'string', 'max:255'],
            'family_name' => ['sometimes', 'filled', 'string', 'max:255'],
            'mis_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('pupils', 'mis_key')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($pupil->id),
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'year_group' => ['sometimes', 'filled', 'string', 'max:50'],
            'send_status' => ['sometimes', 'string', Rule::in(SendStatus::values())],
        ], $this->needFieldRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'mis_key.unique' => 'A Pupil with this MIS key already exists in this School.',
            'school_id.exists' => 'The selected School must be an active School in your organisation.',
            'send_status.in' => 'SEND status must be SEN Support, EHCP, or neither.',
            'given_name.filled' => 'Given name cannot be blank.',
            'family_name.filled' => 'Family name cannot be blank.',
            'year_group.filled' => 'Year group cannot be blank.',
        ], $this->needFieldMessages());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Pupil $pupil */
            $pupil = $this->route('pupil');

            if ($this->filled('school_id') && ! $validator->errors()->has('school_id')) {
                /** @var User|null $user */
                $user = $this->user();
                $schoolId = $this->string('school_id')->toString();

                if ($user !== null && $schoolId !== '') {
                    $school = School::query()->find($schoolId);

                    if ($school === null || ! $user->canAccessSchool($school)) {
                        $validator->errors()->add(
                            'school_id',
                            'You do not have access to the selected School.'
                        );
                    }
                }
            }

            if ($validator->errors()->has('mis_key') || $validator->errors()->has('school_id')) {
                return;
            }

            $effectiveSchoolId = (string) $this->input('school_id', $pupil->school_id);
            $effectiveMisKey = $this->exists('mis_key')
                ? $this->input('mis_key')
                : $pupil->mis_key;

            if (! is_string($effectiveMisKey) || $effectiveMisKey === '') {
                return;
            }

            $conflict = Pupil::query()
                ->where('school_id', $effectiveSchoolId)
                ->where('mis_key', $effectiveMisKey)
                ->whereKeyNot($pupil->id)
                ->exists();

            if ($conflict) {
                $validator->errors()->add(
                    'mis_key',
                    'A Pupil with this MIS key already exists in this School.'
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            /** @var Pupil $pupil */
            $pupil = $this->route('pupil');
            $this->validateNeedConsistency($validator, $pupil);
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['given_name', 'family_name', 'year_group', 'mis_key'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $trimmed = Str::of($this->input($field))->trim()->toString();
            $merge[$field] = $field === 'mis_key' && $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }

        $this->prepareNeedFieldsForValidation();
    }
}
