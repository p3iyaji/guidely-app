<?php

namespace App\Domain\Ontology;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;

/**
 * Resolves the Ontology version used for term lists, validation, and import mapping.
 *
 * Tenant pin (`current_ontology_version_id`) wins only when that version exists and is
 * Published; otherwise the published Pilot Ontology stub is used.
 */
final class EffectiveOntologyVersion
{
    public function id(?Tenant $tenant = null): ?string
    {
        return $this->resolve($tenant)?->id;
    }

    public function resolve(?Tenant $tenant = null): ?OntologyVersion
    {
        $tenant ??= $this->currentTenantOrNull();

        if ($tenant !== null && is_string($tenant->current_ontology_version_id) && $tenant->current_ontology_version_id !== '') {
            $pinned = OntologyVersion::query()
                ->published()
                ->whereKey($tenant->current_ontology_version_id)
                ->first();

            if ($pinned !== null) {
                return $pinned;
            }
        }

        return OntologyVersion::query()
            ->published()
            ->where('code', PilotOntology::VERSION_CODE)
            ->first();
    }

    private function currentTenantOrNull(): ?Tenant
    {
        if (! CurrentTenant::check()) {
            return null;
        }

        return CurrentTenant::require();
    }
}
