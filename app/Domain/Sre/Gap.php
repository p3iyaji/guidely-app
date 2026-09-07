<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\GapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived Gap from a current SRE Determination.
 *
 * Domain\Sre is the sole writer (forceFill). Not mass-assignable from capture APIs.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class Gap extends Model
{
    /** @use HasFactory<GapFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_open' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => SreDimension::class,
            'result' => DeterminationResult::class,
            'is_open' => 'boolean',
        ];
    }

    protected static function newFactory(): GapFactory
    {
        return GapFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function determination(): BelongsTo
    {
        return $this->belongsTo(Determination::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_open'), true);
    }

    #[Scope]
    protected function forPupil(Builder $query, string $pupilId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('pupil_id'), $pupilId);
    }
}
