<?php

namespace App\Domain\Tenancy;

use Database\Factories\TenantFeatureFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'key', 'enabled'])]
class TenantFeatureFlag extends Model
{
    /** @use HasFactory<TenantFeatureFlagFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => FeatureFlagKey::class,
            'enabled' => 'boolean',
        ];
    }

    protected static function newFactory(): TenantFeatureFlagFactory
    {
        return TenantFeatureFlagFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
