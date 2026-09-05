<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenancy\School;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var School $school */
        $school = $this->route('school');

        return $this->user()?->can('update', $school) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
