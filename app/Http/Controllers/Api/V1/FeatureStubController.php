<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\FeatureFlagKey;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder endpoints that prove feature gates open when a flag is enabled.
 * Full Trust / Connector / pack product surfaces land in later epics.
 */
class FeatureStubController extends Controller
{
    public function trustDashboard(): JsonResponse
    {
        return $this->placeholder(FeatureFlagKey::TrustDashboard);
    }

    public function connectors(): JsonResponse
    {
        return $this->placeholder(FeatureFlagKey::Connectors);
    }

    public function advancedDocumentationPacks(): JsonResponse
    {
        return $this->placeholder(FeatureFlagKey::AdvancedDocumentationPacks);
    }

    private function placeholder(FeatureFlagKey $feature): JsonResponse
    {
        return response()->json([
            'available' => true,
            'feature' => $feature->value,
            'placeholder' => true,
            'message' => 'Feature placeholder — full capability lands in a later epic.',
        ]);
    }
}
