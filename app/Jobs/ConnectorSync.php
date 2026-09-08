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
        try {
            CurrentTenant::using($this->tenantId, function () use (
                $filter,
                $pupilUpserter,
                $evidenceUpserter,
                $enqueueSreReevaluation,
                $adapters,
            ): void {
                $this->sync(
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
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function sync(
        FilterConnectorPayload $filter,
        ConnectorPupilUpserter $pupilUpserter,
        ImportedInterventionEvidenceUpserter $evidenceUpserter,
        EnqueueSreReevaluation $enqueueSreReevaluation,
        ConnectorAdapterRegistry $adapters,
    ): void {
        $connector = Connector::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->first();

        if ($connector === null || $connector->enabled !== true) {
            Log::warning('ConnectorSync skipped: Connector missing or disabled.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $school = School::withoutGlobalScope('tenant')->find($this->schoolId);

        if ($school === null || $school->tenant_id !== $this->tenantId) {
            Log::warning('ConnectorSync skipped: School is not in this Tenant.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $user = User::query()->find($this->actorId);

        if ($user === null || $user->tenant_id !== $this->tenantId || ! $user->can('sync', $connector)) {
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

        foreach ($pupils as $pupilPayload) {
            if (! is_array($pupilPayload)) {
                continue;
            }

            try {
                $this->syncPupil(
                    $connector,
                    $user,
                    $request,
                    $pupilPayload,
                    $filter,
                    $pupilUpserter,
                    $evidenceUpserter,
                    $enqueueSreReevaluation,
                );
            } catch (Throwable $exception) {
                Log::error('ConnectorSync pupil failed.', [
                    'tenant_id' => $this->tenantId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
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
    ): void {
        $fieldPayload = Arr::only($pupilPayload, ConnectorField::values());
        $filtered = $filter->handle($connector, $this->schoolId, $fieldPayload);
        $result = $pupilUpserter->upsert($this->schoolId, $filtered, $user, $request);

        if (! isset($result['pupil'])) {
            return;
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
                    'message' => $evidenceResult['error'],
                ]);
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
    }
}
