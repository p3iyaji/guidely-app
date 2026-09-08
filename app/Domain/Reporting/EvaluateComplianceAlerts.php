<?php

namespace App\Domain\Reporting;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Illuminate\Http\Request;

class EvaluateComplianceAlerts
{
    public function __construct(
        private FeatureFlagResolver $flags,
        private BuildTrustIndicators $build,
        private AuditWriter $audit,
    ) {}

    /**
     * Compare live 7.1 Indicators to configured thresholds and open or resolve alerts.
     *
     * @return array{created: int, resolved: int, skipped: bool}
     */
    public function handle(Tenant $tenant, Request $request): array
    {
        if (! $this->flags->isEnabled(FeatureFlagKey::ComplianceAlerts, $tenant)) {
            return ['created' => 0, 'resolved' => 0, 'skipped' => true];
        }

        return CurrentTenant::using($tenant->id, function () use ($request): array {
            $this->ensureDefaultThresholds();
            $indicator = $this->build->handle(includeSchools: true, includeBenchmark: false);
            $created = 0;
            $resolved = 0;

            foreach (ComplianceAlertMetric::cases() as $metric) {
                $outcome = $this->evaluateScope(
                    ComplianceAlertScope::Trust,
                    null,
                    $metric,
                    $this->observed($indicator, $metric),
                    $request,
                );
                $created += $outcome['created'];
                $resolved += $outcome['resolved'];
            }

            foreach ($indicator->schools ?? [] as $school) {
                $schoolId = $school['school_id'];

                foreach (ComplianceAlertMetric::cases() as $metric) {
                    $outcome = $this->evaluateScope(
                        ComplianceAlertScope::School,
                        $schoolId,
                        $metric,
                        $this->observedFromRow($school, $metric),
                        $request,
                    );
                    $created += $outcome['created'];
                    $resolved += $outcome['resolved'];
                }
            }

            return ['created' => $created, 'resolved' => $resolved, 'skipped' => false];
        });
    }

    /**
     * @return array{created: int, resolved: int}
     */
    private function evaluateScope(
        ComplianceAlertScope $scope,
        ?string $schoolId,
        ComplianceAlertMetric $metric,
        float $observed,
        Request $request,
    ): array {
        $threshold = $this->thresholdFor($metric, $schoolId);
        $open = $this->openAlert($scope, $schoolId, $metric);

        if ($observed >= $threshold) {
            if ($open instanceof ComplianceAlert) {
                $open->forceFill([
                    'threshold_value' => $threshold,
                    'observed_value' => $observed,
                ])->save();

                return ['created' => 0, 'resolved' => 0];
            }

            $alert = new ComplianceAlert;
            $alert->forceFill([
                'scope' => $scope,
                'school_id' => $schoolId,
                'metric' => $metric,
                'threshold_value' => $threshold,
                'observed_value' => $observed,
                'is_open' => true,
                'resolved_at' => null,
            ])->save();

            $this->audit->record(
                AuditEventType::ComplianceAlertCreated,
                $request,
                tenantId: $alert->tenant_id,
                resourceType: 'compliance_alert',
                resourceId: $alert->id,
                metadata: [
                    'scope' => $scope->value,
                    'metric' => $metric->value,
                    'threshold_value' => $threshold,
                    'observed_value' => $observed,
                ],
            );

            return ['created' => 1, 'resolved' => 0];
        }

        if (! $open instanceof ComplianceAlert) {
            return ['created' => 0, 'resolved' => 0];
        }

        $open->forceFill([
            'threshold_value' => $threshold,
            'observed_value' => $observed,
            'is_open' => false,
            'resolved_at' => now(),
        ])->save();

        $this->audit->record(
            AuditEventType::ComplianceAlertResolved,
            $request,
            tenantId: $open->tenant_id,
            resourceType: 'compliance_alert',
            resourceId: $open->id,
            metadata: [
                'scope' => $scope->value,
                'metric' => $metric->value,
                'threshold_value' => $threshold,
                'observed_value' => $observed,
            ],
        );

        return ['created' => 0, 'resolved' => 1];
    }

    private function openAlert(
        ComplianceAlertScope $scope,
        ?string $schoolId,
        ComplianceAlertMetric $metric,
    ): ?ComplianceAlert {
        $query = ComplianceAlert::query()
            ->open()
            ->where('scope', $scope)
            ->where('metric', $metric);

        if ($schoolId === null) {
            $query->whereNull('school_id');
        } else {
            $query->where('school_id', $schoolId);
        }

        return $query->first();
    }

    private function thresholdFor(ComplianceAlertMetric $metric, ?string $schoolId): float
    {
        $key = ComplianceAlertThreshold::snapshotKey($metric, $schoolId);
        $row = ComplianceAlertThreshold::query()
            ->where('snapshot_key', $key)
            ->first();

        return $row instanceof ComplianceAlertThreshold
            ? $row->threshold
            : ComplianceAlertThreshold::DEFAULT_THRESHOLD;
    }

    private function ensureDefaultThresholds(): void
    {
        foreach (ComplianceAlertMetric::cases() as $metric) {
            $this->ensureThreshold($metric, null);
        }

        $schools = School::query()
            ->active()
            ->orderBy('id')
            ->get(['id']);

        foreach ($schools as $school) {
            foreach (ComplianceAlertMetric::cases() as $metric) {
                $this->ensureThreshold($metric, $school->id);
            }
        }
    }

    private function ensureThreshold(ComplianceAlertMetric $metric, ?string $schoolId): void
    {
        $key = ComplianceAlertThreshold::snapshotKey($metric, $schoolId);
        $existing = ComplianceAlertThreshold::query()
            ->where('snapshot_key', $key)
            ->first();

        if ($existing instanceof ComplianceAlertThreshold) {
            return;
        }

        $threshold = new ComplianceAlertThreshold;
        $threshold->forceFill([
            'school_id' => $schoolId,
            'metric' => $metric,
            'threshold' => ComplianceAlertThreshold::DEFAULT_THRESHOLD,
            'snapshot_key' => $key,
        ])->save();
    }

    private function observed(TrustIndicator $indicator, ComplianceAlertMetric $metric): float
    {
        return match ($metric) {
            ComplianceAlertMetric::LatenessRate => $indicator->latenessRate,
            ComplianceAlertMetric::GapDensity => $indicator->gapDensity,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function observedFromRow(array $row, ComplianceAlertMetric $metric): float
    {
        return match ($metric) {
            ComplianceAlertMetric::LatenessRate => (float) ($row['lateness_rate'] ?? 0),
            ComplianceAlertMetric::GapDensity => (float) ($row['gap_density'] ?? 0),
        };
    }
}
