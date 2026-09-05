<?php

namespace App\Domain\Tenancy;

/**
 * Resolves Tenant-scoped feature flags from DB configuration (AD-8).
 *
 * School-level overrides are deferred — resolution is Tenant-only for story 1.3.
 */
class FeatureFlagResolver
{
    public function isEnabled(FeatureFlagKey|string $key, ?Tenant $tenant = null): bool
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            return false;
        }

        $flagKey = $this->normalizeKey($key);

        if ($flagKey === null) {
            return false;
        }

        $flag = $tenant->featureFlags()
            ->where('key', $flagKey->value)
            ->first();

        return $flag?->enabled ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function mapFor(Tenant $tenant): array
    {
        $stored = $tenant->featureFlags()
            ->get()
            ->mapWithKeys(fn (TenantFeatureFlag $flag): array => [
                $flag->key->value => $flag->enabled,
            ]);

        $map = [];

        foreach (FeatureFlagKey::cases() as $key) {
            $map[$key->value] = (bool) ($stored[$key->value] ?? false);
        }

        return $map;
    }

    public function set(Tenant $tenant, FeatureFlagKey $key, bool $enabled): TenantFeatureFlag
    {
        /** @var TenantFeatureFlag $flag */
        $flag = $tenant->featureFlags()->updateOrCreate(
            ['key' => $key->value],
            ['enabled' => $enabled],
        );

        return $flag;
    }

    public function seedDefaults(Tenant $tenant): void
    {
        foreach (FeatureFlagKey::cases() as $key) {
            $tenant->featureFlags()->firstOrCreate(
                ['key' => $key->value],
                ['enabled' => false],
            );
        }
    }

    private function currentTenant(): ?Tenant
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }

    private function normalizeKey(FeatureFlagKey|string $key): ?FeatureFlagKey
    {
        if ($key instanceof FeatureFlagKey) {
            return $key;
        }

        return FeatureFlagKey::tryFrom($key);
    }
}
