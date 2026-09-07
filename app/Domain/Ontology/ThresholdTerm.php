<?php

namespace App\Domain\Ontology;

use App\Domain\Ontology\Concerns\BelongsToOntologyVersion;
use Database\Factories\ThresholdTermFactory;
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
}
