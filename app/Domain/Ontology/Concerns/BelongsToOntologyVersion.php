<?php

namespace App\Domain\Ontology\Concerns;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\OntologyVersion;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared Ontology version relation and scopes for taxonomy term models.
 *
 * @mixin Model
 */
trait BelongsToOntologyVersion
{
    public function ontologyVersion(): BelongsTo
    {
        return $this->belongsTo(OntologyVersion::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_active'), true);
    }

    #[Scope]
    protected function forVersion(Builder $query, string $ontologyVersionId): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('ontology_version_id'),
            $ontologyVersionId,
        );
    }

    /**
     * Active terms on the Tenant's effective Ontology version (pin or Pilot fallback).
     */
    #[Scope]
    protected function forTenant(Builder $query, ?Tenant $tenant = null): Builder
    {
        $versionId = app(EffectiveOntologyVersion::class)->id($tenant);

        if ($versionId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->forVersion($versionId)->active();
    }
}
