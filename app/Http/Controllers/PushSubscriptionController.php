<?php

namespace App\Http\Controllers;

use App\Models\PppUser;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    // ── Staff (auth middleware) ───────────────────────────────────────────────

    /**
     * POST /push/subscribe
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'endpoint'    => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth'   => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'subscribable_type' => User::class,
                'subscribable_id'   => $user->id,
                'public_key'        => $data['keys']['p256dh'],
                'auth_token'        => $data['keys']['auth'],
                'owner_id'          => $user->effectiveOwnerId(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /push/unsubscribe
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        PushSubscription::query()
            ->where('subscribable_type', User::class)
            ->where('subscribable_id', $user->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /push/vapid-public-key
     * Public — safe to expose (VAPID public key is meant to be public).
     */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('push.vapid.public_key', ''),
        ]);
    }

    // ── Customer Portal (portal.auth middleware) ──────────────────────────────

    /**
     * POST /portal/{portalSlug}/push/subscribe
     */
    public function portalStore(Request $request, string $portalSlug): JsonResponse
    {
        /** @var PppUser $pppUser */
        $pppUser = $request->attributes->get('portal_ppp_user');

        $data = $request->validate([
            'endpoint'    => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth'   => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'subscribable_type' => PppUser::class,
                'subscribable_id'   => $pppUser->id,
                'public_key'        => $data['keys']['p256dh'],
                'auth_token'        => $data['keys']['auth'],
                'owner_id'          => $pppUser->owner_id,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /portal/{portalSlug}/push/unsubscribe
     */
    public function portalDestroy(Request $request, string $portalSlug): JsonResponse
    {
        /** @var PppUser $pppUser */
        $pppUser = $request->attributes->get('portal_ppp_user');

        PushSubscription::query()
            ->where('subscribable_type', PppUser::class)
            ->where('subscribable_id', $pppUser->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['success' => true]);
    }
}
