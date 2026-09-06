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

class StorePupilRequest extends FormRequest
{
    use ValidatesPupilNeedFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Pupil::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();
        $schoolId = $this->string('school_id')->toString();

        return array_merge([
            'school_id' => [
                'required',
                'ulid',
                Rule::exists('schools', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('is_active', true);
                }),
            ],
            'given_name' => ['required', 'string', 'max:255'],
            'family_name' => ['required', 'string', 'max:255'],
            'mis_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('pupils', 'mis_key')->where(
                    fn ($query) => $query->where('school_id', $schoolId)
                ),
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'year_group' => ['required', 'string', 'max:50'],
            'send_status' => ['required', 'string', Rule::in(SendStatus::values())],
            'notes' => ['nullable', 'string'],
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
        ], $this->needFieldMessages());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('school_id')) {
                return;
            }

            /** @var User|null $user */
            $user = $this->user();
            $schoolId = $this->string('school_id')->toString();

            if ($user === null || $schoolId === '') {
                return;
            }

            $school = School::query()->find($schoolId);

            if ($school === null || ! $user->canAccessSchool($school)) {
                $validator->errors()->add(
                    'school_id',
                    'You do not have access to the selected School.'
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            $this->validateNeedConsistency($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['given_name', 'family_name', 'year_group', 'mis_key', 'notes'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $trimmed = Str::of($this->input($field))->trim()->toString();
            $merge[$field] = in_array($field, ['mis_key', 'notes'], true) && $trimmed === ''
                ? null
                : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }

        $this->prepareNeedFieldsForValidation();
    }
}
