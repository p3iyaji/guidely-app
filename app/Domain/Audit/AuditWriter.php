<?php

namespace App\Domain\Audit;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Append-only Audit Event writer.
 *
 * Accepts structured scalar fields only. Never accepts an `evidence` payload or
 * full Evidence body (extension point for later domains — keep Evidence out of info logs).
 */
class AuditWriter
{
    /**
     * @var list<string>
     */
    private const RESERVED_METADATA_KEYS = [
        'event_type',
        'tenant_id',
        'user_id',
        'resource_type',
        'resource_id',
    ];

    /**
     * Append an audit event. Never updates or deletes existing rows.
     *
     * @param  array<string, bool|int|float|string|null>  $metadata  Scalar metadata only; `evidence` and reserved keys are rejected.
     */
    public function record(
        AuditEventType $eventType,
        Request $request,
        ?User $user = null,
        ?string $tenantId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $metadata = [],
    ): AuditEvent {
        $this->assertNoEvidencePayload($metadata);
        $this->assertNoReservedMetadataKeys($metadata);
        $this->assertScalarMetadata($metadata);
        $this->assertTenantMatchesUser($user, $tenantId);

        $resolvedTenantId = $tenantId ?? $user?->tenant_id;
        $persistedMetadata = $metadata === [] ? null : $metadata;

        $event = new AuditEvent([
            'event_type' => $eventType,
            'tenant_id' => $resolvedTenantId,
            'user_id' => $user?->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $persistedMetadata,
        ]);
        $event->forceFill([
            'created_at' => now(),
        ])->save();

        Log::info('audit.recorded', array_merge($metadata, [
            'event_type' => $eventType->value,
            'tenant_id' => $resolvedTenantId,
            'user_id' => $user?->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]));

        return $event;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function assertNoEvidencePayload(array $metadata): void
    {
        if (array_key_exists('evidence', $metadata)) {
            throw new InvalidArgumentException(
                'AuditWriter must not accept Evidence bodies or an evidence payload key.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function assertNoReservedMetadataKeys(array $metadata): void
    {
        foreach (self::RESERVED_METADATA_KEYS as $reservedKey) {
            if (array_key_exists($reservedKey, $metadata)) {
                throw new InvalidArgumentException(
                    "AuditWriter metadata must not include reserved key [{$reservedKey}]."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function assertScalarMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('AuditWriter metadata keys must be strings.');
            }

            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException(
                    'AuditWriter metadata values must be scalars or null; nested payloads are not allowed.'
                );
            }
        }
    }

    private function assertTenantMatchesUser(?User $user, ?string $tenantId): void
    {
        if ($user === null || $tenantId === null) {
            return;
        }

        if ($user->tenant_id !== $tenantId) {
            throw new InvalidArgumentException(
                'AuditWriter tenant_id does not match the provided user tenant.'
            );
        }
    }
}
