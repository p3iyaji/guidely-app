<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;

trait ValidatesTenantAssignableRoles
{
    /**
     * Role values a Tenant Admin may assign (excludes PlatformOperator;
     * Trust Roles only when trust_dashboard is enabled).
     *
     * @return list<string>
     */
    protected function tenantAssignableRoleValues(): array
    {
        $trustEnabled = app(FeatureFlagResolver::class)
            ->isEnabled(FeatureFlagKey::TrustDashboard);

        $values = [];

        foreach (Role::cases() as $role) {
            if (! $role->isTenantAssignable()) {
                continue;
            }

            if ($role->isTrustRole() && ! $trustEnabled) {
                continue;
            }

            $values[] = $role->value;
        }

        return $values;
    }
}
