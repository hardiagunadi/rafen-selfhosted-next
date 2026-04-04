<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * IsolirSynchronizer
 *
 * Mengurus proses isolir pelanggan PPP:
 * 1. Set radreply agar user mendapat IP dari pool isolir + PPP profile isolir
 * 2. Auto-setup Mikrotik (pool, PPP profile, firewall, NAT) jika belum dilakukan
 * 3. Kick/drop sesi aktif user di Mikrotik agar reconnect dengan profile isolir
 *
 * Mekanisme redirect halaman isolir (TANPA web proxy):
 * - User isolir mendapat IP dari pool-isolir (mis: 10.99.0.x)
 * - Firewall Mikrotik DNAT port 80+443 dari pool ini ke IP server Rafen
 * - User membuka browser → semua HTTP/HTTPS masuk ke halaman /isolir/{slug}
 * - Semua traffic lain ke internet di-DROP
 *
 * Catatan keamanan HTTPS:
 * - Rule NAT mengarahkan 443 ke port 443 server Rafen
 * - Jika server tidak melayani HTTPS pada host/IP tujuan, akses HTTPS bisa gagal
 * - Probe captive portal berbasis HTTP tetap diarahkan ke halaman isolir
 */
class IsolirSynchronizer
{
    private const PPPOE_PARENT_QUEUE_NAME = '0. PPPOe Pelanggan';

    private const HOTSPOT_PARENT_QUEUE_NAME = '1. Hotspot';

    private const EXPIRED_PARENT_QUEUE_NAME = '2. Expired User';

    private const LOOPBACK_INTERFACE_NAME = 'rafen-isolir-loopback';

    private const LOOPBACK_INTERFACE_COMMENT = 'Rafen: loopback isolir';

    private const GATEWAY_ADDRESS_COMMENT = 'Rafen: gateway isolir';

    private const LOCAL_ISOLIR_SECRET_COMMENT = 'Rafen: isolir fallback';

    // Attribute vendor Mikrotik untuk menentukan PPP profile via RADIUS
    private const MIKROTIK_GROUP_ATTR = 'Mikrotik-Group';

    private const FRAMED_POOL_ATTR = 'Framed-Pool';

    /**
     * Aktifkan isolir untuk user PPP.
     * Dipanggil saat status_akun berubah ke 'isolir'.
     */
    public function isolate(PppUser $user): void
    {
        if (! $user->username) {
            return;
        }

        // 1. Tentukan MikrotikConnection yang relevan untuk user ini
        $connection = $this->resolveConnection($user);

        // 2. Auto-setup Mikrotik jika belum pernah dilakukan
        if ($connection && ! $connection->isolir_setup_done) {
            $this->setupMikrotik($connection, $user->owner_id);
        }

        $profileName = $connection?->isolir_profile_name ?: 'isolir-pppoe';
        $poolName = $connection?->isolir_pool_name ?: 'pool-isolir';
        $rateLimit = $connection?->isolir_rate_limit ?: '128k/128k';

        if ($connection) {
            $this->ensureIsolirProfileParentQueue($connection, $profileName, $poolName, $rateLimit);
            $this->ensureLocalIsolirSecret($connection, $user, $profileName);
        }

        // 3. Hapus semua radreply lama (IP statis / pool normal)
        DB::table('radreply')
            ->where('username', $user->username)
            ->whereIn('attribute', [
                'Framed-IP-Address',
                'Framed-IP-Netmask',
                self::FRAMED_POOL_ATTR,
                self::MIKROTIK_GROUP_ATTR,
                'Mikrotik-Queue-Parent-Name',
                'Mikrotik-Rate-Limit',
            ])
            ->delete();

        // 4. Set radreply isolir: profile, pool, parent queue, rate-limit
        DB::table('radreply')->insert([
            ['username' => $user->username, 'attribute' => self::MIKROTIK_GROUP_ATTR, 'op' => ':=', 'value' => $profileName],
            ['username' => $user->username, 'attribute' => self::FRAMED_POOL_ATTR,    'op' => ':=', 'value' => $poolName],
            ['username' => $user->username, 'attribute' => 'Mikrotik-Queue-Parent-Name', 'op' => ':=', 'value' => self::EXPIRED_PARENT_QUEUE_NAME],
            ['username' => $user->username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => ':=', 'value' => $rateLimit],
        ]);

        // 5. Pertahankan radcheck (password tetap) agar user bisa reconnect
        //    Jika radcheck tidak ada, buat ulang
        $exists = DB::table('radcheck')
            ->where('username', $user->username)
            ->where('attribute', 'Cleartext-Password')
            ->exists();

        if (! $exists && $user->ppp_password) {
            DB::table('radcheck')->insert([
                'username' => $user->username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $user->ppp_password,
            ]);
        }

        // 6. Kick sesi aktif → user reconnect, mendapat profile isolir
        if ($connection) {
            $this->kickActiveSessions($connection, $user->username);
        }
    }

    /**
     * Cabut isolir — restore ke profile normal.
     * Dipanggil saat status_akun berubah dari 'isolir' ke 'enable'.
     * RadiusReplySynchronizer::syncSingleUser() menangani restore radreply normal,
     * method ini hanya kick sesi isolir agar user reconnect dengan profile asli.
     */
    public function deisolate(PppUser $user): void
    {
        if (! $user->username) {
            return;
        }

        $connection = $this->resolveConnection($user);

        if ($connection) {
            $this->removeLocalIsolirSecret($connection, $user->username);
            $this->kickActiveSessions($connection, $user->username);
        }
    }

    /**
     * Setup pool isolir, PPP profile, firewall, dan NAT di Mikrotik.
     * Dipanggil sekali per MikrotikConnection saat user pertama diisolir.
     *
     * @throws RuntimeException jika koneksi ke Mikrotik gagal
     */
    public function setupMikrotik(MikrotikConnection $connection, ?int $ownerId = null): void
    {
        $client = new MikrotikApiClient($connection);

        try {
            $client->connect();

            $pool = $connection->isolir_pool_name ?: 'pool-isolir';
            $range = $connection->isolir_pool_range ?: '10.99.0.2-10.99.0.254';
            $gateway = $connection->isolir_gateway ?: '10.99.0.1';
            $profile = $connection->isolir_profile_name ?: 'isolir-pppoe';
            $rate = $connection->isolir_rate_limit ?: '128k/128k';

            // Ambil URL halaman isolir dari settings tenant atau dari field isolir_url
            $isolirUrl = $this->resolveIsolirUrl($connection, $ownerId);

            // Tentukan subnet dari gateway (mis: 10.99.0.1 → 10.99.0.0/24)
            $subnet = $this->gatewayToSubnet($gateway);

            // -- 1. IP Pool --
            $this->ensureIpPool($client, $pool, $range);

            // -- 2. IP Address gateway isolir pada interface loopback/bridge --
            $this->ensureGatewayAddress($client, $gateway, $subnet);

            // -- 3. Parent Queues (PPPoE, Hotspot, Expired User) --
            $this->ensureParentQueues($client, $connection, $subnet, $rate);

            // -- 4. PPP Profile isolir --
            $this->ensurePppProfile($client, $profile, $gateway, $pool, $rate);

            // -- 5. Firewall filter: DROP semua dari subnet isolir KECUALI DNS + HTTP/HTTPS --
            $this->ensureFirewallFilters($client, $subnet);

            // -- 6. NAT DNAT: redirect HTTP + HTTPS dari subnet isolir ke server Rafen --
            if ($isolirUrl) {
                $this->ensureNatRules($client, $subnet, $isolirUrl);
            }

            $client->disconnect();

            // Tandai setup sudah selesai
            $connection->update([
                'isolir_setup_done' => true,
                'isolir_setup_at' => now(),
            ]);

        } catch (RuntimeException $e) {
            $client->disconnect();
            Log::error("IsolirSynchronizer: setup Mikrotik gagal untuk {$connection->name}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Reset setup (hapus semua rule isolir di Mikrotik) dan tandai ulang.
     * Berguna jika rule di router perlu di-re-apply.
     */
    public function resetSetup(MikrotikConnection $connection): void
    {
        $connection->update([
            'isolir_setup_done' => false,
            'isolir_setup_at' => null,
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function resolveConnection(PppUser $user): ?MikrotikConnection
    {
        if (! $user->owner_id) {
            return null;
        }

        return MikrotikConnection::query()
            ->where('owner_id', $user->owner_id)
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->first();
    }

    private function resolveIsolirUrl(MikrotikConnection $connection, ?int $ownerId): ?string
    {
        // Prioritas: isolir_url di NAS → URL halaman Rafen per tenant
        if ($connection->isolir_url) {
            return $connection->isolir_url;
        }

        if ($ownerId) {
            $settings = TenantSettings::getOrCreate($ownerId);
            $appUrl = rtrim(config('app.url', ''), '/');
            $host = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
            $port = parse_url($appUrl, PHP_URL_PORT);

            return $port ? "{$host}:{$port}" : $host;
        }

        return null;
    }

    private function tenantSlug(TenantSettings $settings): string
    {
        return (string) $settings->user_id;
    }

    /**
     * Hitung subnet /24 dari IP gateway.
     * 10.99.0.1 → 10.99.0.0/24
     */
    private function gatewayToSubnet(string $gateway): string
    {
        $parts = explode('.', $gateway);
        if (count($parts) === 4) {
            $parts[3] = '0';

            return implode('.', $parts).'/24';
        }

        return '10.99.0.0/24';
    }

    /**
     * Kelola ketiga parent queue: PPPoE, Hotspot, dan Expired User.
     * Dipanggil dari setupMikrotik() setelah PPP profile dibuat.
     */
    private function ensureParentQueues(
        MikrotikApiClient $client,
        MikrotikConnection $connection,
        string $isolirSubnet,
        string $rateLimit
    ): void {
        $hasPppoeParentQueue = $this->queueExists($client, self::PPPOE_PARENT_QUEUE_NAME);

        // -- 0. PPPOe Pelanggan: target dari profile_groups PPPoE milik tenant --
        $pppoeTarget = $this->resolvePppoeTarget($connection);
        if ($pppoeTarget) {
            $this->ensureSimpleQueue($client, self::PPPOE_PARENT_QUEUE_NAME, $pppoeTarget, '0/0',
                'rafen: parent queue pelanggan PPPoE');
            $hasPppoeParentQueue = true;
        }

        // -- 1. Hotspot: target dari kolom hotspot_subnet per-router --
        if ($connection->hotspot_subnet) {
            $this->ensureSimpleQueue($client, self::HOTSPOT_PARENT_QUEUE_NAME, $connection->hotspot_subnet, '0/0',
                'rafen: parent queue hotspot');
        }

        // -- 2. Expired User: target subnet isolir, throttle sesuai isolir_rate_limit --
        $this->ensureSimpleQueue(
            $client,
            self::EXPIRED_PARENT_QUEUE_NAME,
            $isolirSubnet,
            $rateLimit,
            'rafen-isolir: throttle pelanggan jatuh tempo',
            $hasPppoeParentQueue ? self::PPPOE_PARENT_QUEUE_NAME : null
        );
    }

    /**
     * Buat atau update simple queue berdasarkan nama.
     * Jika sudah ada tapi target/max-limit berbeda → update.
     * Jika belum ada → buat baru.
     */
    private function ensureSimpleQueue(
        MikrotikApiClient $client,
        string $name,
        string $target,
        string $maxLimit,
        string $comment,
        ?string $parent = null
    ): void {
        $existing = $client->command('/queue/simple/print', [], ['name' => $name]);

        if (! empty($existing['data'])) {
            $entry = $existing['data'][0];
            $currentParent = (string) ($entry['parent'] ?? '');
            $desiredParent = $parent ?? '';
            $needsUpdate = ($entry['target'] ?? '') !== $target
                         || ($entry['max-limit'] ?? '') !== $maxLimit
                         || ($entry['comment'] ?? '') !== $comment
                         || $currentParent !== $desiredParent;

            if ($needsUpdate) {
                $payload = [
                    '.id' => $entry['.id'],
                    'target' => $target,
                    'max-limit' => $maxLimit,
                    'comment' => $comment,
                ];

                if ($parent !== null) {
                    $payload['parent'] = $parent;
                } elseif ($currentParent !== '') {
                    $payload['parent'] = 'none';
                }

                $client->command('/queue/simple/set', $payload);
            }

            return;
        }

        $payload = [
            'name' => $name,
            'target' => $target,
            'max-limit' => $maxLimit,
            'comment' => $comment,
        ];

        if ($parent !== null) {
            $payload['parent'] = $parent;
        }

        $client->command('/queue/simple/add', $payload);
    }

    /**
     * Hitung target queue PPPoE dari profile_groups tenant yang punya parent_queue PPPoE.
     * Mengembalikan string subnet dipisah koma, mis: "192.168.22.0/24,192.168.32.0/24"
     */
    private function resolvePppoeTarget(MikrotikConnection $connection): ?string
    {
        $groups = ProfileGroup::query()
            ->where('owner_id', $connection->owner_id)
            ->where('parent_queue', '0. PPPOe Pelanggan')
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->where('ip_address', '!=', '-')
            ->whereNotNull('netmask')
            ->get(['ip_address', 'netmask']);

        $subnets = $groups->map(function ($g) {
            $netmask = (int) $g->netmask;
            $mask = ~((1 << (32 - $netmask)) - 1);
            $network = long2ip(ip2long($g->ip_address) & $mask);

            return "{$network}/{$netmask}";
        })->unique()->sort()->values();

        return $subnets->isNotEmpty() ? $subnets->implode(',') : null;
    }

    private function queueExists(MikrotikApiClient $client, string $name): bool
    {
        $existing = $client->command('/queue/simple/print', [], ['name' => $name]);

        return ! empty($existing['data']);
    }

    private function ensureIsolirProfileParentQueue(
        MikrotikConnection $connection,
        string $profileName,
        string $poolName,
        string $rateLimit
    ): void {
        $client = new MikrotikApiClient($connection);

        try {
            $client->connect();

            $gateway = $connection->isolir_gateway ?: '10.99.0.1';
            $subnet = $this->gatewayToSubnet($gateway);

            if (! $this->queueExists($client, self::EXPIRED_PARENT_QUEUE_NAME)) {
                $this->ensureSimpleQueue(
                    $client,
                    self::EXPIRED_PARENT_QUEUE_NAME,
                    $subnet,
                    $rateLimit,
                    'rafen-isolir: throttle pelanggan jatuh tempo'
                );
            }

            $this->ensurePppProfile($client, $profileName, $gateway, $poolName, $rateLimit);
        } catch (RuntimeException $e) {
            Log::warning("IsolirSynchronizer: gagal sinkron parent queue profile isolir di {$connection->name}: {$e->getMessage()}");
        } finally {
            $client->disconnect();
        }
    }

    private function ensureLocalIsolirSecret(MikrotikConnection $connection, PppUser $user, string $profileName): void
    {
        if (! $user->username || ! $user->ppp_password) {
            return;
        }

        $client = new MikrotikApiClient($connection);

        try {
            $client->connect();

            $existing = $client->command('/ppp/secret/print', [], ['name' => $user->username]);
            $payload = [
                'password' => $user->ppp_password,
                'profile' => $profileName,
                'service' => 'pppoe',
                'comment' => self::LOCAL_ISOLIR_SECRET_COMMENT,
            ];

            if (! empty($existing['data'])) {
                $entry = $existing['data'][0];
                $updatePayload = ['.id' => $entry['.id']];
                $needsUpdate = false;

                foreach ($payload as $field => $value) {
                    if (($entry[$field] ?? '') !== $value) {
                        $updatePayload[$field] = $value;
                        $needsUpdate = true;
                    }
                }

                if ($needsUpdate) {
                    $client->command('/ppp/secret/set', $updatePayload);
                }

                return;
            }

            $client->command('/ppp/secret/add', [
                'name' => $user->username,
                ...$payload,
            ]);
        } catch (RuntimeException $e) {
            Log::warning("IsolirSynchronizer: gagal sinkron secret fallback {$user->username} di {$connection->name}: {$e->getMessage()}");
        } finally {
            $client->disconnect();
        }
    }

    private function removeLocalIsolirSecret(MikrotikConnection $connection, string $username): void
    {
        if ($username === '') {
            return;
        }

        $client = new MikrotikApiClient($connection);

        try {
            $client->connect();

            $secrets = $client->command('/ppp/secret/print', [], ['name' => $username]);
            foreach ($secrets['data'] as $entry) {
                $comment = (string) ($entry['comment'] ?? '');
                if ($comment !== self::LOCAL_ISOLIR_SECRET_COMMENT) {
                    continue;
                }

                if (isset($entry['.id'])) {
                    $client->command('/ppp/secret/remove', ['.id' => $entry['.id']]);
                }
            }
        } catch (RuntimeException $e) {
            Log::warning("IsolirSynchronizer: gagal menghapus secret fallback {$username} di {$connection->name}: {$e->getMessage()}");
        } finally {
            $client->disconnect();
        }
    }

    private function ensureIpPool(MikrotikApiClient $client, string $poolName, string $ranges): void
    {
        $existing = $client->command('/ip/pool/print', [], ['name' => $poolName]);
        if (! empty($existing['data'])) {
            return; // sudah ada
        }

        $client->command('/ip/pool/add', [
            'name' => $poolName,
            'ranges' => $ranges,
            'comment' => 'Rafen: pool isolir pelanggan',
        ]);
    }

    private function ensurePppProfile(
        MikrotikApiClient $client,
        string $profileName,
        string $gateway,
        string $poolName,
        string $rateLimit
    ): void {
        $existing = $client->command('/ppp/profile/print', [], ['name' => $profileName]);

        $payload = [
            'local-address' => $gateway,
            'remote-address' => $poolName,
            'rate-limit' => $rateLimit,
            'parent-queue' => self::EXPIRED_PARENT_QUEUE_NAME,
            'comment' => 'Rafen: profile isolir - jangan hapus',
        ];

        if (! empty($existing['data'])) {
            $entry = $existing['data'][0];
            $updatePayload = ['.id' => $entry['.id']];
            $needsUpdate = false;

            foreach ($payload as $field => $value) {
                if (($entry[$field] ?? '') !== $value) {
                    $updatePayload[$field] = $value;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $client->command('/ppp/profile/set', $updatePayload);
            }

            return;
        }

        $client->command('/ppp/profile/add', [
            'name' => $profileName,
            ...$payload,
        ]);
    }

    private function ensureGatewayAddress(MikrotikApiClient $client, string $gateway, string $subnet): void
    {
        $interface = $this->ensureLoopbackInterface($client);
        $prefix = $this->subnetPrefix($subnet);
        $desiredAddress = "{$gateway}/{$prefix}";

        $addresses = $client->command('/ip/address/print');
        $sameGatewayEntries = [];
        $primaryStatic = null;

        foreach ($addresses['data'] as $entry) {
            $address = (string) ($entry['address'] ?? '');
            if (str_starts_with($address, "{$gateway}/")) {
                $sameGatewayEntries[] = $entry;

                if ($primaryStatic === null && ! $this->isDynamicAddress($entry)) {
                    $primaryStatic = $entry;
                }
            }
        }

        if ($primaryStatic !== null) {
            $payload = ['.id' => $primaryStatic['.id']];
            $needsUpdate = false;

            if (($primaryStatic['address'] ?? '') !== $desiredAddress) {
                $payload['address'] = $desiredAddress;
                $needsUpdate = true;
            }

            if (($primaryStatic['interface'] ?? '') !== $interface) {
                $payload['interface'] = $interface;
                $needsUpdate = true;
            }

            if (($primaryStatic['comment'] ?? '') !== self::GATEWAY_ADDRESS_COMMENT) {
                $payload['comment'] = self::GATEWAY_ADDRESS_COMMENT;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $client->command('/ip/address/set', $payload);
            }

            foreach ($sameGatewayEntries as $duplicate) {
                if (! isset($duplicate['.id'])) {
                    continue;
                }

                if (($duplicate['.id'] ?? null) === ($primaryStatic['.id'] ?? null)) {
                    continue;
                }

                if (! $this->isDynamicAddress($duplicate)) {
                    $client->command('/ip/address/remove', ['.id' => $duplicate['.id']]);
                }
            }

            return;
        }

        try {
            $client->command('/ip/address/add', [
                'address' => $desiredAddress,
                'interface' => $interface,
                'comment' => self::GATEWAY_ADDRESS_COMMENT,
            ]);
        } catch (RuntimeException $exception) {
            // Jika gateway address sudah ada sebagai dynamic address, lanjutkan tanpa gagal total setup.
            if (! str_contains(strtolower($exception->getMessage()), 'already have such address')) {
                throw $exception;
            }
        }
    }

    private function ensureLoopbackInterface(MikrotikApiClient $client): string
    {
        $existingByName = $client->command('/interface/bridge/print', [], ['name' => self::LOOPBACK_INTERFACE_NAME]);
        if (! empty($existingByName['data'])) {
            return self::LOOPBACK_INTERFACE_NAME;
        }

        $existingByComment = $client->command('/interface/bridge/print', [], ['comment' => self::LOOPBACK_INTERFACE_COMMENT]);
        if (! empty($existingByComment['data'])) {
            return (string) ($existingByComment['data'][0]['name'] ?? self::LOOPBACK_INTERFACE_NAME);
        }

        $client->command('/interface/bridge/add', [
            'name' => self::LOOPBACK_INTERFACE_NAME,
            'comment' => self::LOOPBACK_INTERFACE_COMMENT,
            'protocol-mode' => 'none',
        ]);

        return self::LOOPBACK_INTERFACE_NAME;
    }

    private function subnetPrefix(string $subnet): int
    {
        if (str_contains($subnet, '/')) {
            [, $prefix] = explode('/', $subnet, 2);

            return max(1, min(32, (int) $prefix));
        }

        return 24;
    }

    /**
     * @param  array<string, string>  $entry
     */
    private function isDynamicAddress(array $entry): bool
    {
        $dynamic = strtolower((string) ($entry['dynamic'] ?? ''));
        if ($dynamic !== '') {
            return $dynamic === 'true' || $dynamic === 'yes' || $dynamic === '1';
        }

        $flags = strtolower((string) ($entry['flags'] ?? ''));

        return str_contains($flags, 'd');
    }

    /**
     * Buat firewall filter untuk subnet isolir.
     * Urutan: izin DNS → izin HTTP/HTTPS → drop semua.
     * Dicek via comment unik agar tidak duplikat.
     */
    private function ensureFirewallFilters(MikrotikApiClient $client, string $subnet): void
    {
        $rules = [
            [
                'chain' => 'forward',
                'src-address' => $subnet,
                'protocol' => 'udp',
                'dst-port' => '53',
                'action' => 'accept',
                'comment' => 'rafen-isolir: izin DNS',
            ],
            [
                'chain' => 'forward',
                'src-address' => $subnet,
                'protocol' => 'tcp',
                'dst-port' => '53',
                'action' => 'accept',
                'comment' => 'rafen-isolir: izin DNS TCP',
            ],
            [
                'chain' => 'forward',
                'src-address' => $subnet,
                'protocol' => 'tcp',
                'dst-port' => '80,443',
                'action' => 'accept',
                'comment' => 'rafen-isolir: izin HTTP HTTPS',
            ],
            [
                'chain' => 'forward',
                'src-address' => $subnet,
                'action' => 'drop',
                'comment' => 'rafen-isolir: drop semua lainnya',
            ],
        ];

        foreach ($rules as $rule) {
            $comment = $rule['comment'];
            $existing = $client->command('/ip/firewall/filter/print', [], ['comment' => $comment]);
            if (! empty($existing['data'])) {
                continue;
            }
            $client->command('/ip/firewall/filter/add', $rule);
        }
    }

    /**
     * Buat NAT dst-nat: redirect HTTP (80) dan HTTPS (443) dari subnet isolir ke server Rafen.
     * HTTPS diredirect ke port 80 HTTP biasa (halaman isolir tidak perlu cert).
     *
     * @param  string  $isolirHost  Host tujuan (IP atau domain tanpa protocol)
     */
    private function ensureNatRules(MikrotikApiClient $client, string $subnet, string $isolirHost): void
    {
        // Pisahkan host dan port jika ada
        $host = $isolirHost;
        $port = '80';
        if (str_contains($isolirHost, ':')) {
            [$host, $port] = explode(':', $isolirHost, 2);
        }

        // MikroTik to-addresses harus IP address, bukan domain — resolve jika perlu
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($host);
            if ($resolved !== $host) {
                $host = $resolved;
            }
        }

        $rules = [
            [
                'chain' => 'dstnat',
                'src-address' => $subnet,
                'protocol' => 'tcp',
                'dst-port' => '80',
                'action' => 'dst-nat',
                'to-addresses' => $host,
                'to-ports' => $port,
                'comment' => 'rafen-isolir: redirect HTTP ke halaman isolir',
            ],
            [
                'chain' => 'dstnat',
                'src-address' => $subnet,
                'protocol' => 'tcp',
                'dst-port' => '443',
                'action' => 'dst-nat',
                'to-addresses' => $host,
                'to-ports' => '443',
                'comment' => 'rafen-isolir: redirect HTTPS ke halaman isolir',
            ],
        ];

        foreach ($rules as $rule) {
            $comment = $rule['comment'];
            $existing = $client->command('/ip/firewall/nat/print', [], ['comment' => $comment]);

            // Hapus rule lama jika ada agar to-ports dapat diperbarui
            foreach ($existing['data'] ?? [] as $entry) {
                if (isset($entry['.id'])) {
                    try {
                        $client->command('/ip/firewall/nat/remove', ['.id' => $entry['.id']]);
                    } catch (RuntimeException) {
                        // Abaikan jika rule sudah tidak ada
                    }
                }
            }

            $client->command('/ip/firewall/nat/add', $rule);
        }
    }

    /**
     * Kick sesi PPP aktif untuk username tertentu.
     * User akan disconnect dan reconnect → mendapat RADIUS reply baru (profile isolir/normal).
     */
    private function kickActiveSessions(MikrotikConnection $connection, string $username): void
    {
        try {
            $client = new MikrotikApiClient($connection);
            $client->connect();

            $sessions = $client->command('/ppp/active/print', [], ['name' => $username]);

            foreach ($sessions['data'] as $session) {
                $id = $session['.id'] ?? null;
                if ($id) {
                    try {
                        $client->command('/ppp/active/remove', ['.id' => $id]);
                    } catch (RuntimeException) {
                        // Sesi mungkin sudah putus, abaikan
                    }
                }
            }

            $client->disconnect();
        } catch (RuntimeException $e) {
            Log::warning("IsolirSynchronizer: gagal kick sesi {$username} di {$connection->name}: {$e->getMessage()}");
        }
    }
}
