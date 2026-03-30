<?php

namespace App\Services;

use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActiveSessionFetcher
{
    public function __construct(private MikrotikApiClient $client) {}

    /**
     * Sync active PPPoE sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     * Throws RuntimeException if router is unreachable — caller must handle.
     */
    public function syncPpp(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response  = $this->client->command('/ppp/active/print');
        $ifResponse = $this->client->command('/interface/print');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        // Index interface stats by username extracted from interface name "<pppoe-username>"
        $ifStats = [];
        foreach ($ifResponse['data'] ?? [] as $iface) {
            $name = $iface['name'] ?? '';
            if (str_starts_with($name, '<pppoe-') && str_ends_with($name, '>')) {
                $username = substr($name, 7, -1); // strip "<pppoe-" and ">"
                $ifStats[$username] = [
                    'bytes_in'  => isset($iface['rx-byte']) ? (int) $iface['rx-byte'] : null,
                    'bytes_out' => isset($iface['tx-byte']) ? (int) $iface['tx-byte'] : null,
                ];
            }
        }

        $this->upsertSessions($conn, 'pppoe', $sessions, function (array $row) use ($ifStats): array {
            $username = $row['name'] ?? '';
            $stats    = $ifStats[$username] ?? [];
            return [
                'username'     => $username,
                'ipv4_address' => $row['address'] ?? null,
                'uptime'       => $row['uptime'] ?? null,
                'caller_id'    => $row['caller-id'] ?? null,
                'server_name'  => null,
                'profile'      => $row['service'] ?? null,
                'bytes_in'     => $stats['bytes_in'] ?? null,
                'bytes_out'    => $stats['bytes_out'] ?? null,
            ];
        });

        return count($sessions);
    }

    /**
     * Sync active Hotspot sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     * Throws RuntimeException if router is unreachable — caller must handle.
     */
    public function syncHotspot(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response = $this->client->command('/ip/hotspot/active/print');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        $this->upsertSessions($conn, 'hotspot', $sessions, function (array $row): array {
            return [
                'username'     => $row['user'] ?? ($row['name'] ?? ''),
                'ipv4_address' => $row['address'] ?? null,
                'uptime'       => $row['uptime'] ?? null,
                'caller_id'    => $row['mac-address'] ?? null,
                'server_name'  => $row['server'] ?? null,
                'profile'      => null,
                'bytes_in'     => isset($row['bytes-in']) ? (int) $row['bytes-in'] : null,
                'bytes_out'    => isset($row['bytes-out']) ? (int) $row['bytes-out'] : null,
            ];
        });

        (new VoucherUsageTracker)->markUsedFromRadacct();

        $this->kickDisabledHotspotUsers($conn, $sessions);
        $this->ensureDefaultProfileSharedUsers($conn);

        return count($sessions);
    }

    /**
     * Ensure MikroTik hotspot default profile has shared-users >= max shared_users in our DB.
     * Mac-cookie logins bypass RADIUS and always use the default profile, so if shared-users=1
     * on default, users with multiple devices will be blocked on reconnect.
     */
    private function ensureDefaultProfileSharedUsers(MikrotikConnection $conn): void
    {
        $maxSharedUsers = HotspotProfile::where('owner_id', $conn->owner_id)->max('shared_users') ?? 1;

        if ($maxSharedUsers <= 1) {
            return;
        }

        try {
            $this->client->connect();
            $res = $this->client->command('/ip/hotspot/user/profile/print', [], ['default' => 'true']);
            $defaultProfile = $res['data'][0] ?? null;
            if (! $defaultProfile) {
                $this->client->disconnect();
                return;
            }

            $currentSharedUsers = (int) ($defaultProfile['shared-users'] ?? 1);
            if ($currentSharedUsers < $maxSharedUsers) {
                $this->client->command('/ip/hotspot/user/profile/set', [
                    '.id'          => $defaultProfile['.id'],
                    'shared-users' => (string) $maxSharedUsers,
                ]);
            }
            $this->client->disconnect();
        } catch (\RuntimeException) {
            // Non-fatal
        }
    }

    /**
     * Kick active hotspot sessions on MikroTik for users that are disabled/isolir in our DB.
     * Prevents "no more sessions are allowed" errors caused by zombie sessions
     * of users who were disabled while still connected.
     *
     * @param  array<int, array<string, string>>  $sessions  Active sessions from MikroTik
     */
    private function kickDisabledHotspotUsers(MikrotikConnection $conn, array $sessions): void
    {
        if (empty($sessions)) {
            return;
        }

        // Get usernames currently active on this MikroTik
        $activeUsernames = array_filter(array_map(
            fn ($row) => $row['user'] ?? ($row['name'] ?? ''),
            $sessions
        ));

        if (empty($activeUsernames)) {
            return;
        }

        // Find which of those active users are disabled in our DB
        $disabledUsernames = HotspotUser::whereIn('username', $activeUsernames)
            ->whereIn('status_akun', ['disable', 'isolir'])
            ->pluck('username')
            ->all();

        if (empty($disabledUsernames)) {
            return;
        }

        // Remove their sessions from MikroTik
        foreach ($sessions as $row) {
            $username = $row['user'] ?? ($row['name'] ?? '');
            if (! in_array($username, $disabledUsernames, true)) {
                continue;
            }

            $sessionId = $row['.id'] ?? null;
            if (! $sessionId) {
                continue;
            }

            try {
                $this->client->connect();
                $this->client->command('/ip/hotspot/active/remove', ['.id' => $sessionId]);
                $this->client->disconnect();
            } catch (\RuntimeException) {
                // Non-fatal: session may have already ended
            }
        }
    }

    /**
     * Mark all sessions for a router+service as inactive.
     * Called when the router is unreachable so the UI shows stale data correctly.
     */
    public function markAllInactive(MikrotikConnection $conn, string $service): void
    {
        $now = Carbon::now()->toDateTimeString();

        RadiusAccount::where('mikrotik_connection_id', $conn->id)
            ->where('service', $service)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    /**
     * @param  array<int, array<string, string>>  $sessions
     * @param  callable(array<string, string>): array<string, mixed>  $mapper
     */
    private function upsertSessions(
        MikrotikConnection $conn,
        string $service,
        array $sessions,
        callable $mapper
    ): void {
        $now = Carbon::now()->toDateTimeString();
        $activeUsernames = [];

        foreach ($sessions as $row) {
            $mapped = $mapper($row);
            $username = $mapped['username'];

            if (empty($username)) {
                continue;
            }

            $activeUsernames[] = $username;

            RadiusAccount::updateOrCreate(
                [
                    'mikrotik_connection_id' => $conn->id,
                    'username'               => $username,
                    'service'                => $service,
                ],
                [
                    'ipv4_address' => $mapped['ipv4_address'],
                    'uptime'       => $mapped['uptime'],
                    'caller_id'    => $mapped['caller_id'],
                    'server_name'  => $mapped['server_name'],
                    'profile'      => $mapped['profile'],
                    'bytes_in'     => $mapped['bytes_in'] ?? null,
                    'bytes_out'    => $mapped['bytes_out'] ?? null,
                    'is_active'    => true,
                    'updated_at'   => $now,
                ]
            );
        }

        // Mark sessions no longer in MikroTik response as inactive
        RadiusAccount::where('mikrotik_connection_id', $conn->id)
            ->where('service', $service)
            ->where('is_active', true)
            ->when(! empty($activeUsernames), fn ($q) => $q->whereNotIn('username', $activeUsernames))
            ->update(['is_active' => false, 'updated_at' => $now]);

        // Close zombie radacct sessions: open sessions (acctstoptime IS NULL) for users
        // that are no longer active on this NAS. This prevents FreeRADIUS from blocking
        // new logins due to stale Simultaneous-Use counts.
        $nasIp = $conn->host ?? null;
        if ($nasIp !== null) {
            $zombieQuery = DB::table('radacct')
                ->whereNull('acctstoptime')
                ->where('nasipaddress', $nasIp);

            if (! empty($activeUsernames)) {
                $zombieQuery->whereNotIn('username', $activeUsernames);
            }

            $zombieQuery->update([
                'acctstoptime'       => $now,
                'acctupdatetime'     => $now,
                'acctterminatecause' => 'NAS-Request',
            ]);
        }
    }
}
