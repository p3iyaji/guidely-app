<?php

namespace App\Domain\Ontology;

use App\Domain\Ontology\Concerns\BelongsToRuleLibraryVersion;
use Database\Factories\RuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'rule_library_version_id',
    'code',
    'label',
    'dimension',
    'category',
    'condition',
    'evaluation',
    'outcome',
    'is_active',
    'sort_order',
])]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use BelongsToRuleLibraryVersion, HasFactory, HasUlids;

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
            'dimension' => SreDimension::class,
            'category' => RuleCategory::class,
            'condition' => 'array',
            'evaluation' => 'array',
            'outcome' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): RuleFactory
    {
        return RuleFactory::new();
    }
}
