<?php

namespace App\Domain\Reporting;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\School;
use Database\Factories\TrustIndicatorSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly Trust Indicator snapshot for portfolio trends (story 7.3).
 *
 * Written by Domain\Reporting. Not mass-assignable from HTTP.
 */
#[Fillable([
    'tenant_id',
])]
class TrustIndicatorSnapshot extends Model
{
    /** @use HasFactory<TrustIndicatorSnapshotFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pupils_in_scope' => 'integer',
            'gaps' => 'integer',
            'gap_density' => 'float',
            'lateness_rate' => 'float',
            'overdue_open_cycles' => 'integer',
            'open_cycles' => 'integer',
        ];
    }

    protected static function newFactory(): TrustIndicatorSnapshotFactory
    {
        return TrustIndicatorSnapshotFactory::new();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    #[Scope]
    protected function trustTotals(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('school_id'));
    }
}
