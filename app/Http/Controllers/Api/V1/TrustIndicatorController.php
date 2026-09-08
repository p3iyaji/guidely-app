<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Role;
use App\Domain\Reporting\BuildTrustIndicators;
use App\Domain\Reporting\TrustIndicator;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrustIndicatorResource;
use App\Models\User;
use Illuminate\Http\Request;

class TrustIndicatorController extends Controller
{
    /**
     * Read-only Trust Indicators across enabled Schools.
     */
    public function show(
        Request $request,
        BuildTrustIndicators $build,
        FeatureFlagResolver $flags,
    ): TrustIndicatorResource {
        $this->authorize('view', TrustIndicator::class);

        /** @var User $user */
        $user = $request->user();

        return new TrustIndicatorResource(
            $build->handle(
                $user->role === Role::TrustSendLead,
                $flags->isEnabled(FeatureFlagKey::PortfolioBenchmarking),
            ),
        );
    }
}
