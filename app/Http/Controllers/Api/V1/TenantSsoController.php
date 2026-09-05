<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantSsoRequest;
use App\Http\Resources\Api\V1\TenantSsoResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantSsoController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function show(): TenantSsoResource
    {
        $tenant = $this->currentTenantOrFail();

        $this->authorize('update', $tenant);

        return new TenantSsoResource($tenant);
    }

    public function update(UpdateTenantSsoRequest $request): TenantSsoResource
    {
        $tenant = $this->currentTenantOrFail();

        $tenant->update($request->validated());

        $this->audit->record(
            AuditEventType::TenantSsoUpdated,
            $request,
            $request->user(),
            resourceType: 'tenant',
            resourceId: $tenant->id,
        );

        return new TenantSsoResource($tenant->refresh());
    }

    private function currentTenantOrFail(): Tenant
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            throw new NotFoundHttpException;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            throw new NotFoundHttpException;
        }

        return $tenant;
    }
}
