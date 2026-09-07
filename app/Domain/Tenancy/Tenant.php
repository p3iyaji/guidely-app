<?php

namespace App\Domain\Tenancy;

use App\Domain\Ontology\EffectiveOntologyVersion;
use App\Domain\Ontology\OntologyVersion;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'type',
    'cohort_enabled',
    'cohort_label',
    'sso_enabled',
    'sso_provider',
    'sso_entity_id',
    'sso_client_id',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cohort_enabled' => false,
        'sso_enabled' => false,
    ];

    protected static function booted(): void
    {
        static::created(function (Tenant $tenant): void {
            app(FeatureFlagResolver::class)->seedDefaults($tenant);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TenantType::class,
            'cohort_enabled' => 'boolean',
            'sso_enabled' => 'boolean',
        ];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(TenantFeatureFlag::class);
    }

    public function currentOntologyVersion(): BelongsTo
    {
        return $this->belongsTo(OntologyVersion::class, 'current_ontology_version_id');
    }

    /**
     * Ontology version used for term lists / validation (pin or Pilot published fallback).
     */
    public function effectiveOntologyVersion(): ?OntologyVersion
    {
        return app(EffectiveOntologyVersion::class)->resolve($this);
    }

    public function isTrust(): bool
    {
        return $this->type === TenantType::Trust;
    }
}
