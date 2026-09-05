<?php

namespace App\Exceptions;

use App\Domain\Tenancy\FeatureFlagKey;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureNotAvailableException extends Exception
{
    public function __construct(public readonly FeatureFlagKey $feature)
    {
        parent::__construct('This feature is not available for this Tenant.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'feature_not_available',
            'feature' => $this->feature->value,
        ], 403);
    }
}
