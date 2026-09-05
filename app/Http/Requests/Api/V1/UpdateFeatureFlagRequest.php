<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->user()?->tenant;

        if ($tenant === null) {
            return false;
        }

        return $this->user()->can('update', $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', Rule::in(FeatureFlagKey::values())],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
