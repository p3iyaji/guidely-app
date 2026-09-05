<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateFeatureFlagRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FeatureFlagController extends Controller
{
    public function __construct(private FeatureFlagResolver $resolver) {}

    public function index(): JsonResponse
    {
        $tenant = $this->currentTenantOrFail();

        $this->authorize('view', $tenant);

        return response()->json([
            'data' => $this->resolver->mapFor($tenant),
        ]);
    }

    public function update(UpdateFeatureFlagRequest $request): JsonResponse
    {
        $tenant = $this->currentTenantOrFail();

        $key = FeatureFlagKey::from($request->validated('key'));
        $enabled = (bool) $request->validated('enabled');

        $this->resolver->set($tenant, $key, $enabled);

        return response()->json([
            'data' => $this->resolver->mapFor($tenant->refresh()),
        ]);
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
