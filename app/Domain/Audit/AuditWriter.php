<?php

namespace App\Domain\Audit;

use App\Models\User;
use Illuminate\Http\Request;

class AuditWriter
{
    /**
     * Append an auth-related audit event. Never updates or deletes existing rows.
     */
    public function record(
        AuditEventType $eventType,
        Request $request,
        ?User $user = null,
        ?string $tenantId = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'event_type' => $eventType,
            'tenant_id' => $tenantId ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
