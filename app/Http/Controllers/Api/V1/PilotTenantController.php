<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\Tenant;
use App\Domain\Tenancy\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePilotTenantRequest;
use App\Http\Resources\Api\V1\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PilotTenantController extends Controller
{
    public const PILOT_COHORT_LABEL = 'Pilot cohort';

    public function __construct(private AuditWriter $audit) {}

    public function store(StorePilotTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $isSample = $validated['cohort_mode'] === StorePilotTenantRequest::COHORT_MODE_SAMPLE;

        $tenant = DB::transaction(function () use ($request, $validated, $isSample): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $validated['name'],
                'type' => TenantType::from($validated['type']),
                'cohort_enabled' => $isSample,
                'cohort_label' => $isSample ? self::PILOT_COHORT_LABEL : null,
            ]);

            $this->audit->record(
                AuditEventType::TenantCreated,
                $request,
                $request->user(),
                tenantId: $tenant->id,
                resourceType: 'tenant',
                resourceId: $tenant->id,
                metadata: [
                    'cohort_mode' => $validated['cohort_mode'],
                    'type' => $validated['type'],
                ],
            );

            return $tenant;
        });

        return (new TenantResource($tenant->refresh()))
            ->response()
            ->setStatusCode(201);
    }
}
