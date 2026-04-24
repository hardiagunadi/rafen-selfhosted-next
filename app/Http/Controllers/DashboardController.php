<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\RadiusAccount;
use App\Models\User;
use App\Services\MikrotikApiClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $hotspotModuleEnabled = $user->isHotspotModuleEnabled();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->get();

        $pppAccounts = RadiusAccount::query()
            ->accessibleBy($user)
            ->where('service', 'pppoe')->where('is_active', true)->count();
        $hotspotAccounts = $hotspotModuleEnabled
            ? RadiusAccount::query()
                ->accessibleBy($user)
                ->where('service', 'hotspot')
                ->where('is_active', true)
                ->count()
            : 0;

        $invoicesMonth = Invoice::query()
            ->accessibleBy($user)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get();
        $incomeTodayQuery = Invoice::query()
            ->accessibleBy($user)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$todayStart, $todayEnd]);

        if ($user->role === 'teknisi') {
            $incomeTodayQuery
                ->where('paid_by', $user->id)
                ->where('cash_received', '>', 0);
        }

        $incomeToday = (float) $incomeTodayQuery->sum('total');
        $invoiceCountMonth = $invoicesMonth->where('status', 'unpaid')->count();

        $stats = [
            'income_today' => $incomeToday,
            'invoice_count' => $invoiceCountMonth,
            'ppp_online' => $pppAccounts,
            'hotspot_online' => $hotspotAccounts,
            'router_total' => $routers->count(),
            'router_online' => $routers->where('is_online', true)->count(),
            'router_offline' => $routers->where('is_online', false)->count(),
            'ppp_users' => PppUser::query()->accessibleBy($user)->count(),
            'ppp_active' => PppUser::query()->accessibleBy($user)->where('status_akun', 'enable')->count(),
            'ppp_isolir' => PppUser::query()->accessibleBy($user)->where('status_akun', 'isolir')->count(),
            'invoice_paid_month' => $invoicesMonth->where('status', 'paid')->count(),
            'invoice_total_month' => $invoicesMonth->count(),
        ];

        // Daftar owner hanya untuk super admin (untuk filter switcher)
        $owners = $user->isSuperAdmin()
            ? User::query()->tenants()->orderBy('name')->get()
            : collect();

        return view('dashboard', compact('stats', 'owners', 'hotspotModuleEnabled'));
    }

    public function apiDashboard(Request $request): View
    {
        $user = auth()->user();
        $hotspotModuleEnabled = $user->isHotspotModuleEnabled();
        $connections = MikrotikConnection::query()
            ->accessibleBy($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $selectedConnection = $this->resolveConnection($request->integer('connection_id'), $connections);
        $resource = $this->apiDashboardPayload($selectedConnection);

        return view('api-dashboard', compact('connections', 'selectedConnection', 'resource', 'hotspotModuleEnabled'));
    }

    public function apiDashboardData(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);

        return response()->json([
            'data' => $this->apiDashboardPayload($connection),
        ]);
    }

    public function restartRadius(Request $request): JsonResponse|RedirectResponse
    {
        $command = config('radius.reload_command', 'systemctl reload freeradius');
        $result = Process::timeout(30)->run($command);
        $error = trim($result->errorOutput() ?: $result->output());

        if ($request->wantsJson()) {
            if ($result->successful()) {
                return response()->json(['status' => 'ok', 'message' => 'Core Radius berhasil direload.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Gagal reload Core Radius: '.$error], 500);
        }

        if ($result->successful()) {
            return redirect()->route('dashboard')->with('status', 'Core Radius berhasil direload.');
        }

        return redirect()->route('dashboard')->with('error', 'Gagal reload Core Radius: '.$error);
    }

    public function restartGenieacs(Request $request): JsonResponse
    {
        $services = ['genieacs-cwmp', 'genieacs-nbi', 'genieacs-fs'];
        $errors = [];

        foreach ($services as $svc) {
            $result = Process::timeout(30)->run("sudo systemctl restart {$svc}");
            if (! $result->successful()) {
                $errors[] = $svc.': '.trim($result->errorOutput() ?: $result->output());
            }
        }

        if (empty($errors)) {
            return response()->json(['status' => 'ok', 'message' => 'GenieACS berhasil direstart.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Gagal restart GenieACS: '.implode('; ', $errors)], 500);
    }

    /**
     * @return array<string, string>
     */
    private function systemMetrics(): array
    {
        return [
            'uptime' => $this->formatUptime($this->uptimeSeconds()),
            'ram_total' => $this->formatBytes($this->memoryInfo('MemTotal')),
            'ram_free' => $this->formatBytes($this->memoryInfo('MemAvailable')),
            'disk_total' => $this->formatBytes(@disk_total_space('/') ?: 0),
            'disk_free' => $this->formatBytes(@disk_free_space('/') ?: 0),
        ];
    }

    /**
     * @return array{uptime:int, ram_total:int, ram_free:int, disk_total:int, disk_free:int}
     */
    private function systemMetricsRaw(): array
    {
        return [
            'uptime' => $this->uptimeSeconds(),
            'ram_total' => $this->memoryInfo('MemTotal'),
            'ram_free' => $this->memoryInfo('MemAvailable'),
            'disk_total' => (int) (@disk_total_space('/') ?: 0),
            'disk_free' => (int) (@disk_free_space('/') ?: 0),
        ];
    }

    private function uptimeSeconds(): int
    {
        $contents = @file_get_contents('/proc/uptime');

        if (! $contents) {
            return 0;
        }

        $parts = explode(' ', trim($contents));

        return (int) ($parts[0] ?? 0);
    }

    private function memoryInfo(string $key): int
    {
        $contents = @file('/proc/meminfo');
        if (! $contents) {
            return 0;
        }

        foreach ($contents as $line) {
            if (str_starts_with($line, $key)) {
                $parts = preg_split('/\s+/', trim($line));
                $valueKb = (int) ($parts[1] ?? 0);

                return $valueKb * 1024;
            }
        }

        return 0;
    }

    /**
     * @return array{model:?string, cores:int, mhz:?int}
     */
    private function cpuMetrics(): array
    {
        $contents = @file('/proc/cpuinfo');
        if (! $contents) {
            return [
                'model' => null,
                'cores' => 1,
                'mhz' => null,
            ];
        }

        $model = null;
        $mhz = null;
        $cores = 0;
        foreach ($contents as $line) {
            if (str_starts_with($line, 'model name')) {
                $model = trim(explode(':', $line, 2)[1] ?? '');
            }
            if (str_starts_with($line, 'cpu MHz')) {
                $value = trim(explode(':', $line, 2)[1] ?? '');
                $mhz = is_numeric($value) ? (int) round((float) $value) : $mhz;
            }
            if (str_starts_with($line, 'processor')) {
                $cores++;
            }
        }

        return [
            'model' => $model ?: null,
            'cores' => max(1, $cores),
            'mhz' => $mhz,
        ];
    }

    private function cpuLoadPercent(int $cores): ?float
    {
        $load = sys_getloadavg();
        if (! $load || $cores <= 0) {
            return null;
        }

        return min(100, max(0, ($load[0] / $cores) * 100));
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'N/A';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'d';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        return implode(' ', $parts) ?: '0m';
    }

    private function formatPercent(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value, 3, '.', '').'%';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, 1).' '.$units[$power];
    }

    private function buildTimestamp(): ?int
    {
        $candidates = [
            base_path('bootstrap/cache/config.php'),
            base_path('composer.lock'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $timestamp = filemtime($path);

                return $timestamp ?: null;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MikrotikConnection>|null  $connections
     */
    private function resolveConnection(?int $connectionId, ?Collection $connections = null): ?MikrotikConnection
    {
        if ($connections) {
            if ($connectionId) {
                return $connections->firstWhere('id', $connectionId) ?? $connections->first();
            }

            return $connections->first();
        }

        $query = MikrotikConnection::query()->where('is_active', true)->orderBy('name');
        if ($connectionId) {
            $selected = $query->whereKey($connectionId)->first();

            return $selected ?: MikrotikConnection::query()->where('is_active', true)->orderBy('name')->first();
        }

        return $query->first();
    }

    private function resolveConnectionForUser(?int $connectionId, User $user): ?MikrotikConnection
    {
        $base = MikrotikConnection::query()->accessibleBy($user)->where('is_active', true)->orderBy('name');
        if ($connectionId) {
            return (clone $base)->whereKey($connectionId)->first()
                ?? $base->first();
        }

        return $base->first();
    }

    /**
     * @return array{
     *     platform_vendor:string,
     *     platform_model:string,
     *     routeros:string,
     *     cpu_type:string,
     *     cpu_cores:string,
     *     cpu_mhz:string,
     *     cpu_load:string,
     *     ram_free_percent:string,
     *     disk_free_percent:string,
     *     build_date:string,
     *     build_time:string,
     *     uptime:string
     * }
     */
    public function apiDashboardMenu(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);

        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $menu = $request->string('menu')->toString();

        $menuMap = [
            'interface' => '/interface/print',
            'ppp_active' => '/ppp/active/print',
            'ppp_setting' => '/ppp/secret/print',
            'ppp_interface' => '/interface/pppoe-client/print',
            'pppoe_server' => '/interface/pppoe-server/server/print',
            'hotspot_active' => '/ip/hotspot/active/print',
            'hotspot_setting' => '/ip/hotspot/print',
            'hotspot_ip_binding' => '/ip/hotspot/ip-binding/print',
            'hotspot_server' => '/ip/hotspot/server/print',
            'hotspot_profiles' => '/ip/hotspot/profile/print',
            'hotspot_cookies' => '/ip/hotspot/cookie/print',
        ];

        if (! isset($menuMap[$menu])) {
            return response()->json(['error' => 'Menu tidak valid.'], 422);
        }

        if (str_starts_with($menu, 'hotspot_') && ! $user->isHotspotModuleEnabled()) {
            return response()->json(['error' => 'Modul hotspot dinonaktifkan untuk tenant ini.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $result = $client->command($menuMap[$menu]);
            $client->disconnect();

            return response()->json(['data' => $result['data']]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppSecretStore(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $name = trim($request->string('name')->toString());
        $password = trim($request->string('password')->toString());
        $profile = trim($request->string('profile')->toString());
        $service = trim($request->string('service', 'pppoe')->toString());

        if ($name === '') {
            return response()->json(['error' => 'Name wajib diisi.'], 422);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['name' => $name, 'password' => $password, 'service' => $service];
            if ($profile !== '') {
                $attrs['profile'] = $profile;
            }
            $client->command('/ppp/secret/add', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'PPP Secret berhasil ditambahkan.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppSecretUpdate(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['.id' => $id];
            if ($request->filled('password')) {
                $attrs['password'] = $request->string('password')->toString();
            }
            if ($request->filled('profile')) {
                $attrs['profile'] = $request->string('profile')->toString();
            }
            if ($request->filled('service')) {
                $attrs['service'] = $request->string('service')->toString();
            }
            if ($request->filled('comment')) {
                $attrs['comment'] = $request->string('comment')->toString();
            }
            $client->command('/ppp/secret/set', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'PPP Secret berhasil diperbarui.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppSecretDestroy(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/ppp/secret/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'PPP Secret berhasil dihapus.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppActiveDisconnect(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/ppp/active/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'Session PPP berhasil didisconnect.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotUserStore(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $name = trim($request->string('name')->toString());
        if ($name === '') {
            return response()->json(['error' => 'Name wajib diisi.'], 422);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['name' => $name];
            if ($request->filled('password')) {
                $attrs['password'] = $request->string('password')->toString();
            }
            if ($request->filled('profile')) {
                $attrs['profile'] = $request->string('profile')->toString();
            }
            if ($request->filled('server')) {
                $attrs['server'] = $request->string('server')->toString();
            }
            $client->command('/ip/hotspot/user/add', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'Hotspot user berhasil ditambahkan.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotUserUpdate(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['.id' => $id];
            if ($request->filled('password')) {
                $attrs['password'] = $request->string('password')->toString();
            }
            if ($request->filled('profile')) {
                $attrs['profile'] = $request->string('profile')->toString();
            }
            if ($request->filled('server')) {
                $attrs['server'] = $request->string('server')->toString();
            }
            if ($request->filled('comment')) {
                $attrs['comment'] = $request->string('comment')->toString();
            }
            $client->command('/ip/hotspot/user/set', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'Hotspot user berhasil diperbarui.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotUserDestroy(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/ip/hotspot/user/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'Hotspot user berhasil dihapus.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotIpBindingStore(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $macAddress = trim($request->string('mac-address')->toString());
        if ($macAddress === '') {
            return response()->json(['error' => 'MAC Address wajib diisi.'], 422);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['mac-address' => $macAddress, 'type' => 'bypassed'];
            if ($request->filled('address')) {
                $attrs['address'] = $request->string('address')->toString();
            }
            if ($request->filled('server')) {
                $attrs['server'] = $request->string('server')->toString();
            }
            if ($request->filled('comment')) {
                $attrs['comment'] = $request->string('comment')->toString();
            }
            if ($request->filled('type')) {
                $attrs['type'] = $request->string('type')->toString();
            }
            $client->command('/ip/hotspot/ip-binding/add', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'IP Binding berhasil ditambahkan.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotIpBindingUpdate(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['.id' => $id];
            if ($request->filled('mac-address')) {
                $attrs['mac-address'] = $request->string('mac-address')->toString();
            }
            if ($request->filled('address')) {
                $attrs['address'] = $request->string('address')->toString();
            }
            if ($request->filled('server')) {
                $attrs['server'] = $request->string('server')->toString();
            }
            if ($request->filled('comment')) {
                $attrs['comment'] = $request->string('comment')->toString();
            }
            if ($request->filled('type')) {
                $attrs['type'] = $request->string('type')->toString();
            }
            $client->command('/ip/hotspot/ip-binding/set', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'IP Binding berhasil diperbarui.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotIpBindingDestroy(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/ip/hotspot/ip-binding/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'IP Binding berhasil dihapus.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function hotspotActiveDisconnect(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/ip/hotspot/active/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'Session Hotspot berhasil didisconnect.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppoeServerStore(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $name = trim($request->string('name')->toString());
        $interface = trim($request->string('interface')->toString());
        if ($name === '' || $interface === '') {
            return response()->json(['error' => 'Name dan Interface wajib diisi.'], 422);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['name' => $name, 'interface' => $interface];
            if ($request->filled('service-name')) {
                $attrs['service-name'] = $request->string('service-name')->toString();
            }
            if ($request->filled('max-sessions')) {
                $attrs['max-sessions'] = $request->string('max-sessions')->toString();
            }
            if ($request->filled('keepalive-timeout')) {
                $attrs['keepalive-timeout'] = $request->string('keepalive-timeout')->toString();
            }
            if ($request->filled('default-profile')) {
                $attrs['default-profile'] = $request->string('default-profile')->toString();
            }
            $client->command('/interface/pppoe-server/server/add', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'PPPoE Server berhasil ditambahkan.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppoeServerUpdate(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $attrs = ['.id' => $id];
            if ($request->filled('interface')) {
                $attrs['interface'] = $request->string('interface')->toString();
            }
            if ($request->filled('service-name')) {
                $attrs['service-name'] = $request->string('service-name')->toString();
            }
            if ($request->filled('max-sessions')) {
                $attrs['max-sessions'] = $request->string('max-sessions')->toString();
            }
            if ($request->filled('keepalive-timeout')) {
                $attrs['keepalive-timeout'] = $request->string('keepalive-timeout')->toString();
            }
            if ($request->filled('default-profile')) {
                $attrs['default-profile'] = $request->string('default-profile')->toString();
            }
            $client->command('/interface/pppoe-server/server/set', $attrs);
            $client->disconnect();

            return response()->json(['message' => 'PPPoE Server berhasil diperbarui.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pppoeServerDestroy(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);
        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $client->command('/interface/pppoe-server/server/remove', ['.id' => $id]);
            $client->disconnect();

            return response()->json(['message' => 'PPPoE Server berhasil dihapus.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiDashboardTraffic(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connection = $this->resolveConnectionForUser($request->integer('connection_id'), $user);

        if (! $connection) {
            return response()->json(['error' => 'Router tidak ditemukan.'], 404);
        }

        $interface = $request->string('interface')->toString();
        if ($interface === '') {
            return response()->json(['error' => 'Interface wajib diisi.'], 422);
        }

        try {
            $client = new MikrotikApiClient($connection);
            $result = $client->command('/interface/monitor-traffic', [
                'interface' => $interface,
                'once' => '',
            ]);
            $client->disconnect();

            $row = $result['data'][0] ?? [];

            return response()->json([
                'tx' => (int) ($row['tx-bits-per-second'] ?? 0),
                'rx' => (int) ($row['rx-bits-per-second'] ?? 0),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function apiDashboardPayload(?MikrotikConnection $connection): array
    {
        if (! $connection) {
            return [
                'platform_vendor' => 'MikroTik',
                'platform_model' => 'Belum ada router',
                'routeros' => 'N/A',
                'cpu_type' => 'N/A',
                'cpu_cores' => 'N/A',
                'cpu_mhz' => 'N/A',
                'cpu_load' => 'N/A',
                'ram_free_percent' => 'N/A',
                'disk_free_percent' => 'N/A',
                'build_date' => 'N/A',
                'build_time' => 'N/A',
                'uptime' => 'N/A',
            ];
        }

        try {
            $client = new MikrotikApiClient($connection);
            $result = $client->command('/system/resource/print');
            $client->disconnect();

            $res = $result['data'][0] ?? [];

            $totalMem = (int) ($res['total-memory'] ?? 0);
            $freeMem = (int) ($res['free-memory'] ?? 0);
            $totalDisk = (int) ($res['total-hdd-space'] ?? 0);
            $freeDisk = (int) ($res['free-hdd-space'] ?? 0);

            $ramPercent = $totalMem > 0 ? ($freeMem / $totalMem) * 100 : null;
            $diskPercent = $totalDisk > 0 ? ($freeDisk / $totalDisk) * 100 : null;
            $cpuLoad = isset($res['cpu-load']) ? (float) $res['cpu-load'] : null;

            $version = $res['version'] ?? null;
            $routeros = $version ? 'ROS '.explode(' ', $version)[0] : 'N/A';

            $buildDate = $res['build-time'] ?? null;
            $buildDateFormatted = 'N/A';
            $buildTimeFormatted = 'N/A';
            if ($buildDate) {
                // ROS 7: "2025-02-06 09:10:24", ROS 6: "Feb/06/2025 09:10:24"
                $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $buildDate)
                    ?: \DateTime::createFromFormat('M/d/Y H:i:s', $buildDate);
                if ($parsed) {
                    $buildDateFormatted = $parsed->format('Y-m-d');
                    $buildTimeFormatted = $parsed->format('H:i:s');
                } else {
                    $buildDateFormatted = $buildDate;
                }
            }

            return [
                'platform_vendor' => $res['platform'] ?? 'MikroTik',
                'platform_model' => $connection->name,
                'routeros' => $routeros,
                'cpu_type' => $res['cpu'] ?? 'N/A',
                'cpu_cores' => ($res['cpu-count'] ?? '?').' core(s)',
                'cpu_mhz' => isset($res['cpu-frequency']) ? $res['cpu-frequency'].' MHz' : 'N/A',
                'cpu_load' => $cpuLoad !== null ? number_format($cpuLoad, 1, '.', '').'%' : 'N/A',
                'ram_free_percent' => $this->formatPercent($ramPercent),
                'disk_free_percent' => $this->formatPercent($diskPercent),
                'build_date' => $buildDateFormatted,
                'build_time' => $buildTimeFormatted,
                'uptime' => $res['uptime'] ?? 'N/A',
            ];
        } catch (\RuntimeException $e) {
            return [
                'platform_vendor' => 'MikroTik',
                'platform_model' => $connection->name,
                'routeros' => 'N/A',
                'cpu_type' => 'N/A',
                'cpu_cores' => 'N/A',
                'cpu_mhz' => 'N/A',
                'cpu_load' => 'N/A',
                'ram_free_percent' => 'N/A',
                'disk_free_percent' => 'N/A',
                'build_date' => 'N/A',
                'build_time' => 'N/A',
                'uptime' => 'N/A',
            ];
        }
    }
}
