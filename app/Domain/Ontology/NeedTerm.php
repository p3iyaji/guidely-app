<?php

namespace App\Domain\Ontology;

use Database\Factories\NeedTermFactory;
use Database\Seeders\NeedOntologySeeder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ontology_version_id',
    'code',
    'label',
    'is_active',
    'sort_order',
])]
class NeedTerm extends Model
{
    /** @use HasFactory<NeedTermFactory> */
    use HasFactory, HasUlids;

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

    protected static function newFactory(): NeedTermFactory
    {
        return NeedTermFactory::new();
    }

    public function ontologyVersion(): BelongsTo
    {
        return $this->belongsTo(OntologyVersion::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function fromPublishedStub(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('ontologyVersion', function (Builder $versionQuery): void {
                $versionQuery->published()
                    ->where('code', NeedOntologySeeder::STUB_VERSION_CODE);
            });
    }
}
