<?php

namespace Database\Factories;

use App\Domain\Reporting\ComplianceAlertMetric;
use App\Domain\Reporting\ComplianceAlertThreshold;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceAlertThreshold>
 */
class ComplianceAlertThresholdFactory extends Factory
{
    protected $model = ComplianceAlertThreshold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metric = ComplianceAlertMetric::GapDensity;

        return [
            'tenant_id' => Tenant::factory(),
            'school_id' => null,
            'metric' => $metric,
            'threshold' => ComplianceAlertThreshold::DEFAULT_THRESHOLD,
            'snapshot_key' => ComplianceAlertThreshold::snapshotKey($metric, null),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forSchool(School $school): static
    {
        return $this->state(function (array $attributes) use ($school): array {
            $metric = $attributes['metric'] ?? ComplianceAlertMetric::GapDensity;
            if (! $metric instanceof ComplianceAlertMetric) {
                $metric = ComplianceAlertMetric::from((string) $metric);
            }

            return [
                'tenant_id' => $school->tenant_id,
                'school_id' => $school->id,
                'snapshot_key' => ComplianceAlertThreshold::snapshotKey($metric, $school->id),
            ];
        });
    }

    public function forMetric(ComplianceAlertMetric $metric): static
    {
        return $this->state(function (array $attributes) use ($metric): array {
            $schoolId = $attributes['school_id'] ?? null;

            return [
                'metric' => $metric,
                'snapshot_key' => ComplianceAlertThreshold::snapshotKey(
                    $metric,
                    is_string($schoolId) ? $schoolId : null,
                ),
            ];
        });
    }
}
