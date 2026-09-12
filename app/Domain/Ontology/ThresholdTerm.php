<?php

namespace App\Domain\Ontology;

use App\Domain\Ontology\Concerns\BelongsToOntologyVersion;
use Database\Factories\ThresholdTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'ontology_version_id',
    'code',
    'label',
    'is_active',
    'sort_order',
])]
class ThresholdTerm extends Model
{
    /** @use HasFactory<ThresholdTermFactory> */
    use BelongsToOntologyVersion, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): ThresholdTermFactory
    {
        return ThresholdTermFactory::new();
    }

    public function isInUse(): bool
    {
        return RelationshipMapping::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('from_domain', 'threshold')
                    ->where('from_term_id', $this->id);
            })
            ->orWhere(function (Builder $query): void {
                $query
                    ->where('to_domain', 'threshold')
                    ->where('to_term_id', $this->id);
            })
            ->exists();
    }

    /**
     * Resolve only terms on the Tenant's effective published Ontology version.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $versionId = app(EffectiveOntologyVersion::class)->id();

        if ($versionId === null) {
            return null;
        }

        return static::query()
            ->forVersion($versionId)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
