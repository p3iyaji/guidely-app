<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\CurrentTenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DestroyPupilAssignmentRequest extends FormRequest
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
        return [];
    }

    /**
     * Abort before authorization side-effects when the assignee is outside the current Tenant.
     * Route binding already scopes Users, but keep an explicit tenant guard.
     */
    protected function prepareForValidation(): void
    {
        /** @var User $assignee */
        $assignee = $this->route('user');
        $tenantId = CurrentTenant::id();

        if ($tenantId === null || $assignee->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
