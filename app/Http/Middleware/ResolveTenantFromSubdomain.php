<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveTenantFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        if ((bool) config('license.self_hosted_enabled', false)) {
            return $next($request);
        }

        $host       = $request->getHost();
        $mainDomain = config('app.main_domain', 'watumalang.online');

        if (str_ends_with($host, '.' . $mainDomain)) {
            $subdomain = substr($host, 0, strlen($host) - strlen('.' . $mainDomain));
            app()->instance('tenant_subdomain', $subdomain);
        }

        return $next($request);
    }
}
