<?php

namespace App\Models;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'external_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Always persist emails lowercased so login/SSO lookups stay consistent.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null
                ? null
                : Str::lower(trim($value)),
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)->withTimestamps();
    }

    /**
     * BelongsToTenant-equivalent for User admin queries — local scope only.
     * Do not attach BelongsToTenant; null-tenant 0=1 would break login email lookup.
     */
    #[Scope]
    protected function forCurrentTenant(Builder $query): Builder
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where($query->qualifyColumn('tenant_id'), $tenantId);
    }

    /**
     * Scope route-model binding to the current Tenant (404 for cross-Tenant ids).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::query()
            ->forCurrentTenant()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === Role::TenantAdmin;
    }

    public function isPlatformOperator(): bool
    {
        return $this->role === Role::PlatformOperator && $this->tenant_id === null;
    }

    /**
     * Whether this User is the sole active Tenant Admin for their Tenant.
     */
    public function isLastActiveTenantAdmin(): bool
    {
        if ($this->tenant_id === null || ! $this->isTenantAdmin() || $this->isDeactivated()) {
            return false;
        }

        return static::activeTenantAdminCount($this->tenant_id) === 1;
    }

    /**
     * Count of active Tenant Admins for a Tenant (orphan-guard input).
     */
    public static function activeTenantAdminCount(string $tenantId): int
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('role', Role::TenantAdmin)
            ->whereNull('deactivated_at')
            ->count();
    }

    /**
     * Active same-tenant staff for current product surfaces.
     * Trust Roles require trust_dashboard; Platform Operator is never Tenant staff.
     */
    public function isActiveTenantStaff(?FeatureFlagResolver $resolver = null): bool
    {
        if ($this->isDeactivated() || $this->tenant_id === null || $this->role === null) {
            return false;
        }

        if (! $this->role->isTenantAssignable()) {
            return false;
        }

        if ($this->role->isTrustRole()) {
            return $this->isActiveTrustStaff($resolver);
        }

        return true;
    }

    /**
     * Trust Roles are only active staff when trust_dashboard is enabled for the Tenant.
     */
    public function isActiveTrustStaff(?FeatureFlagResolver $resolver = null): bool
    {
        if ($this->role === null || ! $this->role->isTrustRole()) {
            return false;
        }

        $resolver ??= app(FeatureFlagResolver::class);

        return $resolver->isEnabled(FeatureFlagKey::TrustDashboard, $this->tenant);
    }

    /**
     * Tenant Admins and active Trust staff see every School in the Tenant.
     * Other Roles are limited to the school_user pivot.
     */
    public function seesAllTenantSchools(?FeatureFlagResolver $resolver = null): bool
    {
        if (! $this->isActiveTenantStaff($resolver)) {
            return false;
        }

        return $this->isTenantAdmin() || $this->isActiveTrustStaff($resolver);
    }

    /**
     * Whether this User may view/access a School (same Tenant + Admin/Trust or pivot).
     */
    public function canAccessSchool(School $school, ?FeatureFlagResolver $resolver = null): bool
    {
        if (! $this->isActiveTenantStaff($resolver)) {
            return false;
        }

        if ($this->tenant_id === null || $this->tenant_id !== $school->tenant_id) {
            return false;
        }

        if ($this->seesAllTenantSchools($resolver)) {
            return true;
        }

        return $this->schools()->whereKey($school->id)->exists();
    }

    /**
     * Mark the user deactivated and revoke Sanctum tokens immediately.
     */
    public function deactivate(): void
    {
        $this->forceFill([
            'deactivated_at' => now(),
        ])->save();

        $this->tokens()->delete();
    }
}
