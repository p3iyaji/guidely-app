<?php

namespace App\Domain\Ontology;

use App\Domain\Ontology\Concerns\BelongsToOntologyVersion;
use Database\Factories\RelationshipMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'ontology_version_id',
    'code',
    'label',
    'relationship_type',
    'from_domain',
    'from_term_id',
    'to_domain',
    'to_term_id',
    'is_active',
    'sort_order',
])]
class RelationshipMapping extends Model
{
    /** @use HasFactory<RelationshipMappingFactory> */
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

    protected static function newFactory(): RelationshipMappingFactory
    {
        return RelationshipMappingFactory::new();
    }
}
