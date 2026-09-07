<?php

namespace App\Domain\Ontology\Concerns;

use App\Domain\Ontology\EffectiveRuleLibraryVersion;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared Rule Library version relation and scopes for Rule models.
 *
 * @mixin Model
 */
trait BelongsToRuleLibraryVersion
{
    public function ruleLibraryVersion(): BelongsTo
    {
        return $this->belongsTo(RuleLibraryVersion::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_active'), true);
    }

    #[Scope]
    protected function forVersion(Builder $query, string $ruleLibraryVersionId): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('rule_library_version_id'),
            $ruleLibraryVersionId,
        );
    }

    /**
     * Active Rules on the Tenant's effective Rule Library version (pin or Pilot fallback).
     */
    #[Scope]
    protected function forTenant(Builder $query, ?Tenant $tenant = null): Builder
    {
        $versionId = app(EffectiveRuleLibraryVersion::class)->id($tenant);

        if ($versionId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->forVersion($versionId)->active();
    }
}
