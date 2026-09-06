<?php

namespace App\Domain\Ontology;

use Database\Factories\ProvisionTermFactory;
use Database\Seeders\ProvisionOntologySeeder;
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
class ProvisionTerm extends Model
{
    /** @use HasFactory<ProvisionTermFactory> */
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

    protected static function newFactory(): ProvisionTermFactory
    {
        return ProvisionTermFactory::new();
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
                    ->where('code', ProvisionOntologySeeder::STUB_VERSION_CODE);
            });
    }
}
