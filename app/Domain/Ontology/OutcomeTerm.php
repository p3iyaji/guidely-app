<?php

namespace App\Domain\Ontology;

use App\Domain\Ontology\Concerns\BelongsToOntologyVersion;
use Database\Factories\OutcomeTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
class OutcomeTerm extends Model
{
    /** @use HasFactory<OutcomeTermFactory> */
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

    protected static function newFactory(): OutcomeTermFactory
    {
        return OutcomeTermFactory::new();
    }

    public function isInUse(): bool
    {
        return RelationshipMapping::query()
            ->where(fn ($query) => $query
                ->where('from_domain', 'outcome')
                ->where('from_term_id', $this->id))
            ->orWhere(fn ($query) => $query
                ->where('to_domain', 'outcome')
                ->where('to_term_id', $this->id))
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
