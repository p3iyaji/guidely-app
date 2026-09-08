<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $existing = Connector::query()->first();

        if ($existing === null) {
            return $user->can('create', Connector::class);
        }

        return $user->can('update', $existing);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::enum(ConnectorType::class)],
            'enabled' => ['sometimes', 'boolean'],
            'secret' => ['sometimes', 'nullable', 'string'],
            'field_shares' => ['sometimes', 'array'],
            'field_shares.*.school_id' => [
                'required',
                'string',
                'distinct',
                Rule::exists('schools', 'id')->where(function ($query): void {
                    $query->where('tenant_id', $this->user()?->tenant_id);
                }),
            ],
            'field_shares.*.fields' => ['required', 'array'],
            'field_shares.*.fields.*' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $shares = $this->input('field_shares');

                if (! is_array($shares)) {
                    return;
                }

                $allowed = ConnectorField::values();

                foreach ($shares as $index => $share) {
                    if (! is_array($share) || ! isset($share['fields']) || ! is_array($share['fields'])) {
                        continue;
                    }

                    foreach (array_keys($share['fields']) as $key) {
                        if (! is_string($key) || ! in_array($key, $allowed, true)) {
                            $validator->errors()->add(
                                "field_shares.{$index}.fields.{$key}",
                                'This field is not in the Connector sharing allowlist.',
                            );
                        }
                    }
                }
            },
        ];
    }

    public function type(): ConnectorType
    {
        $value = $this->validated('type');

        if (is_string($value)) {
            return ConnectorType::from($value);
        }

        return ConnectorType::PilotStub;
    }

    public function enabled(bool $fallback): bool
    {
        if (! $this->exists('enabled')) {
            return $fallback;
        }

        return $this->boolean('enabled');
    }

    public function hasSecretInput(): bool
    {
        return $this->secret() !== '';
    }

    public function secret(): string
    {
        $secret = $this->input('secret');

        return is_string($secret) ? trim($secret) : '';
    }

    /**
     * @return list<array{school_id: string, fields: array<string, bool>}>
     */
    public function normalizedFieldShares(): array
    {
        /** @var list<array{school_id: string, fields?: array<string, mixed>}> $shares */
        $shares = $this->validated('field_shares') ?? [];
        $normalized = [];

        foreach ($shares as $share) {
            $fields = ConnectorField::defaultMap();

            foreach ($share['fields'] ?? [] as $key => $value) {
                if (is_string($key) && array_key_exists($key, $fields)) {
                    $fields[$key] = ConnectorField::isShared($value);
                }
            }

            $normalized[] = [
                'school_id' => $share['school_id'],
                'fields' => $fields,
            ];
        }

        return $normalized;
    }
}
