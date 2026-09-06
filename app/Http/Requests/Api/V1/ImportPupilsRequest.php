<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportPupilsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import-pupils') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:csv,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required.',
            'file.file' => 'The upload must be a file.',
            'file.mimes' => 'The upload must be a CSV file.',
            'file.max' => 'The CSV file must not exceed 5 MB.',
        ];
    }
}
