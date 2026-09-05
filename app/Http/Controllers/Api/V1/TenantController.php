<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantCohortRequest;
use App\Http\Resources\Api\V1\TenantResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function show(Request $request): TenantResource
    {
        $tenant = $this->currentTenantOrFail();

        $this->authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    public function update(UpdateTenantCohortRequest $request): TenantResource
    {
        $tenant = $this->currentTenantOrFail();

        $tenant->update($request->validated());

        $this->audit->record(
            AuditEventType::TenantCohortUpdated,
            $request,
            $request->user(),
            resourceType: 'tenant',
            resourceId: $tenant->id,
        );

        return new TenantResource($tenant->refresh());
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
