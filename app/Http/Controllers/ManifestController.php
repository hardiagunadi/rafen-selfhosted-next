<?php

namespace App\Http\Controllers;

use App\Models\TenantSettings;
use App\Services\PwaIconService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManifestController extends Controller
{
    public function admin(PwaIconService $pwaIconService): Response
    {
        $settings = $this->resolveSettings();
        $name = $pwaIconService->appName($settings, 'Rafen Manager', 'Admin');
        $shortName = $pwaIconService->appShortName($settings, 'Rafen', 'Admin');
        $icons = [
            ['src' => $pwaIconService->iconUrl($settings, 192, 'manifest.admin.icon'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => $pwaIconService->iconUrl($settings, 512, 'manifest.admin.icon'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ];

        $data = json_encode([
            'id' => '/',
            'name' => $name,
            'short_name' => $shortName,
            'description' => 'ISP Management System — Admin Panel',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f4f7fb',
            'theme_color' => '#1367a4',
            'lang' => 'id',
            'icons' => $icons,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($data, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-store',
        ])->withoutCookie('XSRF-TOKEN')->withoutCookie('laravel-session');
    }

    public function icon(int $size, PwaIconService $pwaIconService): BinaryFileResponse
    {
        abort_unless(in_array($size, [32, 180, 192, 512], true), 404);

        return response()->file($pwaIconService->iconPath($this->resolveSettings(), $size), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function resolveSettings(): ?TenantSettings
    {
        $subdomain = app()->has('tenant_subdomain') ? app('tenant_subdomain') : null;
        $settings = null;

        if ($subdomain) {
            $settings = TenantSettings::where('admin_subdomain', $subdomain)->first();
        }

        if (! $settings && auth()->check()) {
            $settings = TenantSettings::where('user_id', auth()->user()->effectiveOwnerId())->first();
        }

        if (! $settings) {
            $settings = TenantSettings::whereNotNull('business_name')
                ->where('business_name', '!=', '')
                ->first();
        }

        return $settings;
    }
}
