<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateFeatureFlagRequest;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends Controller
{
    public function __construct(
        private FeatureFlagResolver $resolver,
        private AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = CurrentTenant::require();

        $this->authorize('view', $tenant);

        return response()->json([
            'data' => $this->resolver->mapFor($tenant),
        ]);
    }

    public function update(UpdateFeatureFlagRequest $request): JsonResponse
    {
        $tenant = CurrentTenant::require();

        $key = FeatureFlagKey::from($request->validated('key'));
        $enabled = (bool) $request->validated('enabled');

        $this->resolver->set($tenant, $key, $enabled);

        $this->audit->record(
            AuditEventType::FeatureFlagUpdated,
            $request,
            $request->user(),
            resourceType: 'tenant',
            resourceId: $tenant->id,
            metadata: [
                'flag_key' => $key->value,
                'enabled' => $enabled,
            ],
        );

        return response()->json([
            'data' => $this->resolver->mapFor($tenant->refresh()),
        ]);
    }
}
