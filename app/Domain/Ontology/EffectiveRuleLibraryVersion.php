<?php

namespace App\Domain\Ontology;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;

/**
 * Resolves the Rule Library version used for Rule lists and SRE citation pinning.
 *
 * Tenant pin (`current_rule_library_version_id`) wins only when that version exists and is
 * Published; otherwise the published Pilot Rule Library stub is used (created if missing).
 */
final class EffectiveRuleLibraryVersion
{
    public function id(?Tenant $tenant = null): ?string
    {
        return $this->resolve($tenant)?->id;
    }

    public function resolve(?Tenant $tenant = null): ?RuleLibraryVersion
    {
        $tenant ??= $this->currentTenantOrNull();

        if ($tenant !== null && is_string($tenant->current_rule_library_version_id) && $tenant->current_rule_library_version_id !== '') {
            $pinned = RuleLibraryVersion::query()
                ->published()
                ->whereKey($tenant->current_rule_library_version_id)
                ->first();

            if ($pinned !== null) {
                return $pinned;
            }
        }

        $pilot = RuleLibraryVersion::query()
            ->published()
            ->where('code', PilotRuleLibrary::VERSION_CODE)
            ->first();

        return $pilot ?? PilotRuleLibrary::ensurePublishedVersion();
    }

    private function currentTenantOrNull(): ?Tenant
    {
        if (! CurrentTenant::check()) {
            return null;
        }

        return CurrentTenant::require();
    }
}
