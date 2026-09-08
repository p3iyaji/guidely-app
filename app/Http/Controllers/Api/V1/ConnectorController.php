<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorAdapterRegistry;
use App\Domain\Connectors\ConnectorField;
use App\Domain\Connectors\ConnectorType;
use App\Domain\Connectors\UnsupportedConnectorTypeException;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncConnectorRequest;
use App\Http\Requests\Api\V1\UpsertConnectorRequest;
use App\Http\Resources\Api\V1\ConnectorResource;
use App\Jobs\ConnectorSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ConnectorController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private ConnectorAdapterRegistry $adapters,
    ) {}

    public function index(): ConnectorResource
    {
        return $this->show();
    }

    public function show(): ConnectorResource
    {
        $connector = Connector::query()->first();

        if ($connector === null) {
            $this->authorize('viewAny', Connector::class);

            return new ConnectorResource($this->defaultConnector());
        }

        $this->authorize('view', $connector);

        return new ConnectorResource($connector);
    }

    public function upsert(UpsertConnectorRequest $request): JsonResponse
    {
        $existing = Connector::query()->first();

        if ($existing === null) {
            $this->authorize('create', Connector::class);
        } else {
            $this->authorize('update', $existing);
        }

        if ($request->exists('type')) {
            $type = $request->type();
        } elseif ($existing === null) {
            $type = ConnectorType::PilotStub;
        } else {
            $type = $existing->type;

            if (! $type instanceof ConnectorType) {
                throw new UnsupportedConnectorTypeException;
            }
        }

        $this->adapters->for($type);

        $connector = DB::transaction(function () use ($request, $type): Connector {
            /** @var Connector $connector */
            $connector = Connector::query()->lockForUpdate()->first()
                ?? new Connector([
                    'tenant_id' => $request->user()?->tenant_id ?? CurrentTenant::id(),
                ]);

            $beforeShares = $connector->normalizedFieldShares();

            $updates = [
                'type' => $type,
                'enabled' => $request->enabled((bool) $connector->enabled),
            ];

            if ($request->exists('field_shares')) {
                $updates['field_shares'] = $request->normalizedFieldShares();
            } elseif (! $connector->exists) {
                $updates['field_shares'] = [];
            }

            if ($request->hasSecretInput()) {
                $updates['secret'] = Crypt::encryptString($request->secret());
            }

            $connector->forceFill($updates)->save();

            $this->audit->record(
                AuditEventType::ConnectorUpdated,
                $request,
                $request->user(),
                resourceType: 'connector',
                resourceId: $connector->id,
                metadata: $this->auditMetadata($beforeShares, $connector),
            );

            return $connector;
        });

        return (new ConnectorResource($connector->refresh()))
            ->response()
            ->setStatusCode(200);
    }

    public function sync(SyncConnectorRequest $request): JsonResponse
    {
        $existing = Connector::query()->first();

        if ($existing === null) {
            $this->authorize('create', Connector::class);
        } else {
            $this->authorize('sync', $existing);
        }

        if ($existing === null || $existing->enabled !== true) {
            return response()->json([
                'message' => 'The Connector is disabled.',
                'code' => 'connector_disabled',
            ], 422);
        }

        $type = $existing->type;

        if (! $type instanceof ConnectorType) {
            throw new UnsupportedConnectorTypeException;
        }

        $this->adapters->for($type);

        $user = $request->user();

        if ($user === null || $user->tenant_id === null) {
            abort(401);
        }

        ConnectorSync::dispatch(
            $user->tenant_id,
            $request->string('school_id')->toString(),
            $user->id,
            $request->validated('pupils'),
        );

        return response()->json([
            'message' => 'Connector sync queued.',
        ], 202);
    }

    private function defaultConnector(): Connector
    {
        $connector = new Connector;

        $connector->forceFill([
            'tenant_id' => CurrentTenant::id(),
            'type' => ConnectorType::PilotStub,
            'enabled' => false,
            'field_shares' => [],
        ]);

        return $connector;
    }

    /**
     * @param  list<array{school_id: string, fields: array<string, bool>}>  $beforeShares
     * @return array<string, bool|int|float|string|null>
     */
    private function auditMetadata(array $beforeShares, Connector $after): array
    {
        $metadata = [
            'enabled' => $after->enabled,
            'type' => $after->type instanceof ConnectorType
                ? $after->type->value
                : ConnectorType::PilotStub->value,
            'has_secret' => $after->hasSecret(),
        ];

        $beforeMap = $this->shareMap($beforeShares);
        $afterMap = $this->shareMap($after->normalizedFieldShares());
        $schoolIds = array_unique([...array_keys($beforeMap), ...array_keys($afterMap)]);

        foreach ($schoolIds as $schoolId) {
            foreach (ConnectorField::values() as $field) {
                $beforeValue = $beforeMap[$schoolId][$field] ?? false;
                $afterValue = $afterMap[$schoolId][$field] ?? false;

                if ($beforeValue !== $afterValue) {
                    $metadata[$schoolId.'.'.$field] = $afterValue;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param  list<array{school_id: string, fields: array<string, bool>}>  $shares
     * @return array<string, array<string, bool>>
     */
    private function shareMap(array $shares): array
    {
        $map = [];

        foreach ($shares as $share) {
            $map[$share['school_id']] = $share['fields'];
        }

        return $map;
    }
}
