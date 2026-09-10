<?php

namespace App\Domain\Connectors;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped Connector configuration (one row per Tenant).
 *
 * Domain\Connectors is the sole writer (forceFill). Not mass-assignable from capture APIs.
 */
#[Fillable([
    'tenant_id',
])]
class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'pilot_stub',
        'enabled' => false,
        'field_shares' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConnectorType::class,
            'enabled' => 'boolean',
            'field_shares' => 'array',
            'last_sync_started_at' => 'datetime',
            'last_sync_completed_at' => 'datetime',
            'last_sync_failed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ConnectorFactory
    {
        return ConnectorFactory::new();
    }

    public function hasSecret(): bool
    {
        $secret = $this->attributes['secret'] ?? null;

        return is_string($secret) && $secret !== '';
    }

    /**
     * @return array<string, bool>
     */
    public function fieldSharesFor(string $schoolId): array
    {
        $defaults = ConnectorField::defaultMap();

        foreach ($this->normalizedFieldShares() as $share) {
            if ($share['school_id'] === $schoolId) {
                return array_merge($defaults, $share['fields']);
            }
        }

        return $defaults;
    }

    /**
     * @return list<array{school_id: string, fields: array<string, bool>}>
     */
    public function normalizedFieldShares(): array
    {
        $shares = $this->field_shares;

        if (! is_array($shares)) {
            return [];
        }

        $normalized = [];

        foreach ($shares as $share) {
            if (! is_array($share) || ! isset($share['school_id']) || ! is_string($share['school_id'])) {
                continue;
            }

            $fields = ConnectorField::defaultMap();
            $rawFields = $share['fields'] ?? [];

            if (is_array($rawFields)) {
                foreach ($rawFields as $key => $value) {
                    if (is_string($key) && array_key_exists($key, $fields)) {
                        $fields[$key] = ConnectorField::isShared($value);
                    }
                }
            }

            $normalized[] = [
                'school_id' => $share['school_id'],
                'fields' => $fields,
            ];
        }

        return $normalized;
    }
}
