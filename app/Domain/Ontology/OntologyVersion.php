<?php

namespace App\Domain\Ontology;

use Database\Factories\OntologyVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'label',
    'status',
    'published_at',
])]
class OntologyVersion extends Model
{
    /** @use HasFactory<OntologyVersionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OntologyVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): OntologyVersionFactory
    {
        return OntologyVersionFactory::new();
    }

    public function needTerms(): HasMany
    {
        return $this->hasMany(NeedTerm::class);
    }

    public function settingTerms(): HasMany
    {
        return $this->hasMany(SettingTerm::class);
    }

    public function provisionTerms(): HasMany
    {
        return $this->hasMany(ProvisionTerm::class);
    }

    public function outcomeTerms(): HasMany
    {
        return $this->hasMany(OutcomeTerm::class);
    }

    public function thresholdTerms(): HasMany
    {
        return $this->hasMany(ThresholdTerm::class);
    }

    public function relationshipMappings(): HasMany
    {
        return $this->hasMany(RelationshipMapping::class);
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', OntologyVersionStatus::Published);
    }
}
