<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Identity\AccessPermission;
use App\Domain\Identity\AccessRole;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesAccessCatalogueFields
{
    protected function prepareAccessCatalogueFields(): void
    {
        if ($this->exists('key')) {
            $this->merge([
                'key' => strtolower(trim((string) $this->input('key'))),
            ]);
        }

        if ($this->exists('label')) {
            $this->merge([
                'label' => trim((string) $this->input('label')),
            ]);
        }

        foreach (['description', 'group'] as $field) {
            if ($this->exists($field)) {
                $trimmed = trim((string) $this->input($field));

                $this->merge([
                    $field => $trimmed === '' ? null : $trimmed,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function accessRoleRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $tenantId = CurrentTenant::id();
        $ignore = $this->route('accessRole');
        $ignoreId = $ignore instanceof AccessRole ? $ignore->id : null;

        $unique = Rule::unique('access_roles', 'key')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId ?? ''));

        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        if ($ignore instanceof AccessRole && $ignore->is_system) {
            return [
                'key' => ['sometimes', 'string', Rule::in([$ignore->key])],
                'label' => [$presence, 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:500'],
                'permission_ids' => ['sometimes', 'array'],
                'permission_ids.*' => [
                    'ulid',
                    Rule::exists('access_permissions', 'id')->where(
                        function ($query) use ($tenantId): void {
                            $query->where(function ($inner) use ($tenantId): void {
                                $inner->whereNull('tenant_id');

                                if ($tenantId !== null) {
                                    $inner->orWhere('tenant_id', $tenantId);
                                }
                            });
                        }
                    ),
                ],
            ];
        }

        return [
            'key' => [
                $presence,
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(Role::values()),
                $unique,
            ],
            'label' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => [
                'ulid',
                Rule::exists('access_permissions', 'id')->where(
                    function ($query) use ($tenantId): void {
                        $query->where(function ($inner) use ($tenantId): void {
                            $inner->whereNull('tenant_id');

                            if ($tenantId !== null) {
                                $inner->orWhere('tenant_id', $tenantId);
                            }
                        });
                    }
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function accessPermissionRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $tenantId = CurrentTenant::id();
        $ignore = $this->route('accessPermission');
        $ignoreId = $ignore instanceof AccessPermission ? $ignore->id : null;

        $unique = Rule::unique('access_permissions', 'key')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId ?? ''));

        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        $systemKeys = AccessPermission::query()
            ->whereNull('tenant_id')
            ->pluck('key')
            ->all();

        if ($ignore instanceof AccessPermission && $ignore->is_system) {
            return [
                'key' => ['sometimes', 'string', Rule::in([$ignore->key])],
                'label' => [$presence, 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:500'],
                'group' => ['sometimes', 'nullable', 'string', 'max:64'],
            ];
        }

        return [
            'key' => [
                $presence,
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn($systemKeys),
                $unique,
            ],
            'label' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'group' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function accessCatalogueMessages(): array
    {
        return [
            'key.regex' => 'The key may contain only lowercase letters, numbers, and underscores, and must start with a letter.',
            'key.not_in' => 'This key is reserved for a built-in record.',
            'key.in' => 'Built-in keys cannot be changed.',
            'key.unique' => 'A record with this key already exists in this Tenant.',
            'permission_ids.*.exists' => 'One or more Permissions are invalid for this Tenant.',
        ];
    }

    /**
     * @return list<\Closure>
     */
    protected function accessCatalogueAfterHooks(): array
    {
        return [
            function (Validator $validator): void {
                if (CurrentTenant::id() !== null) {
                    return;
                }

                $validator->errors()->add(
                    'key',
                    'A Tenant context is required to manage custom Roles and Permissions.',
                );
            },
        ];
    }
}
