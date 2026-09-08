<?php

namespace App\Domain\Reporting;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\School;
use Database\Factories\ComplianceAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Indicator alert when a live Trust/School metric meets a configured threshold.
 */
#[Fillable([
    'tenant_id',
])]
class ComplianceAlert extends Model
{
    /** @use HasFactory<ComplianceAlertFactory> */
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
            'scope' => ComplianceAlertScope::class,
            'metric' => ComplianceAlertMetric::class,
            'threshold_value' => 'float',
            'observed_value' => 'float',
            'is_open' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ComplianceAlertFactory
    {
        return ComplianceAlertFactory::new();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_open'), true);
    }
}
