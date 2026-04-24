<?php

namespace App\Http\Controllers;

use App\Models\HotspotUser;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use App\Services\WaGatewayService;
use App\Services\YCloudInboundMediaService;
use App\Services\YCloudWhatsAppService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaChatController extends Controller
{
    use LogsActivity;

    private const ALLOWED_ROLES = ['administrator', 'noc', 'it_support', 'cs'];

    public function __construct(
        private YCloudInboundMediaService $ycloudInboundMediaService,
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        $settings = TenantSettings::where('user_id', $user->effectiveOwnerId())->first();

        return view('wa-chat.index', compact('settings'));
    }

    public function conversations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        $status = $request->input('status');
        $search = $request->input('search');

        $query = WaConversation::query()
            ->accessibleBy($user)
            ->with('assignedTo:id,name,nickname')
            ->orderByDesc('last_message_at');

        if ($status && in_array($status, ['open', 'pending', 'resolved'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        $ownerId = $user->isSuperAdmin() ? null : $user->effectiveOwnerId();

        $conversations = $query->limit(100)->get()->map(function (WaConversation $c) use ($ownerId) {
            $effectiveOwner = $ownerId ?? $c->owner_id;
            $customer = $this->resolveCustomer($c->contact_phone, $effectiveOwner);

            return [
                'id' => $c->id,
                'provider' => $this->resolveConversationProvider($c),
                'contact_phone' => $c->contact_phone,
                'contact_name' => $c->contact_name ?? $c->contact_phone,
                'status' => $c->status,
                'bot_paused_until' => $c->bot_paused_until?->toISOString(),
                'last_message' => $c->last_message,
                'last_message_at' => $c->last_message_at?->diffForHumans(),
                'last_message_at_raw' => $c->last_message_at?->toISOString(),
                'unread_count' => $c->unread_count,
                'assigned_to' => $c->assignedTo ? ($c->assignedTo->nickname ?? $c->assignedTo->name) : null,
                'customer' => $customer,
            ];
        });

        return response()->json(['data' => $conversations]);
    }

    public function show(WaConversation $waConversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waConversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $afterId = request()->query('after');

        // Append-only mode: return only new messages after given id
        if ($afterId !== null) {
            $newMessages = $waConversation->messages()
                ->where('id', '>', (int) $afterId)
                ->orderBy('created_at')
                ->get()
                ->map(fn (WaChatMessage $msg) => $this->formatMessage($this->ycloudInboundMediaService->hydrateChatMessage($msg)));

            return response()->json(['new_messages' => $newMessages]);
        }

        $messages = $waConversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (WaChatMessage $msg) => $this->formatMessage($this->ycloudInboundMediaService->hydrateChatMessage($msg)));

        // Reset unread count when CS opens conversation
        if ($waConversation->unread_count > 0) {
            $waConversation->update(['unread_count' => 0]);
        }

        $this->markConversationAsReadIfNeeded($waConversation);

        $customer = $this->resolveCustomer($waConversation->contact_phone, $waConversation->owner_id);

        return response()->json([
            'conversation' => [
                'id' => $waConversation->id,
                'provider' => $this->resolveConversationProvider($waConversation),
                'contact_phone' => $waConversation->contact_phone,
                'contact_name' => $waConversation->contact_name ?? $waConversation->contact_phone,
                'status' => $waConversation->status,
                'bot_paused_until' => $waConversation->bot_paused_until?->toISOString(),
                'customer' => $customer,
            ],
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, WaConversation $waConversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waConversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $request->validate(['message' => ['required', 'string', 'max:4000']]);

        $nickname = $user->nickname ?? $user->name;
        $text = $request->message."\n- ".$nickname;

        $ownerId = $user->effectiveOwnerId();
        $settings = TenantSettings::where('user_id', $ownerId)->first();

        if (! $settings || ! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        $this->showTypingIndicatorIfSupported($settings, $waConversation);

        $result = $this->sendConversationText($settings, $waConversation, $text, [
            'event' => 'cs_reply',
            'conversation_id' => $waConversation->id,
        ]);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $waConversation->messages()->create([
            'owner_id' => $ownerId,
            'provider' => $result['provider'],
            'direction' => 'outbound',
            'message' => $text,
            'message_type' => 'text',
            'pricing_category' => $result['pricing_category'],
            'is_free_window_send' => $result['is_free_window_send'],
            'delivery_status' => $result['delivery_status'],
            'pricing_metadata' => $result['pricing_metadata'],
            'sender_name' => $nickname,
            'sender_id' => $user->id,
            'wa_message_id' => $result['provider_message_id'],
            'provider_message_id' => $result['provider_message_id'],
            'created_at' => now(),
        ]);

        $waConversation->updateConversationState([
            'last_message' => mb_substr($text, 0, 255),
            'last_message_at' => now(),
            'bot_paused_until' => null, // CS sudah handle, bot aktif kembali
        ]);

        $this->logActivity('replied', 'WaConversation', $waConversation->id, $waConversation->contact_phone, $ownerId);

        return response()->json(['success' => true, 'message' => 'Pesan terkirim.']);
    }

    public function media(WaChatMessage $waChatMessage): StreamedResponse
    {
        $conversation = $waChatMessage->conversation()->firstOrFail();
        $this->authorizeAccess($conversation);

        $waChatMessage = $this->ycloudInboundMediaService->hydrateChatMessage($waChatMessage);

        $mediaPath = trim((string) ($waChatMessage->media_path ?? ''));
        if ($mediaPath === '' || ! Storage::disk('public')->exists($mediaPath)) {
            abort(404);
        }

        $mediaMime = trim((string) ($waChatMessage->media_mime ?? ''));
        $mediaFilename = trim((string) ($waChatMessage->media_filename ?? ''));
        $disposition = in_array($waChatMessage->media_type, ['image', 'video', 'audio'], true) ? 'inline' : 'attachment';

        return Storage::disk('public')->response(
            $mediaPath,
            $mediaFilename !== '' ? $mediaFilename : null,
            $mediaMime !== '' ? ['Content-Type' => $mediaMime] : [],
            $disposition
        );
    }

    public function replyImage(Request $request, WaConversation $waConversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waConversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $ownerId = $user->effectiveOwnerId();
        $settings = TenantSettings::where('user_id', $ownerId)->first();

        if (! $settings || ! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        $path = $request->file('image')->store('wa-chat-images', 'public');
        $pubUrl = asset('storage/'.$path);
        $nickname = $user->nickname ?? $user->name;
        $caption = trim($request->input('caption', ''));
        $captionFull = $caption !== '' ? $caption."\n- ".$nickname : '- '.$nickname;

        $this->showTypingIndicatorIfSupported($settings, $waConversation);

        $result = $this->sendConversationImage($settings, $waConversation, $pubUrl, $captionFull, [
            'event' => 'cs_reply_image',
            'conversation_id' => $waConversation->id,
        ]);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $waConversation->messages()->create([
            'owner_id' => $ownerId,
            'provider' => $result['provider'],
            'direction' => 'outbound',
            'message' => $captionFull ?: null,
            'message_type' => 'image',
            'pricing_category' => $result['pricing_category'],
            'is_free_window_send' => $result['is_free_window_send'],
            'delivery_status' => $result['delivery_status'],
            'pricing_metadata' => $result['pricing_metadata'],
            'media_type' => 'image',
            'media_path' => $path,
            'sender_name' => $nickname,
            'sender_id' => $user->id,
            'wa_message_id' => $result['provider_message_id'],
            'provider_message_id' => $result['provider_message_id'],
            'created_at' => now(),
        ]);

        $waConversation->updateConversationState([
            'last_message' => '[Gambar]'.($caption ? ' '.$caption : ''),
            'last_message_at' => now(),
            'bot_paused_until' => null,
        ]);

        $this->logActivity('replied', 'WaConversation', $waConversation->id, $waConversation->contact_phone, $ownerId);

        return response()->json([
            'success' => true,
            'media_url' => $pubUrl,
            'caption' => $captionFull,
        ]);
    }

    public function markResolved(WaConversation $waConversation): JsonResponse
    {
        $this->authorizeAccess($waConversation);
        $waConversation->update(['status' => 'resolved']);

        return response()->json(['success' => true]);
    }

    public function markOpen(WaConversation $waConversation): JsonResponse
    {
        $this->authorizeAccess($waConversation);
        $waConversation->update(['status' => 'open']);

        return response()->json(['success' => true]);
    }

    public function resumeBot(WaConversation $waConversation): JsonResponse
    {
        $this->authorizeAccess($waConversation);
        $waConversation->update(['bot_paused_until' => null]);

        return response()->json(['success' => true]);
    }

    public function destroy(WaConversation $waConversation): JsonResponse
    {
        $this->authorizeAccess($waConversation);

        if ($waConversation->status !== 'resolved') {
            return response()->json(['success' => false, 'message' => 'Hanya percakapan yang sudah selesai (resolved) yang bisa dihapus.'], 422);
        }

        $waConversation->messages()->delete();
        $waConversation->delete();

        return response()->json(['success' => true]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $ownerId = $user->effectiveOwnerId();
        $results = [];

        $ppps = PppUser::where('owner_id', $ownerId)
            ->where(function ($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('nomor_hp', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get(['id', 'customer_name', 'username', 'nomor_hp']);

        foreach ($ppps as $p) {
            $results[] = [
                'type' => 'ppp',
                'id' => $p->id,
                'name' => $p->customer_name,
                'sub' => $p->username.($p->nomor_hp ? ' · '.$p->nomor_hp : ''),
                'url' => route('ppp-users.show', $p->id),
            ];
        }

        $hotspots = HotspotUser::where('owner_id', $ownerId)
            ->where(function ($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('nomor_hp', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get(['id', 'customer_name', 'username', 'nomor_hp']);

        foreach ($hotspots as $h) {
            $results[] = [
                'type' => 'hotspot',
                'id' => $h->id,
                'name' => $h->customer_name,
                'sub' => $h->username.($h->nomor_hp ? ' · '.$h->nomor_hp : ''),
                'url' => route('hotspot-users.show', $h->id),
            ];
        }

        return response()->json($results);
    }

    public function assignableUsers(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();

        $users = User::query()
            ->where(function ($q) use ($ownerId) {
                $q->where('id', $ownerId)
                    ->orWhere('parent_id', $ownerId);
            })
            ->whereIn('role', ['administrator', 'noc', 'it_support', 'cs', 'teknisi'])
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'nickname', 'role'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'label' => ($u->nickname ?? $u->name).' ('.$u->role.')',
            ]);

        return response()->json($users);
    }

    public function assign(Request $request, WaConversation $waConversation): JsonResponse
    {
        $this->authorizeAccess($waConversation);

        $request->validate(['assigned_to_id' => ['nullable', 'integer']]);

        $assignedToId = $request->input('assigned_to_id');

        if ($assignedToId !== null) {
            /** @var User $user */
            $user = Auth::user();
            $assignee = User::find($assignedToId);

            if (! $assignee) {
                return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 422);
            }

            if (! $user->isSuperAdmin() && $assignee->effectiveOwnerId() !== $user->effectiveOwnerId()) {
                return response()->json(['success' => false, 'message' => 'User bukan anggota tenant ini.'], 422);
            }
        }

        $waConversation->update(['assigned_to_id' => $assignedToId]);

        return response()->json(['success' => true]);
    }

    private function formatMessage(WaChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'provider' => $msg->provider ?? 'local',
            'direction' => $msg->direction,
            'message' => $msg->message,
            'message_type' => $msg->message_type,
            'delivery_status' => $msg->delivery_status,
            'media_type' => $msg->media_type,
            'media_url' => $msg->media_path ? route('wa-chat.media', $msg) : null,
            'media_mime' => $msg->media_mime,
            'media_filename' => $msg->media_filename,
            'sender_name' => $msg->sender_name,
            'created_at' => $msg->created_at?->toISOString(),
            'created_at_human' => $msg->created_at?->format('H:i'),
            'created_at_date' => $msg->created_at?->format('d M Y'),
        ];
    }

    /**
     * Cari pelanggan (PPP atau Hotspot) berdasarkan nomor HP.
     * Returns array ['type', 'id', 'name', 'url'] atau null jika tidak ditemukan.
     */
    private function resolveCustomer(string $phone, int $ownerId): ?array
    {
        // Normalisasi: strip leading 0 / +62 → 62...
        $normalized = preg_replace('/^\+/', '', $phone);

        $ppp = PppUser::where('owner_id', $ownerId)
            ->where('nomor_hp', $normalized)
            ->first(['id', 'customer_name']);

        if ($ppp) {
            return [
                'type' => 'ppp',
                'id' => $ppp->id,
                'name' => $ppp->customer_name,
                'url' => route('ppp-users.show', $ppp->id),
            ];
        }

        $hotspot = HotspotUser::where('owner_id', $ownerId)
            ->where('nomor_hp', $normalized)
            ->first(['id', 'customer_name']);

        if ($hotspot) {
            return [
                'type' => 'hotspot',
                'id' => $hotspot->id,
                'name' => $hotspot->customer_name,
                'url' => route('hotspot-users.show', $hotspot->id),
            ];
        }

        return null;
    }

    private function authorizeAccess(WaConversation $waConversation): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::ALLOWED_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waConversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
    }

    private function resolveConversationProvider(WaConversation $waConversation, ?TenantSettings $settings = null): string
    {
        $provider = trim((string) ($waConversation->provider ?? ''));

        if ($provider !== '') {
            return $provider;
        }

        if ($settings?->usesYCloud()) {
            return 'ycloud';
        }

        return 'local';
    }

    private function markConversationAsReadIfNeeded(WaConversation $waConversation): void
    {
        $settings = TenantSettings::query()->where('user_id', $waConversation->owner_id)->first();
        if (! $settings || $this->resolveConversationProvider($waConversation, $settings) !== 'ycloud') {
            return;
        }

        $service = YCloudWhatsAppService::forTenant($settings);
        $messageId = $this->latestInboundProviderMessageId($waConversation);

        if ($service && $messageId !== null) {
            $service->markAsRead($messageId);
        }
    }

    private function showTypingIndicatorIfSupported(TenantSettings $settings, WaConversation $waConversation): void
    {
        if ($this->resolveConversationProvider($waConversation, $settings) !== 'ycloud') {
            return;
        }

        $service = YCloudWhatsAppService::forTenant($settings);
        $messageId = $this->latestInboundProviderMessageId($waConversation);

        if ($service && $messageId !== null) {
            $service->showTypingIndicator($messageId);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, provider: string, message: string, provider_message_id: string|null, delivery_status: string|null, pricing_category: string|null, is_free_window_send: bool, pricing_metadata: array<mixed>}
     */
    private function sendConversationText(TenantSettings $settings, WaConversation $waConversation, string $message, array $context = []): array
    {
        if ($this->resolveConversationProvider($waConversation, $settings) === 'ycloud') {
            $service = YCloudWhatsAppService::forTenant($settings);
            if (! $service) {
                return $this->failedProviderResult('ycloud', 'YCloud WhatsApp belum dikonfigurasi.');
            }

            return $this->normalizeYCloudResult(
                $service->sendTextMessage($waConversation->contact_phone, $message),
                $waConversation
            );
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return $this->failedProviderResult('local', 'WA Gateway tidak tersedia.');
        }

        $ok = $service->sendMessage($waConversation->contact_phone, $message, $context);

        return [
            'ok' => $ok,
            'provider' => 'local',
            'message' => $ok ? 'Pesan terkirim.' : 'Gagal mengirim pesan ke gateway lokal.',
            'provider_message_id' => null,
            'delivery_status' => null,
            'pricing_category' => null,
            'is_free_window_send' => false,
            'pricing_metadata' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, provider: string, message: string, provider_message_id: string|null, delivery_status: string|null, pricing_category: string|null, is_free_window_send: bool, pricing_metadata: array<mixed>}
     */
    private function sendConversationImage(TenantSettings $settings, WaConversation $waConversation, string $imageUrl, string $caption = '', array $context = []): array
    {
        if ($this->resolveConversationProvider($waConversation, $settings) === 'ycloud') {
            $service = YCloudWhatsAppService::forTenant($settings);
            if (! $service) {
                return $this->failedProviderResult('ycloud', 'YCloud WhatsApp belum dikonfigurasi.');
            }

            return $this->normalizeYCloudResult(
                $service->sendImageMessage($waConversation->contact_phone, $imageUrl, $caption),
                $waConversation
            );
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return $this->failedProviderResult('local', 'WA Gateway tidak tersedia.');
        }

        $ok = $service->sendImage($waConversation->contact_phone, $imageUrl, $caption, $context);

        return [
            'ok' => $ok,
            'provider' => 'local',
            'message' => $ok ? 'Pesan terkirim.' : 'Gagal mengirim gambar ke gateway lokal.',
            'provider_message_id' => null,
            'delivery_status' => null,
            'pricing_category' => null,
            'is_free_window_send' => false,
            'pricing_metadata' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{ok: bool, provider: string, message: string, provider_message_id: string|null, delivery_status: string|null, pricing_category: string|null, is_free_window_send: bool, pricing_metadata: array<mixed>}
     */
    private function normalizeYCloudResult(array $result, WaConversation $waConversation): array
    {
        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'provider' => 'ycloud',
            'message' => (string) ($result['message'] ?? ''),
            'provider_message_id' => is_scalar($result['provider_message_id'] ?? null) ? (string) $result['provider_message_id'] : null,
            'delivery_status' => is_scalar($result['delivery_status'] ?? null) ? (string) $result['delivery_status'] : null,
            'pricing_category' => null,
            'is_free_window_send' => $waConversation->hasOpenServiceWindow(),
            'pricing_metadata' => is_array($result['pricing_metadata'] ?? null) ? $result['pricing_metadata'] : [],
        ];
    }

    /**
     * @return array{ok: bool, provider: string, message: string, provider_message_id: string|null, delivery_status: string|null, pricing_category: string|null, is_free_window_send: bool, pricing_metadata: array<mixed>}
     */
    private function failedProviderResult(string $provider, string $message): array
    {
        return [
            'ok' => false,
            'provider' => $provider,
            'message' => $message,
            'provider_message_id' => null,
            'delivery_status' => null,
            'pricing_category' => null,
            'is_free_window_send' => false,
            'pricing_metadata' => [],
        ];
    }

    private function latestInboundProviderMessageId(WaConversation $waConversation): ?string
    {
        $message = $waConversation->messages()
            ->where('direction', 'inbound')
            ->where('provider', 'ycloud')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $message) {
            return null;
        }

        $providerMessageId = trim((string) ($message->provider_message_id ?? $message->wa_message_id ?? ''));

        return $providerMessageId !== '' ? $providerMessageId : null;
    }
}
