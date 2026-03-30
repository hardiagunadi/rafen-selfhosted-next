<?php

use App\Http\Middleware\EnsureSystemFeatureEnabled;
use App\Http\Middleware\EnsureTenantModuleEnabled;
use App\Http\Middleware\EnsureValidSystemLicense;
use App\Http\Middleware\PortalAuth;
use App\Http\Middleware\RedirectIsolatedCaptivePortal;
use App\Http\Middleware\ResolveTenantFromSubdomain;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedirectIsolatedCaptivePortal::class);
        $middleware->prepend(ResolveTenantFromSubdomain::class);

        $middleware->validateCsrfTokens(except: [
            'payment/callback',
            'subscription/payment/callback',
            'webhook/wa',
            'webhook/wa/*',
            'webhook/wa/session',
            'webhook/wa/message',
            'webhook/wa/auto-reply',
            'webhook/wa/status',
            'webhook',
            'webhook/*',
            'webhook/session',
            'webhook/message',
            'webhook/auto-reply',
            'webhook/status',
            'wa-multi-session',
            'wa-multi-session/*',
        ]);
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'tenant.module' => EnsureTenantModuleEnabled::class,
            'portal.auth' => PortalAuth::class,
            'super.admin' => SuperAdminMiddleware::class,
            'role' => RoleMiddleware::class,
            'system.license' => EnsureValidSystemLicense::class,
            'system.feature' => EnsureSystemFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
