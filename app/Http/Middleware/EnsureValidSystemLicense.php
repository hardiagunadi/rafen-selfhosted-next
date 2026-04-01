<?php

namespace App\Http\Middleware;

use App\Services\SystemLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidSystemLicense
{
    public function __construct(
        private readonly SystemLicenseService $systemLicenseService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->systemLicenseService->isSelfHostedEnabled()) {
            return $next($request);
        }

        if (! $this->systemLicenseService->isEnforced()) {
            return $next($request);
        }

        if (
            $request->routeIs('super-admin.settings.license')
            || $request->routeIs('super-admin.settings.license.update')
        ) {
            return $next($request);
        }

        if ($this->systemLicenseService->allowsAccess()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, 'Lisensi sistem belum valid.');
        }

        if ($request->user()?->isSuperAdmin()) {
            return redirect()
                ->route('super-admin.settings.license')
                ->with('error', 'Lisensi sistem belum valid. Silakan unggah lisensi yang benar.');
        }

        abort(423, 'Lisensi sistem belum valid.');
    }
}
