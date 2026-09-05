<?php

namespace App\Domain\Audit;

use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row. No update/delete product API.
 *
 * `created_at` is intentionally not fillable — only AuditWriter may set it via forceFill.
 */
#[Fillable([
    'event_type',
    'tenant_id',
    'user_id',
    'resource_type',
    'resource_id',
    'ip',
    'user_agent',
    'metadata',
])]
class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw AuditEventImmutableException::cannotUpdate();
        });

        static::deleting(function (): void {
            throw AuditEventImmutableException::cannotDelete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
