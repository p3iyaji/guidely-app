<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reviews\RollForwardReviewCycles;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewCycleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewCycleAutomationController extends Controller
{
    public function __construct(private RollForwardReviewCycles $rollForward) {}

    public function store(Request $request): JsonResponse
    {
        $tenant = CurrentTenant::require();

        $this->authorize('update', $tenant);

        $created = $this->rollForward->handle($tenant, $request);

        $response = ReviewCycleResource::collection($created)->response();

        if ($created->isNotEmpty()) {
            $response->setStatusCode(201);
        }

        return $response;
    }
}
