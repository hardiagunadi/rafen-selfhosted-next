<?php

namespace App\Http\Middleware;

use App\Models\PortalSession;
use App\Models\TenantSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class PortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('portal_session');

        if (! $token) {
            return $this->redirectToLogin($request);
        }

        $session = PortalSession::with('pppUser')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $session || ! $session->pppUser) {
            return $this->redirectToLogin($request);
        }

        $tenantSettings = $this->resolveTenantSettings($request);
        if ($tenantSettings && $tenantSettings->user_id !== $session->pppUser->owner_id) {
            return $this->redirectToLogin($request);
        }

        $session->update([
            'last_activity_at' => now(),
            'expires_at' => PortalSession::newExpiry(),
        ]);

        $request->attributes->set('portal_ppp_user', $session->pppUser);
        $request->attributes->set('portal_session', $session);

        $response = $next($request);
        $response->headers->setCookie(
            Cookie::make('portal_session', $token, PortalSession::LIFETIME_MINUTES, '/', null, false, true)
        );

        return $response;
    }

    private function redirectToLogin(Request $request): Response
    {
        $query = [];
        if ($request->filled('slug')) {
            $query['slug'] = $request->query('slug');
        }
        $queryString = $query ? '?'.http_build_query($query) : '';

        return redirect()->to($request->getSchemeAndHttpHost().'/portal/login'.$queryString);
    }

    private function resolveTenantSettings(Request $request): ?TenantSettings
    {
        $subdomain = app()->has('tenant_subdomain') ? app('tenant_subdomain') : null;
        if ($subdomain) {
            return TenantSettings::where('admin_subdomain', $subdomain)
                ->orWhere('portal_slug', $subdomain)
                ->first();
        }

        if ($request->filled('slug')) {
            $slug = (string) $request->query('slug');

            return TenantSettings::where('portal_slug', $slug)
                ->orWhere('admin_subdomain', $slug)
                ->first();
        }

        return null;
    }
}
