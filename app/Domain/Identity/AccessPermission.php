<?php

namespace App\Domain\Identity;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use Database\Factories\AccessPermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'key', 'label', 'description', 'group', 'is_system'])]
class AccessPermission extends Model
{
    /** @use HasFactory<AccessPermissionFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function newFactory(): AccessPermissionFactory
    {
        return AccessPermissionFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<AccessRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AccessRole::class, 'access_permission_role')
            ->withTimestamps();
    }

    public function isInUse(): bool
    {
        return $this->roles()->exists();
    }

    /**
     * System Permissions plus custom Permissions for the current Tenant.
     */
    #[Scope]
    protected function visibleToCurrentTenant(Builder $query): Builder
    {
        $tenantId = CurrentTenant::id();

        return $query->where(function (Builder $inner) use ($tenantId): void {
            $inner->whereNull($inner->qualifyColumn('tenant_id'));

            if ($tenantId !== null) {
                $inner->orWhere($inner->qualifyColumn('tenant_id'), $tenantId);
            }
        });
    }

    /**
     * Scope route-model binding to system rows or the current Tenant (404 otherwise).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::query()
            ->visibleToCurrentTenant()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
