<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantSsoRequest;
use App\Http\Resources\Api\V1\TenantSsoResource;

class TenantSsoController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function show(): TenantSsoResource
    {
        $tenant = CurrentTenant::require();

        $this->authorize('update', $tenant);

        return new TenantSsoResource($tenant);
    }

    public function update(UpdateTenantSsoRequest $request): TenantSsoResource
    {
        $tenant = CurrentTenant::require();

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
}
