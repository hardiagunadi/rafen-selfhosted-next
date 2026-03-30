<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWgPeerRequest;
use App\Http\Requests\UpdateWgPeerRequest;
use App\Models\MikrotikConnection;
use App\Models\WgPeer;
use App\Services\RadiusClientsSynchronizer;
use App\Services\WgPeerSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class WgSettingsController extends Controller
{
    public function index(): View
    {
        $user    = auth()->user();
        $peers   = WgPeer::query()->accessibleBy($user)->with('mikrotikConnection')->orderBy('name')->get();
        $routers = MikrotikConnection::query()->accessibleBy($user)->orderBy('name')->get();

        $configuredHost = (string) config('wg.host');
        $detectedIp     = $configuredHost !== '' ? null : $this->detectPublicIp();

        $serverPublicKey  = (string) config('wg.server_public_key');
        $serverPrivateKey = (string) config('wg.server_private_key');

        // If .env keys are missing, try to read from the key files on disk
        if ($serverPublicKey === '' || $serverPrivateKey === '') {
            [$serverPrivateKey, $serverPublicKey] = $this->resolveServerKeypair(
                $serverPrivateKey,
                $serverPublicKey,
            );
        }

        return view('settings.wg', [
            'wg' => [
                'host'              => $configuredHost,
                'server_ip'         => (string) config('wg.server_ip'),
                'server_address'    => (string) config('wg.server_address'),
                'server_public_key' => $serverPublicKey,
                'listen_port'       => (string) config('wg.listen_port'),
                'interface'         => (string) config('wg.interface'),
                'pool_start'        => (string) config('wg.pool_start'),
                'pool_end'          => (string) config('wg.pool_end'),
            ],
            'detectedIp'      => $detectedIp,
            'peers'           => $peers,
            'routers'         => $routers,
            'keyAutoDetected' => $serverPublicKey !== '' && (string) config('wg.server_public_key') === '',
        ]);
    }

    public function store(StoreWgPeerRequest $request, WgPeerSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        $data             = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        // Auto-generate keypair if not provided
        if (empty($data['public_key']) || empty($data['private_key'])) {
            [$privKey, $pubKey]  = $this->generateKeypair();
            $data['private_key'] = $privKey;
            $data['public_key']  = $pubKey;
        }

        if (empty($data['vpn_ip'])) {
            $data['vpn_ip'] = $this->allocateVpnIp();
        }

        if (! $data['vpn_ip']) {
            if ($request->wantsJson()) {
                return response()->json(
                    ['error' => 'IP VPN pool sudah habis atau belum diatur (WG_POOL_START/WG_POOL_END).'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            return redirect()->route('settings.wg')->with('error', 'IP VPN pool sudah habis.');
        }

        $data['owner_id'] = auth()->user()->effectiveOwnerId();
        $peer = WgPeer::create($data);
        $peer->load('mikrotikConnection');

        try {
            $synchronizer->syncAll(WgPeer::query()->get());
            $peer->update(['last_synced_at' => now()]);
            $peer->refresh();
        } catch (Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'Peer tersimpan, tetapi sync wg0.conf gagal: ' . $exception->getMessage(),
                    'peer'    => $this->peerPayload($peer),
                ]);
            }
            return redirect()->route('settings.wg')
                ->with('error', 'Peer tersimpan, tetapi sync wg0.conf gagal: ' . $exception->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'WireGuard peer berhasil dibuat.',
                'peer'   => $this->peerPayload($peer),
            ]);
        }
        return redirect()->route('settings.wg')->with('status', 'WireGuard peer berhasil dibuat.');
    }

    public function update(
        UpdateWgPeerRequest $request,
        WgPeer $wgPeer,
        WgPeerSynchronizer $synchronizer
    ): JsonResponse|RedirectResponse {
        $this->authorizeWgPeer($wgPeer);
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $wgPeer->is_active);

        $wgPeer->update($data);
        $wgPeer->load('mikrotikConnection');

        try {
            $synchronizer->syncAll(WgPeer::query()->get());
            $wgPeer->update(['last_synced_at' => now()]);
            $wgPeer->refresh();
        } catch (Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'Peer diperbarui, tetapi sync wg0.conf gagal: ' . $exception->getMessage(),
                    'peer'    => $this->peerPayload($wgPeer),
                ]);
            }
            return redirect()->route('settings.wg')
                ->with('error', 'Peer diperbarui, tetapi sync wg0.conf gagal: ' . $exception->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'WireGuard peer diperbarui.',
                'peer'   => $this->peerPayload($wgPeer),
            ]);
        }
        return redirect()->route('settings.wg')->with('status', 'WireGuard peer diperbarui.');
    }

    public function destroy(WgPeer $wgPeer, WgPeerSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        $this->authorizeWgPeer($wgPeer);
        $wgPeer->delete();

        try {
            $synchronizer->syncAll(WgPeer::query()->get());
        } catch (Throwable) {
            // Best-effort; peer sudah dihapus dari DB
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'WireGuard peer dihapus.']);
        }
        return redirect()->route('settings.wg')->with('status', 'WireGuard peer dihapus.');
    }

    public function sync(WgPeer $wgPeer, WgPeerSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        $this->authorizeWgPeer($wgPeer);
        try {
            $synchronizer->syncAll(WgPeer::query()->get());
            $wgPeer->update(['last_synced_at' => now()]);
            $wgPeer->refresh();

            if (request()->wantsJson()) {
                return response()->json([
                    'status'         => 'Sinkronisasi wg0.conf berhasil.',
                    'last_synced_at' => $wgPeer->last_synced_at?->format('Y-m-d H:i:s'),
                ]);
            }
            return redirect()->route('settings.wg')->with('status', 'Sinkronisasi wg0.conf berhasil.');
        } catch (Throwable $exception) {
            if (request()->wantsJson()) {
                return response()->json(
                    ['error' => 'Sinkronisasi gagal: ' . $exception->getMessage()],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            return redirect()->route('settings.wg')
                ->with('error', 'Sinkronisasi gagal: ' . $exception->getMessage());
        }
    }

    public function keygen(WgPeer $wgPeer, WgPeerSynchronizer $synchronizer): JsonResponse
    {
        $this->authorizeWgPeer($wgPeer);
        [$privKey, $pubKey] = $this->generateKeypair();

        $wgPeer->update([
            'private_key' => $privKey,
            'public_key'  => $pubKey,
        ]);
        $wgPeer->load('mikrotikConnection');

        try {
            $synchronizer->syncAll(WgPeer::query()->get());
            $wgPeer->update(['last_synced_at' => now()]);
            $wgPeer->refresh();
        } catch (Throwable $exception) {
            return response()->json([
                'warning' => 'Keypair diperbarui, tetapi sync wg0.conf gagal: ' . $exception->getMessage(),
                'peer'    => $this->peerPayload($wgPeer),
            ]);
        }

        return response()->json([
            'status' => 'Keypair baru berhasil di-generate dan disinkronkan.',
            'peer'   => $this->peerPayload($wgPeer),
        ]);
    }

    public function createNas(WgPeer $wgPeer, RadiusClientsSynchronizer $radiusSynchronizer): JsonResponse|RedirectResponse
    {
        $this->authorizeWgPeer($wgPeer);
        if ($wgPeer->mikrotik_connection_id !== null) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Peer ini sudah terhubung ke router NAS.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            return redirect()->route('settings.wg')->with('error', 'Peer ini sudah terhubung ke router NAS.');
        }

        if (! $wgPeer->vpn_ip) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Peer belum memiliki IP VPN.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            return redirect()->route('settings.wg')->with('error', 'Peer belum memiliki IP VPN.');
        }

        $secret   = Str::random(17);
        $apiUser  = 'TMD-' . Str::upper(Str::random(6));

        $nas = MikrotikConnection::create([
            'owner_id'      => auth()->id(),
            'name'          => $wgPeer->name,
            'host'          => $wgPeer->vpn_ip,
            'api_port'      => 2266,
            'api_ssl_port'  => 2267,
            'use_ssl'       => false,
            'username'      => $apiUser,
            'password'      => $secret,
            'radius_secret' => $secret,
            'ros_version'   => '7',
            'api_timeout'   => 10,
            'is_active'     => true,
        ]);

        $wgPeer->update(['mikrotik_connection_id' => $nas->id]);
        $wgPeer->load('mikrotikConnection');

        try {
            $radiusSynchronizer->sync();
        } catch (Throwable) {
            // Non-fatal — NAS sudah dibuat, sync RADIUS bisa dijalankan manual
        }

        if (request()->wantsJson()) {
            return response()->json([
                'status' => 'NAS berhasil dibuat dengan IP VPN ' . $wgPeer->vpn_ip . '.',
                'peer'   => $this->peerPayload($wgPeer),
                'nas_id' => $nas->id,
                'edit_url' => route('mikrotik-connections.edit', $nas),
            ]);
        }

        return redirect()->route('mikrotik-connections.edit', $nas)
            ->with('status', 'NAS berhasil dibuat dengan IP VPN ' . $wgPeer->vpn_ip . '. Lengkapi konfigurasi router di sini.');
    }

    public function ping(): JsonResponse
    {
        $ip = request()->query('ip', '');

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json(['error' => 'IP tidak valid.'], 422);
        }

        $output = [];
        $escaped = escapeshellarg($ip);
        exec("ping -c 4 -W 2 {$escaped} 2>&1", $output, $code);

        return response()->json([
            'ip'     => $ip,
            'output' => implode("\n", $output),
            'alive'  => $code === 0,
        ]);
    }

    public function saveServerKeys(): RedirectResponse
    {
        $configuredPub  = (string) config('wg.server_public_key');
        $configuredPriv = (string) config('wg.server_private_key');

        // Resolve from disk if not already in .env
        [$resolvedPriv, $resolvedPub] = $this->resolveServerKeypair($configuredPriv, $configuredPub);

        if ($resolvedPub === '') {
            return redirect()->route('settings.wg')
                ->with('error', 'Server public key tidak ditemukan. Pastikan WireGuard sudah terinstall.');
        }

        $envPath = base_path('.env');
        $env     = file_get_contents($envPath);

        foreach ([
            'WG_SERVER_PUBLIC_KEY'  => $resolvedPub,
            'WG_SERVER_PRIVATE_KEY' => $resolvedPriv,
        ] as $key => $value) {
            if ($value === '') {
                continue;
            }
            if (preg_match('/^' . $key . '=.*$/m', $env)) {
                $env = preg_replace('/^' . $key . '=.*$/m', $key . '=' . $value, $env);
            } else {
                $env .= PHP_EOL . $key . '=' . $value;
            }
        }

        file_put_contents($envPath, $env);

        return redirect()->route('settings.wg')
            ->with('status', 'Server keypair berhasil disimpan ke .env.');
    }

    public function saveHost(): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak. Hanya administrator yang dapat menyimpan WG_HOST.');

        $ip = $this->detectPublicIp();

        if ($ip === null || $ip === '') {
            return redirect()->route('settings.wg')
                ->with('error', 'Gagal mendeteksi IP publik server. Isi WG_HOST di .env secara manual.');
        }

        $envPath = base_path('.env');
        $env     = file_get_contents($envPath);

        if (preg_match('/^WG_HOST=.*$/m', $env)) {
            $env = preg_replace('/^WG_HOST=.*$/m', 'WG_HOST=' . $ip, $env);
        } else {
            $env .= PHP_EOL . 'WG_HOST=' . $ip;
        }

        file_put_contents($envPath, $env);

        return redirect()->route('settings.wg')
            ->with('status', 'WG_HOST berhasil disimpan ke .env: ' . $ip);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function authorizeWgPeer(WgPeer $peer): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($peer->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
    }

    /**
     * Try to resolve the server keypair from disk files, then auto-generate if needed.
     * Returns [privateKey, publicKey].
     *
     * @return array{string, string}
     */
    private function resolveServerKeypair(string $privFromEnv, string $pubFromEnv): array
    {
        $keyDir  = '/etc/wireguard';
        $privFile = $keyDir . '/server_private.key';
        $pubFile  = $keyDir . '/server_public.key';

        $priv = $privFromEnv;
        $pub  = $pubFromEnv;

        // Try reading from disk files (written by install-wg.sh)
        if ($priv === '' && is_readable($privFile)) {
            $priv = trim((string) @file_get_contents($privFile));
        }
        if ($pub === '' && is_readable($pubFile)) {
            $pub = trim((string) @file_get_contents($pubFile));
        }

        // If private key found on disk but public key is missing, derive it
        if ($priv !== '' && $pub === '') {
            $derived = trim((string) shell_exec('echo ' . escapeshellarg($priv) . ' | wg pubkey 2>/dev/null'));
            if ($derived !== '') {
                $pub = $derived;
                // Persist public key file so we don't have to derive again
                @file_put_contents($pubFile, $pub);
            }
        }

        // Last resort: auto-generate a new server keypair and write to disk
        if ($priv === '') {
            $priv = trim((string) shell_exec('wg genkey 2>/dev/null'));
            if ($priv !== '') {
                $pub = trim((string) shell_exec('echo ' . escapeshellarg($priv) . ' | wg pubkey 2>/dev/null'));

                if (is_dir($keyDir) && is_writable($keyDir)) {
                    @file_put_contents($privFile, $priv);
                    @chmod($privFile, 0600);
                    if ($pub !== '') {
                        @file_put_contents($pubFile, $pub);
                        @chmod($pubFile, 0644);
                    }
                }
            }
        }

        return [$priv, $pub];
    }

    private function peerPayload(WgPeer $peer): array
    {
        return [
            'id'                  => $peer->id,
            'name'                => $peer->name,
            'public_key'          => $peer->public_key,
            'private_key'         => $peer->private_key,
            'preshared_key'       => $peer->preshared_key,
            'vpn_ip'              => $peer->vpn_ip,
            'is_active'           => $peer->is_active,
            'last_synced_at'      => $peer->last_synced_at?->format('Y-m-d H:i:s'),
            'mikrotik_connection' => $peer->mikrotikConnection?->name,
            'radius_secret'       => $peer->mikrotikConnection?->radius_secret ?? '',
            'update_url'          => route('settings.wg.peers.update', $peer),
            'destroy_url'         => route('settings.wg.peers.destroy', $peer),
            'sync_url'            => route('settings.wg.peers.sync', $peer),
            'create_nas_url'      => route('settings.wg.peers.create-nas', $peer),
            'keygen_url'          => route('settings.wg.peers.keygen', $peer),
        ];
    }

    /**
     * Generate a WireGuard keypair via wg CLI.
     *
     * @return array{string, string}  [privateKey, publicKey]
     */
    private function generateKeypair(): array
    {
        $privKey = trim((string) shell_exec('wg genkey 2>/dev/null'));
        if ($privKey === '') {
            // Fallback for dev environments without wg installed
            $privKey = base64_encode(random_bytes(32));
        }

        $pubKey = trim((string) shell_exec('echo ' . escapeshellarg($privKey) . ' | wg pubkey 2>/dev/null'));
        if ($pubKey === '') {
            $pubKey = base64_encode(random_bytes(32));
        }

        return [$privKey, $pubKey];
    }

    private function allocateVpnIp(): ?string
    {
        $startLong = $this->ipToLong((string) config('wg.pool_start'));
        $endLong   = $this->ipToLong((string) config('wg.pool_end'));

        if ($startLong === null || $endLong === null || $startLong > $endLong) {
            return null;
        }

        $used = WgPeer::query()
            ->whereNotNull('vpn_ip')
            ->pluck('vpn_ip')
            ->map(fn (string $ip) => $this->ipToLong($ip))
            ->filter(fn (?int $l) => $l !== null)
            ->unique()
            ->all();

        $usedLookup = array_fill_keys($used, true);

        // Exclude server IP from pool
        $serverLong = $this->ipToLong((string) config('wg.server_ip'));
        if ($serverLong !== null) {
            $usedLookup[$serverLong] = true;
        }

        for ($current = $startLong; $current <= $endLong; $current++) {
            if (! isset($usedLookup[$current])) {
                return $this->longToIp($current);
            }
        }

        return null;
    }

    private function detectPublicIp(): ?string
    {
        $cacheFile = storage_path('app/detected_public_ip.txt');
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $cached = trim((string) file_get_contents($cacheFile));
            if ($cached !== '') {
                return $cached;
            }
        }

        $ip = null;

        $output = shell_exec('curl -s --max-time 3 https://api.ipify.org 2>/dev/null');
        if ($output !== null) {
            $candidate = trim($output);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip = $candidate;
            }
        }

        if ($ip === null) {
            $output = shell_exec('hostname -I 2>/dev/null');
            if ($output !== null) {
                foreach (explode(' ', trim($output)) as $candidate) {
                    $candidate = trim($candidate);
                    if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $ip = $candidate;
                        break;
                    }
                }
            }
        }

        if ($ip !== null) {
            @file_put_contents($cacheFile, $ip);
        }

        return $ip;
    }

    private function ipToLong(string $ip): ?int
    {
        $long = ip2long($ip);
        if ($long === false) {
            return null;
        }
        return $long < 0 ? $long + (2 ** 32) : $long;
    }

    private function longToIp(int $long): string
    {
        if ($long > 2147483647) {
            $long -= 2 ** 32;
        }
        return long2ip($long);
    }
}
