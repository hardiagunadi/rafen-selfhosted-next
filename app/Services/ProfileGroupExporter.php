<?php

namespace App\Services;

use App\Models\HotspotProfile;
use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use RuntimeException;
use Throwable;

class ProfileGroupExporter
{
    public function export(ProfileGroup $group, MikrotikConnection $connection): void
    {
        $client = new MikrotikApiClient($connection);
        $client->connect();

        try {
            $poolName = $this->resolvePoolName($group);

            // For sql mode: create/update IP pool on MikroTik first
            if ($group->ip_pool_mode === 'sql' && $group->type === 'pppoe') {
                $this->exportIpPool($client, $group, $poolName);
            }

            $this->exportProfile($client, $group, $poolName);
        } finally {
            $client->disconnect();
        }
    }

    private function exportIpPool(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        if (! $poolName) {
            return;
        }

        $rangeStart = trim((string) $group->range_start);
        $rangeEnd = trim((string) $group->range_end);

        if ($rangeStart === '' || $rangeEnd === '') {
            return;
        }

        $ranges = $rangeStart.'-'.$rangeEnd;

        $existingId = $this->findId($client, '/ip/pool/print', ['name' => $poolName]);

        if ($existingId) {
            $client->command('/ip/pool/set', ['numbers' => $existingId, 'ranges' => $ranges]);
        } else {
            $client->command('/ip/pool/add', ['name' => $poolName, 'ranges' => $ranges]);
        }
    }

    private function exportProfile(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        $profileName = trim((string) $group->name);
        if ($profileName === '') {
            throw new RuntimeException('Nama profil group belum diisi.');
        }

        $isPppProfile = $group->type === 'pppoe';
        [$basePath, $attributes] = $isPppProfile
            ? ['/ppp/profile', $this->pppProfileAttributes($group, $poolName)]
            : ['/ip/hotspot/user/profile', $this->hotspotProfileAttributes($group, $poolName)];
        $attributes = $this->sanitizeParentQueueAttribute($client, $group, $attributes);

        $existingId = $this->findId($client, $basePath.'/print', ['name' => $profileName]);

        $payload = array_merge(['name' => $profileName], $attributes);

        if ($existingId) {
            $payload['numbers'] = $existingId;
            $client->command($basePath.'/set', $payload);

            return;
        }

        $client->command($basePath.'/add', $payload);
    }

    private function pppProfileAttributes(ProfileGroup $group, ?string $poolName): array
    {
        $localAddress = trim((string) $group->ip_address);
        if ($localAddress === '') {
            throw new RuntimeException('IP lokal belum diisi pada profil group "'.$group->name.'".');
        }

        $attributes = [
            'local-address' => $localAddress,
            'comment' => 'added by TMDRadius',
        ];

        // remote-address: sql mode → use pool (RADIUS sends Framed-IP-Address,
        //   but pool must exist on MikroTik as the PPP profile still needs one)
        // group_only → use named pool on MikroTik
        if ($group->ip_pool_mode === 'sql' || $group->ip_pool_mode === 'group_only') {
            $attributes['remote-address'] = $poolName ?? '';
        }

        $dns = trim((string) $group->dns_servers);
        if ($dns !== '') {
            $attributes['dns-server'] = $dns;
        }

        $parentQueue = trim((string) $group->parent_queue);
        if ($parentQueue !== '') {
            $attributes['parent-queue'] = $parentQueue;
        }

        return $attributes;
    }

    private function hotspotProfileAttributes(ProfileGroup $group, ?string $poolName): array
    {
        // Use explicit ip_pool_name if set; otherwise 'none' so MikroTik assigns IP directly
        $explicitPool = trim((string) $group->ip_pool_name);
        $attributes = [
            'address-pool' => $explicitPool !== '' ? $explicitPool : 'none',
            'shared-users' => (string) $this->resolveHotspotSharedUsers($group),
        ];

        $parentQueue = trim((string) $group->parent_queue);
        if ($parentQueue !== '') {
            $attributes['parent-queue'] = $parentQueue;
        }

        return $attributes;
    }

    private function resolveHotspotSharedUsers(ProfileGroup $group): int
    {
        $sharedUsers = HotspotProfile::query()
            ->where('profile_group_id', $group->id)
            ->max('shared_users');

        return max(1, (int) ($sharedUsers ?? 1));
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array<string, string>
     */
    private function sanitizeParentQueueAttribute(MikrotikApiClient $client, ProfileGroup $group, array $attributes): array
    {
        if (! isset($attributes['parent-queue'])) {
            return $attributes;
        }

        $parentQueue = trim((string) $attributes['parent-queue']);
        if ($parentQueue === '' || strtolower($parentQueue) === 'none') {
            unset($attributes['parent-queue']);

            return $attributes;
        }

        if ($this->queueExists($client, $parentQueue)) {
            return $attributes;
        }

        if ($this->createParentQueueFromDatabase($client, $group, $parentQueue)) {
            return $attributes;
        }

        unset($attributes['parent-queue']);

        return $attributes;
    }

    private function queueExists(MikrotikApiClient $client, string $queueName): bool
    {
        try {
            if ($this->findId($client, '/queue/simple/print', ['name' => $queueName])) {
                return true;
            }

            return $this->findId($client, '/queue/tree/print', ['name' => $queueName]) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function createParentQueueFromDatabase(MikrotikApiClient $client, ProfileGroup $group, string $parentQueue): bool
    {
        try {
            $target = $this->resolveQueueTargetFromDatabase($group, $parentQueue) ?: '0.0.0.0/0';

            $client->command('/queue/simple/add', [
                'name' => $parentQueue,
                'target' => $target,
                'max-limit' => '0/0',
                'comment' => 'rafen: auto-created parent queue from profile group',
            ]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function resolveQueueTargetFromDatabase(ProfileGroup $group, string $parentQueue): ?string
    {
        $groups = ProfileGroup::query()
            ->where('owner_id', $group->owner_id)
            ->where('parent_queue', $parentQueue)
            ->get(['ip_address', 'netmask', 'range_start', 'range_end']);

        $targets = $groups
            ->flatMap(function (ProfileGroup $item): array {
                $targets = [];
                $cidr = $this->convertToCidr(
                    trim((string) $item->ip_address),
                    trim((string) $item->netmask),
                );
                if ($cidr !== null) {
                    $targets[] = $cidr;
                }

                $rangeStart = trim((string) $item->range_start);
                $rangeEnd = trim((string) $item->range_end);
                if ($rangeStart !== '' && $rangeEnd !== '') {
                    $targets[] = $rangeStart.'-'.$rangeEnd;
                }

                return $targets;
            })
            ->filter()
            ->unique()
            ->values();

        if ($targets->isNotEmpty()) {
            return $targets->implode(',');
        }

        return $this->resolveFallbackTargetFromConnection($group);
    }

    private function resolveFallbackTargetFromConnection(ProfileGroup $group): ?string
    {
        if (! $group->mikrotik_connection_id) {
            return null;
        }

        $connection = MikrotikConnection::query()
            ->whereKey($group->mikrotik_connection_id)
            ->first(['hotspot_subnet']);

        $hotspotSubnet = trim((string) ($connection?->hotspot_subnet ?? ''));

        return $hotspotSubnet !== '' ? $hotspotSubnet : null;
    }

    private function convertToCidr(string $ipAddress, string $netmask): ?string
    {
        if ($ipAddress === '' || $netmask === '') {
            return null;
        }

        $ipLong = ip2long($ipAddress);
        if ($ipLong === false) {
            return null;
        }

        $prefix = $this->normalizePrefixLength($netmask);
        if ($prefix === null) {
            return null;
        }

        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));
        $network = long2ip($ipLong & $mask);

        if ($network === false) {
            return null;
        }

        return $network.'/'.$prefix;
    }

    private function normalizePrefixLength(string $netmask): ?int
    {
        if (is_numeric($netmask)) {
            $prefix = (int) $netmask;

            return $prefix >= 0 && $prefix <= 32 ? $prefix : null;
        }

        $maskLong = ip2long($netmask);
        if ($maskLong === false) {
            return null;
        }

        $binary = str_pad(decbin($maskLong & 0xFFFFFFFF), 32, '0', STR_PAD_LEFT);
        if (! preg_match('/^1*0*$/', $binary)) {
            return null;
        }

        return substr_count($binary, '1');
    }

    private function resolvePoolName(ProfileGroup $group): ?string
    {
        $poolName = trim((string) $group->ip_pool_name);

        if ($poolName === '') {
            $poolName = trim((string) $group->name);
        }

        return $poolName !== '' ? $poolName : null;
    }

    /**
     * @param  array<string, string>  $queries
     */
    private function findId(MikrotikApiClient $client, string $path, array $queries): ?string
    {
        $response = $client->command($path, [], $queries);

        return $response['data'][0]['.id'] ?? null;
    }
}
