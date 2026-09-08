<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Reporting\SchoolReport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowSchoolReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->role === Role::TrustSendLead) {
            return $user->can('view', SchoolReport::class) && $this->filled('school_id');
        }

        return $user->can('view', SchoolReport::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'window' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
            'school_id' => [
                'sometimes',
                'string',
                Rule::exists('schools', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('is_active', true);
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'window.in' => 'Window must be 7, 30, or 90 days.',
        ];
    }

    public function windowDays(): int
    {
        $window = $this->integer('window');

        return in_array($window, [7, 30, 90], true) ? $window : 30;
    }

    public function schoolId(): ?string
    {
        $schoolId = $this->input('school_id');

        return is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('school_id')) {
            abort(404);
        }

        parent::failedValidation($validator);
    }
}
