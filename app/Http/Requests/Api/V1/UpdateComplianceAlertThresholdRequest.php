<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reporting\ComplianceAlert;
use App\Domain\Reporting\ComplianceAlertMetric;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceAlertThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateThresholds', ComplianceAlert::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();

        return [
            'school_id' => [
                'nullable',
                'string',
                Rule::exists('schools', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'metric' => ['required', 'string', Rule::enum(ComplianceAlertMetric::class)],
            'threshold' => ['required', 'numeric', 'gte:0', 'lte:1'],
        ];
    }
}
