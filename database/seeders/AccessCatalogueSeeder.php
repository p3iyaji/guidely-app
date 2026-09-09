<?php

namespace Database\Seeders;

use App\Domain\Identity\AccessCatalogue;
use App\Domain\Identity\AccessPermission;
use App\Domain\Identity\AccessRole;
use App\Domain\Identity\Role;
use Illuminate\Database\Seeder;

class AccessCatalogueSeeder extends Seeder
{
    /**
     * Seed built-in Roles and Permissions (idempotent).
     */
    public function run(): void
    {
        $permissionsByKey = [];

        foreach (AccessCatalogue::systemPermissions() as $definition) {
            $permission = AccessPermission::query()->updateOrCreate(
                [
                    'tenant_id' => null,
                    'key' => $definition['key'],
                ],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'group' => $definition['group'],
                    'is_system' => true,
                ],
            );

            $permissionsByKey[$definition['key']] = $permission->id;
        }

        foreach (AccessCatalogue::systemRoles() as $definition) {
            $role = AccessRole::query()->updateOrCreate(
                [
                    'tenant_id' => null,
                    'key' => $definition['key'],
                ],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $enum = Role::from($definition['key']);
            $permissionIds = [];

            foreach (AccessCatalogue::defaultPermissionKeysFor($enum) as $key) {
                if (isset($permissionsByKey[$key])) {
                    $permissionIds[] = $permissionsByKey[$key];
                }
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}
