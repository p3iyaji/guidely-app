<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Exceptions\FeatureNotAvailableException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureIsEnabled
{
    public function __construct(private FeatureFlagResolver $resolver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $key = FeatureFlagKey::tryFrom($feature);

        if ($key === null) {
            abort(500, 'Unknown feature flag key configured on route.');
        }

        if (! $this->resolver->isEnabled($key)) {
            throw new FeatureNotAvailableException($key);
        }

        return $next($request);
    }
}
