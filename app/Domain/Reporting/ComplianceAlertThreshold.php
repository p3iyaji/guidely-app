<?php

namespace App\Domain\Reporting;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\School;
use Database\Factories\ComplianceAlertThresholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configured Indicator threshold for compliance alerts.
 */
#[Fillable([
    'tenant_id',
])]
class ComplianceAlertThreshold extends Model
{
    /** @use HasFactory<ComplianceAlertThresholdFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const DEFAULT_THRESHOLD = 0.5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric' => ComplianceAlertMetric::class,
            'threshold' => 'float',
        ];
    }

    protected static function newFactory(): ComplianceAlertThresholdFactory
    {
        return ComplianceAlertThresholdFactory::new();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function snapshotKey(ComplianceAlertMetric $metric, ?string $schoolId): string
    {
        return $metric->value.':'.($schoolId ?? 'trust');
    }
}
