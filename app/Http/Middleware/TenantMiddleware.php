<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ((bool) config('license.self_hosted_enabled', false)) {
            return $next($request);
        }

        // Super admins can always access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user can access the app
        if (! $user->canAccessApp()) {
            // Izinkan akses ke route subscription.* agar bisa bayar perpanjangan
            $allowedRoutes = [
                'subscription.expired', 'subscription.plans', 'subscription.subscribe',
                'subscription.payment', 'subscription.process-payment', 'subscription.index',
                'subscription.renew', 'subscription.callback', 'subscription.history',
                'subscription.history-datatable', 'subscription.subscriptions-datatable',
            ];
            if ($request->routeIs(...$allowedRoutes)) {
                return $next($request);
            }
            return redirect()->route('subscription.expired');
        }

        return $next($request);
    }
}
