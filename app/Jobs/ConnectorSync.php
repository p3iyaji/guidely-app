<?php

namespace App\Jobs;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorAdapterRegistry;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorPupilUpserter;
use App\Domain\Connectors\ConnectorType;
use App\Domain\Connectors\FilterConnectorPayload;
use App\Domain\Connectors\Import\ImportedInterventionEvidenceUpserter;
use App\Domain\Connectors\UnsupportedConnectorTypeException;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\School;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConnectorSync implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $pupils
     */
    public function __construct(
        public string $tenantId,
        public string $schoolId,
        public string $actorId,
        public array $pupils,
    ) {}

    public function handle(
        FilterConnectorPayload $filter,
        ConnectorPupilUpserter $pupilUpserter,
        ImportedInterventionEvidenceUpserter $evidenceUpserter,
        EnqueueSreReevaluation $enqueueSreReevaluation,
        ConnectorAdapterRegistry $adapters,
    ): void {
        $connector = $this->connector();

        if ($connector === null) {
            Log::warning('ConnectorSync skipped: Connector missing.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $connector->forceFill([
            'last_sync_started_at' => now(),
        ])->save();

        try {
            CurrentTenant::using($this->tenantId, function () use (
                $connector,
                $filter,
                $pupilUpserter,
                $evidenceUpserter,
                $enqueueSreReevaluation,
                $adapters,
            ): void {
                $this->sync(
                    $connector,
                    $filter,
                    $pupilUpserter,
                    $evidenceUpserter,
                    $enqueueSreReevaluation,
                    $adapters,
                );
            });
        } catch (Throwable $exception) {
            Log::error('ConnectorSync failed.', [
                'tenant_id' => $this->tenantId,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function sync(
        Connector $connector,
        FilterConnectorPayload $filter,
        ConnectorPupilUpserter $pupilUpserter,
        ImportedInterventionEvidenceUpserter $evidenceUpserter,
        EnqueueSreReevaluation $enqueueSreReevaluation,
        ConnectorAdapterRegistry $adapters,
    ): void {
        if ($connector->enabled !== true) {
            $this->recordFailure($connector, 'Connector is disabled.');
            Log::warning('ConnectorSync skipped: Connector disabled.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $school = School::withoutGlobalScope('tenant')->find($this->schoolId);

        if ($school === null || $school->tenant_id !== $this->tenantId) {
            $this->recordFailure($connector, 'The selected School is unavailable for this Connector.');
            Log::warning('ConnectorSync skipped: School is not in this Tenant.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $user = User::query()->find($this->actorId);

        if ($user === null || $user->tenant_id !== $this->tenantId || ! $user->can('sync', $connector)) {
            $this->recordFailure($connector, 'The requesting operator is unavailable or no longer permitted to sync.');
            Log::warning('ConnectorSync skipped: actor cannot sync this Connector.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $request = Request::create('/api/v1/connectors/sync', 'POST');
        $type = $connector->type;

        if (! $type instanceof ConnectorType) {
            throw new UnsupportedConnectorTypeException;
        }

        $pupils = $adapters->for($type)->pull($connector, $school, $this->pupils);
        $warningCount = 0;

        foreach ($pupils as $pupilPayload) {
            if (! is_array($pupilPayload)) {
                $warningCount++;

                continue;
            }

            try {
                if ($this->syncPupil(
                    $connector,
                    $user,
                    $request,
                    $pupilPayload,
                    $filter,
                    $pupilUpserter,
                    $evidenceUpserter,
                    $enqueueSreReevaluation,
                )) {
                    $warningCount++;
                }
            } catch (Throwable $exception) {
                $warningCount++;
                Log::error('ConnectorSync pupil failed.', [
                    'tenant_id' => $this->tenantId,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        $this->recordCompletion($connector, $warningCount);
    }

    /**
     * @param  array<string, mixed>  $pupilPayload
     */
    private function syncPupil(
        Connector $connector,
        User $user,
        Request $request,
        array $pupilPayload,
        FilterConnectorPayload $filter,
        ConnectorPupilUpserter $pupilUpserter,
        ImportedInterventionEvidenceUpserter $evidenceUpserter,
        EnqueueSreReevaluation $enqueueSreReevaluation,
    ): bool {
        $fieldPayload = Arr::only($pupilPayload, ConnectorField::values());
        $filtered = $filter->handle($connector, $this->schoolId, $fieldPayload);
        $result = $pupilUpserter->upsert($this->schoolId, $filtered, $user, $request);

        if (! isset($result['pupil'])) {
            return true;
        }

        $pupil = $result['pupil'];
        $evidenceChanged = false;
        $externalId = $pupilPayload['evidence_external_id'] ?? null;

        if (is_string($externalId)) {
            $externalId = trim($externalId);
        }

        if (is_string($externalId) && $externalId !== '') {
            $row = Arr::only($pupilPayload, ImportedInterventionEvidenceUpserter::EVIDENCE_FIELD_HEADERS);
            $row['evidence_external_id'] = $externalId;
            $headers = [];

            foreach ($row as $header => $value) {
                if ($value !== null && $value !== '') {
                    $headers[] = $header;
                }
            }

            $evidenceResult = $evidenceUpserter->process(
                $row,
                $headers,
                $pupil,
                $user,
                $request,
                EvidenceSource::Connector,
            );

            if (isset($evidenceResult['error'])) {
                Log::warning('ConnectorSync evidence skipped.', [
                    'tenant_id' => $this->tenantId,
                ]);

                return true;
            } else {
                $action = $evidenceResult['action'] ?? null;

                if ($action === 'evidence_created' || $action === 'evidence_updated') {
                    $evidenceChanged = true;
                }
            }
        }

        if ($evidenceChanged) {
            $enqueueSreReevaluation->handle($pupil, 'connector_sync', 'connector');
        }

        return false;
    }

    public function failed(?Throwable $exception): void
    {
        $connector = $this->connector();

        if ($connector === null) {
            return;
        }

        $this->recordFailure($connector, 'Connector sync failed. Review the application logs or try again.');
    }

    private function connector(): ?Connector
    {
        return Connector::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->first();
    }

    private function recordCompletion(Connector $connector, int $warningCount): void
    {
        $connector->forceFill([
            'last_sync_completed_at' => now(),
            'last_error' => $warningCount > 0
                ? "Completed with warnings: {$warningCount} pupil record(s) could not be processed."
                : null,
        ])->save();
    }

    private function recordFailure(Connector $connector, string $safeReason): void
    {
        $connector->forceFill([
            'last_sync_failed_at' => now(),
            'last_error' => $safeReason,
        ])->save();
    }
}
