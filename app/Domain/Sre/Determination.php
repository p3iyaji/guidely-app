<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\Rule;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DeterminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRE Determination — citation-pinned evaluation for one FR-26 dimension.
 *
 * Citation FKs (`ontology_version_id`, `rule_library_version_id`) and evaluator
 * write fields are intentionally not fillable; Domain\Sre writes via forceFill.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class Determination extends Model
{
    /** @use HasFactory<DeterminationFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_current' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => SreDimension::class,
            'result' => DeterminationResult::class,
            'reasoning_pathway' => 'array',
            'is_current' => 'boolean',
            'evaluated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DeterminationFactory
    {
        return DeterminationFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function ontologyVersion(): BelongsTo
    {
        return $this->belongsTo(OntologyVersion::class);
    }

    public function ruleLibraryVersion(): BelongsTo
    {
        return $this->belongsTo(RuleLibraryVersion::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_current'), true);
    }

    #[Scope]
    protected function forPupil(Builder $query, string $pupilId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('pupil_id'), $pupilId);
    }

    #[Scope]
    protected function forDimension(Builder $query, SreDimension $dimension): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('dimension'), $dimension->value);
    }
}
