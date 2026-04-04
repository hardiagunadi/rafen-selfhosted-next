<?php

namespace App\Http\Middleware;

use App\Models\MikrotikConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RedirectIsolatedCaptivePortal
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (! $this->hasMikrotikConnectionsTable()) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();
        if ($clientIp === '') {
            return $next($request);
        }

        $ownerFromHost = (int) Cache::remember("isolir:nas-owner:{$clientIp}", now()->addMinute(), function () use ($clientIp): int {
            $ownerFromHost = (int) (MikrotikConnection::query()
                ->where('is_active', true)
                ->where('host', $clientIp)
                ->orderByDesc('is_online')
                ->value('owner_id') ?? 0);

            return $ownerFromHost;
        });

        $ownerFromPool = (int) Cache::remember("isolir:pool-owner:{$clientIp}", now()->addMinute(), function () use ($clientIp): int {
            return $this->resolveOwnerIdByIsolirPoolRange($clientIp);
        });

        $ownerId = $ownerFromHost > 0 ? $ownerFromHost : $ownerFromPool;

        if ($ownerId <= 0) {
            return $next($request);
        }

        // Klien yang sudah berada di pool isolir harus tetap diarahkan ke halaman isolir
        // walaupun mereka membuka APP_URL berbasis IP secara langsung.
        if ($ownerFromPool <= 0 && ! $this->isCaptiveRequest($request)) {
            return $next($request);
        }

        return redirect()->to(route('isolir.show', ['userId' => $ownerId], false));
    }

    private function resolveOwnerIdByIsolirPoolRange(string $clientIp): int
    {
        $ipLong = ip2long($clientIp);

        if ($ipLong === false) {
            return 0;
        }

        $connections = MikrotikConnection::query()
            ->where('is_active', true)
            ->whereNotNull('isolir_pool_range')
            ->orderByDesc('is_online')
            ->get(['owner_id', 'isolir_pool_range']);

        foreach ($connections as $connection) {
            $range = $this->parseIpRange((string) $connection->isolir_pool_range);
            if ($range === null) {
                continue;
            }

            if ($ipLong >= $range['start'] && $ipLong <= $range['end']) {
                return (int) $connection->owner_id;
            }
        }

        return 0;
    }

    /**
     * @return array{start:int, end:int}|null
     */
    private function parseIpRange(string $range): ?array
    {
        $normalized = trim($range);
        if ($normalized === '' || ! str_contains($normalized, '-')) {
            return null;
        }

        [$startIp, $endIp] = array_map('trim', explode('-', $normalized, 2));
        if ($startIp === '' || $endIp === '') {
            return null;
        }

        $startLong = ip2long($startIp);
        $endLong = ip2long($endIp);

        if ($startLong === false || $endLong === false) {
            return null;
        }

        if ($startLong > $endLong) {
            return null;
        }

        return ['start' => $startLong, 'end' => $endLong];
    }

    private function hasMikrotikConnectionsTable(): bool
    {
        try {
            return Schema::hasTable('mikrotik_connections');
        } catch (\Throwable) {
            return false;
        }
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('DELETE')) {
            return true;
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return true;
        }

        if ($request->user()) {
            return true;
        }

        if ($request->is('isolir/*')) {
            return true;
        }

        if ($request->is('webhook') || $request->is('webhook/*') || $request->is('webhook/wa') || $request->is('webhook/wa/*')) {
            return true;
        }

        if ($request->is('payment/callback') || $request->is('payment/callback/*') || $request->is('subscription/payment/callback')) {
            return true;
        }

        if ($request->is('bayar/*')) {
            return true;
        }

        if ($request->is('up')) {
            return true;
        }

        return false;
    }

    private function isCaptiveRequest(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        $normalizedPath = strtolower($path);
        $host = strtolower((string) $request->getHost());
        $appHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));

        $captivePaths = [
            'generate_204',
            'gen_204',
            'connecttest.txt',
            'ncsi.txt',
            'hotspot-detect.html',
            'getDNList',
            'getHttpDnsServerList',
            'chat',
            'route/mac/v1',
        ];

        if (in_array($normalizedPath, $captivePaths, true)) {
            return true;
        }

        if (str_starts_with($normalizedPath, 'generate204')) {
            return true;
        }

        if ($appHost === '') {
            return false;
        }

        // Subdomain dari main_domain bukan captive (e.g. tmd.watumalang.online)
        $mainDomain = strtolower((string) config('app.main_domain', ''));
        if ($mainDomain !== '' && str_ends_with($host, '.' . $mainDomain)) {
            return false;
        }

        return $host !== $appHost;
    }
}
