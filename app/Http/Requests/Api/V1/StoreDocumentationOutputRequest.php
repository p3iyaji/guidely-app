<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentationOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $pupil = $this->pupil();

        if ($pupil instanceof Pupil) {
            return $user->can('create', [DocumentationOutput::class, $pupil]);
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
        $nestedCycle = $this->route('reviewCycle');

        return [
            'pupil_id' => [
                Rule::requiredIf(! $nestedPupil instanceof Pupil),
                'ulid',
                Rule::exists('pupils', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'review_cycle_id' => [
                Rule::requiredIf(! $nestedCycle instanceof ReviewCycle),
                'ulid',
                Rule::exists('review_cycles', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'type' => ['required', 'string', Rule::in(DocumentationOutputType::values())],
            'purpose' => [
                Rule::requiredIf($this->requestsAdvancedType() && $this->advancedPacksEnabled()),
                'nullable',
                'string',
                'max:2000',
            ],
            'confirmer_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'disclaimer_acknowledged' => ['required', 'accepted'],
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
            'review_cycle_id.required' => 'A Review Cycle is required.',
            'review_cycle_id.exists' => 'The selected Review Cycle is invalid.',
            'type.required' => 'An output type is required.',
            'type.in' => 'Type must be Review summary, EHCP pack, Tribunal pack, or Inspection pack.',
            'purpose.required' => 'A purpose is required for tribunal and inspection packs.',
            'purpose.required_if' => 'A purpose is required for tribunal and inspection packs.',
            'confirmer_user_id.required' => 'A confirmer is required.',
            'disclaimer_acknowledged.required' => 'Disclaimer acknowledgement is required.',
            'disclaimer_acknowledged.accepted' => 'Disclaimer acknowledgement is required.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $confirmerId = $this->input('confirmer_user_id');

                if ($user !== null && $confirmerId !== null && (string) $confirmerId !== (string) $user->id) {
                    $validator->errors()->add(
                        'confirmer_user_id',
                        'The confirmer must be the signed-in User.',
                    );
                }

                if ($validator->errors()->hasAny(['pupil_id', 'review_cycle_id'])) {
                    return;
                }

                $pupil = $this->pupil();
                $cycle = $this->reviewCycle();

                if ($pupil instanceof Pupil && $cycle instanceof ReviewCycle && $cycle->pupil_id !== $pupil->id) {
                    $validator->errors()->add(
                        'review_cycle_id',
                        'The Review Cycle must belong to the selected Pupil.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('purpose') || ! is_string($this->input('purpose'))) {
            return;
        }

        $this->merge([
            'purpose' => trim($this->string('purpose')->toString()),
        ]);
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

    public function reviewCycle(): ?ReviewCycle
    {
        $nested = $this->route('reviewCycle');

        if ($nested instanceof ReviewCycle) {
            return $nested;
        }

        $cycleId = $this->string('review_cycle_id')->toString();

        if ($cycleId === '') {
            return null;
        }

        return ReviewCycle::query()->find($cycleId);
    }

    public function type(): DocumentationOutputType
    {
        return DocumentationOutputType::from($this->string('type')->toString());
    }

    public function purpose(): ?string
    {
        $purpose = trim($this->string('purpose')->toString());

        return $purpose === '' ? null : $purpose;
    }

    private function requestsAdvancedType(): bool
    {
        $type = DocumentationOutputType::tryFrom($this->string('type')->toString());

        return $type instanceof DocumentationOutputType && $type->isAdvanced();
    }

    private function advancedPacksEnabled(): bool
    {
        return app(FeatureFlagResolver::class)
            ->isEnabled(FeatureFlagKey::AdvancedDocumentationPacks);
    }
}
