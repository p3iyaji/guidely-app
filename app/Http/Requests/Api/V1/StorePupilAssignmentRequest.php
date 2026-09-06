<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\School;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePupilAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Pupil $pupil */
        $pupil = $this->route('pupil');

        return $this->user()?->can('assign', $pupil) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->whereNull('deactivated_at')
                        ->whereIn('role', [
                            Role::Teacher->value,
                            Role::SupportStaff->value,
                        ]);
                }),
            ],
            'class_label' => ['nullable', 'string', 'max:255'],
            'cohort_label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected User must be an active Teacher or Support Staff in your organisation.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('user_id')) {
                return;
            }

            /** @var Pupil $pupil */
            $pupil = $this->route('pupil');
            $assigneeId = $this->integer('user_id');

            if ($assigneeId === 0) {
                return;
            }

            $assignee = User::query()->find($assigneeId);

            if ($assignee === null) {
                return;
            }

            $school = School::query()->find($pupil->school_id);

            if ($school === null || ! $assignee->canAccessSchool($school)) {
                $validator->errors()->add(
                    'user_id',
                    'The selected User must have access to the Pupil\'s School.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['class_label', 'cohort_label'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $trimmed = Str::of($this->input($field))->trim()->toString();
            $merge[$field] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
