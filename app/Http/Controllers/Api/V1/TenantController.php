<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantCohortRequest;
use App\Http\Resources\Api\V1\TenantResource;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function show(Request $request): TenantResource
    {
        $tenant = CurrentTenant::require();

        $this->authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    public function update(UpdateTenantCohortRequest $request): TenantResource
    {
        $tenant = CurrentTenant::require();

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
}
