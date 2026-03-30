<?php

namespace App\Http\Middleware;

use App\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemFeatureEnabled
{
    public function __construct(
        private readonly FeatureGateService $featureGateService,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->featureGateService->isEnabled($feature)) {
            return $next($request);
        }

        $message = $this->featureGateService->message($feature);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
