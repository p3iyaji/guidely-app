<?php

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('tenant_id'))) {
                return;
            }

            $tenantId = CurrentTenant::id();

            if ($tenantId === null) {
                throw new LogicException('Cannot create a tenant-owned model without a current Tenant.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = CurrentTenant::id();

            if ($tenantId === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
