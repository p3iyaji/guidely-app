<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reporting\DashboardSummary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowDashboardSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', DashboardSummary::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'window' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
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
}
