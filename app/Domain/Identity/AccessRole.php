<?php

namespace App\Domain\Identity;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Database\Factories\AccessRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'key', 'label', 'description', 'is_system'])]
class AccessRole extends Model
{
    /** @use HasFactory<AccessRoleFactory> */
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

    protected static function newFactory(): AccessRoleFactory
    {
        return AccessRoleFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<AccessPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AccessPermission::class, 'access_permission_role')
            ->withTimestamps();
    }

    public function isInUse(): bool
    {
        return User::query()
            ->where('role', $this->key)
            ->when(
                $this->tenant_id !== null,
                fn (Builder $query) => $query->where('tenant_id', $this->tenant_id),
            )
            ->exists();
    }

    /**
     * System Roles plus custom Roles for the current Tenant.
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
