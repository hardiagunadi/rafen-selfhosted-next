<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\PppUser;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private static ?WebPush $client = null;

    private static function client(): ?WebPush
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $publicKey  = config('push.vapid.public_key', '');
        $privateKey = config('push.vapid.private_key', '');

        if (empty($publicKey) || empty($privateKey)) {
            Log::warning('PushNotification: VAPID keys not configured.');

            return null;
        }

        self::$client = new WebPush([
            'VAPID' => [
                'subject'    => config('push.vapid.subject'),
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        self::$client->setReuseVAPIDHeaders(true);

        return self::$client;
    }

    /**
     * Send push notification to a staff user (User model).
     */
    public static function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        $subscriptions = PushSubscription::query()
            ->where('subscribable_type', User::class)
            ->where('subscribable_id', $user->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        self::dispatch($subscriptions, $title, $body, $data);
    }

    /**
     * Send push notification to a customer (PppUser or HotspotUser).
     */
    public static function sendToCustomer(
        PppUser|HotspotUser $customer,
        string $title,
        string $body,
        array $data = []
    ): void {
        $subscriptions = PushSubscription::query()
            ->where('subscribable_type', $customer::class)
            ->where('subscribable_id', $customer->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        self::dispatch($subscriptions, $title, $body, $data);
    }

    /**
     * Send push notification to all staff of a tenant.
     *
     * @param  string[]  $roles  Empty = all roles. e.g. ['administrator','noc','cs']
     */
    public static function sendToOwnerStaff(
        int $ownerId,
        string $title,
        string $body,
        array $data = [],
        array $roles = []
    ): void {
        $userQuery = User::query()
            ->where(function ($q) use ($ownerId) {
                $q->where('id', $ownerId)
                  ->orWhere('parent_id', $ownerId);
            });

        if (! empty($roles)) {
            $userQuery->whereIn('role', $roles);
        }

        $userIds = $userQuery->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('subscribable_type', User::class)
            ->whereIn('subscribable_id', $userIds)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        self::dispatch($subscriptions, $title, $body, $data);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    private static function dispatch(
        Collection $subscriptions,
        string $title,
        string $body,
        array $data
    ): void {
        $webPush = self::client();
        if ($webPush === null) {
            return;
        }

        $payload = json_encode([
            'title'  => $title,
            'body'   => $body,
            'icon'   => $data['icon']  ?? '/branding/favicon-192.png',
            'badge'  => $data['badge'] ?? '/branding/favicon-192.png',
            'url'    => $data['url']   ?? '/',
            'tag'    => $data['tag']   ?? 'rafen-notify',
        ]);

        $endpointToId = [];

        try {
            foreach ($subscriptions as $sub) {
                $endpointToId[$sub->endpoint] = $sub->id;

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys'     => [
                            'p256dh' => $sub->public_key,
                            'auth'   => $sub->auth_token,
                        ],
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = (string) $report->getRequest()->getUri();

                if ($report->isSubscriptionExpired()) {
                    if (isset($endpointToId[$endpoint])) {
                        PushSubscription::destroy($endpointToId[$endpoint]);
                        Log::info('PushNotification: removed expired subscription', [
                            'endpoint_prefix' => substr($endpoint, 0, 60),
                        ]);
                    }
                } elseif (! $report->isSuccess()) {
                    Log::warning('PushNotification: failed to send', [
                        'reason'          => $report->getReason(),
                        'endpoint_prefix' => substr($endpoint, 0, 60),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PushNotification: exception during dispatch', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
