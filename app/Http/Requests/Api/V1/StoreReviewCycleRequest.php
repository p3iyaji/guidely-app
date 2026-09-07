<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Reviews\ReviewCycleType;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $pupil = $this->pupil();

        if ($pupil instanceof Pupil) {
            return $user->can('create', [ReviewCycle::class, $pupil]);
        }

        return $user->isActiveTenantStaff() && $user->role === Role::Senco;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();
        $nestedPupil = $this->route('pupil');

        return [
            'pupil_id' => [
                Rule::requiredIf(! $nestedPupil instanceof Pupil),
                'ulid',
                Rule::exists('pupils', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'type' => ['required', 'string', Rule::in(ReviewCycleType::values())],
            'due_on' => ['required', 'date', 'date_format:Y-m-d'],
            'ehcp_linked' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pupil_id.required' => 'A Pupil is required.',
            'pupil_id.exists' => 'The selected Pupil is invalid.',
            'type.required' => 'A Review Cycle type is required.',
            'type.in' => 'Type must be Annual Review, Interim, or Other.',
            'due_on.required' => 'A due date is required.',
            'due_on.date' => 'The due date must be a valid date.',
            'due_on.date_format' => 'The due date must be a valid date.',
        ];
    }

    public function pupil(): ?Pupil
    {
        $nested = $this->route('pupil');

        if ($nested instanceof Pupil) {
            return $nested;
        }

        $pupilId = $this->string('pupil_id')->toString();

        if ($pupilId === '') {
            return null;
        }

        return Pupil::query()->find($pupilId);
    }

    public function type(): ReviewCycleType
    {
        return ReviewCycleType::from($this->string('type')->toString());
    }

    public function dueOn(): string
    {
        return $this->date('due_on')?->toDateString() ?? $this->string('due_on')->toString();
    }

    public function ehcpLinked(): bool
    {
        return $this->boolean('ehcp_linked');
    }
}
