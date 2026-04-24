<?php

namespace App\Http\Controllers;

use App\Http\Requests\YCloudWhatsAppWebhookRequest;
use App\Models\TenantSettings;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use App\Models\WaWebhookLog;
use App\Services\YCloudInboundMediaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class YCloudWhatsAppWebhookController extends Controller
{
    public function __construct(private YCloudInboundMediaService $ycloudInboundMediaService) {}

    public function receive(YCloudWhatsAppWebhookRequest $request): JsonResponse
    {
        $request->validated();

        $payload = $request->all();
        $settings = $this->resolveSettings($payload);
        $secret = $this->resolveWebhookSecret($settings);

        if ($secret === '' || ! $this->hasValidSignature($request, $secret)) {
            return response()->json(['status' => false, 'message' => 'Invalid signature.'], 401);
        }

        $eventId = trim((string) ($payload['id'] ?? ''));
        if ($eventId !== '' && ! Cache::add('ycloud_webhook_'.$eventId, true, now()->addDays(7))) {
            return response()->json(['status' => true, 'duplicate' => true]);
        }

        $eventType = trim((string) ($payload['type'] ?? ''));

        if ($settings) {
            $this->storeWebhookLog($settings, $payload, $eventType);
        }

        if ($settings && $this->isInboundEvent($payload)) {
            $this->syncInboundConversation($settings, $payload);
            $this->dispatchConversationalAutoReply($settings, $payload);
        }

        if ($settings && $this->isStatusEvent($payload)) {
            $this->syncMessageStatus($settings, $payload);
        }

        return response()->json(['status' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveSettings(array $payload): ?TenantSettings
    {
        $phoneNumberId = trim((string) ($this->extractPayloadValue($payload, [
            'whatsappInboundMessage.to.phoneNumberId',
            'whatsappInboundMessage.phoneNumberId',
            'whatsappMessage.to.phoneNumberId',
            'whatsappMessage.phoneNumberId',
            'whatsappMessage.metadata.phone_number_id',
        ]) ?? ''));

        if ($phoneNumberId !== '') {
            $settings = TenantSettings::query()
                ->where('wa_provider', 'ycloud')
                ->where('ycloud_phone_number_id', $phoneNumberId)
                ->first();

            if ($settings) {
                return $settings;
            }
        }

        $businessNumber = $this->normalizePhone((string) ($this->extractPayloadValue($payload, [
            'whatsappInboundMessage.to',
            'whatsappMessage.to',
        ]) ?? ''));

        if ($businessNumber === '') {
            return null;
        }

        return TenantSettings::query()
            ->where('wa_provider', 'ycloud')
            ->whereIn('ycloud_business_number', [$businessNumber, '+'.$businessNumber])
            ->first();
    }

    private function resolveWebhookSecret(?TenantSettings $settings): string
    {
        if ($settings) {
            return trim((string) ($settings->ycloud_webhook_secret ?? ''));
        }

        return trim((string) config('services.ycloud_whatsapp.webhook_secret', ''));
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $header = trim((string) $request->header('YCloud-Signature', ''));
        if ($header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $fragment) {
            [$key, $value] = array_pad(explode('=', trim($fragment), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['s'] ?? null;

        if (! is_string($timestamp) || ! is_string($signature) || $timestamp === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isInboundEvent(array $payload): bool
    {
        $eventType = trim((string) ($payload['type'] ?? ''));

        return str_contains($eventType, 'inbound')
            || is_array($payload['whatsappInboundMessage'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isStatusEvent(array $payload): bool
    {
        $eventType = trim((string) ($payload['type'] ?? ''));

        return str_contains($eventType, 'updated')
            || str_contains($eventType, 'status')
            || is_array($payload['whatsappMessage'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncInboundConversation(TenantSettings $settings, array $payload): void
    {
        $message = is_array($payload['whatsappInboundMessage'] ?? null)
            ? $payload['whatsappInboundMessage']
            : (is_array($payload['whatsappMessage'] ?? null) ? $payload['whatsappMessage'] : []);

        $sender = $this->normalizePhone((string) ($message['from'] ?? ''));
        if ($sender === '') {
            return;
        }

        $providerMessageId = trim((string) ($message['id'] ?? ''));
        if ($providerMessageId !== '' && WaChatMessage::query()->where('provider', 'ycloud')->where('provider_message_id', $providerMessageId)->exists()) {
            return;
        }

        $messageType = trim((string) ($message['type'] ?? 'text')) ?: 'text';
        $messageBody = $this->resolveMessageBody($message);
        if ($messageBody === '') {
            $messageBody = '['.$messageType.']';
        }
        $media = $this->ycloudInboundMediaService->resolveInboundMedia(
            $message,
            trim((string) ($settings->ycloud_api_key ?? ''))
        );

        $conversation = WaConversation::query()->firstOrNew([
            'owner_id' => $settings->user_id,
            'provider' => 'ycloud',
            'contact_phone' => $sender,
        ]);

        if (! $conversation->exists) {
            $conversation->fill([
                'session_id' => trim((string) ($settings->ycloud_phone_number_id ?? '')) ?: null,
                'provider_customer_key' => trim((string) ($message['from'] ?? '')) ?: null,
                'contact_name' => $this->resolveContactName($message),
                'status' => 'open',
                'unread_count' => 0,
            ]);
            $conversation->save();
        }

        $createdAt = $this->resolvePayloadTimestamp((string) ($message['createTime'] ?? ''));

        $conversation->fill([
            'session_id' => trim((string) ($settings->ycloud_phone_number_id ?? '')) ?: $conversation->session_id,
            'provider_customer_key' => trim((string) ($message['from'] ?? '')) ?: $conversation->provider_customer_key,
            'contact_name' => $this->resolveContactName($message) ?: $conversation->contact_name,
        ]);
        $conversation->save();

        $conversation->messages()->create([
            'owner_id' => $settings->user_id,
            'provider' => 'ycloud',
            'direction' => 'inbound',
            'message' => $messageBody,
            'message_type' => $messageType,
            'media_type' => $media['type'],
            'media_path' => $media['path'],
            'media_mime' => $media['mime'],
            'media_filename' => $media['filename'],
            'delivery_status' => 'received',
            'wa_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'created_at' => $createdAt,
        ]);

        $conversation->updateConversationState([
            'last_message' => mb_substr($messageBody, 0, 500),
            'last_message_at' => $createdAt,
            'last_inbound_at' => $createdAt,
            'service_window_expires_at' => $createdAt->copy()->addDay(),
            'status' => 'open',
            'unread_count' => ((int) $conversation->unread_count) + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncMessageStatus(TenantSettings $settings, array $payload): void
    {
        $message = is_array($payload['whatsappMessage'] ?? null) ? $payload['whatsappMessage'] : [];
        $providerMessageId = trim((string) ($message['id'] ?? ''));
        if ($providerMessageId === '') {
            return;
        }

        $chatMessage = WaChatMessage::query()
            ->where('owner_id', $settings->user_id)
            ->where('provider', 'ycloud')
            ->where(function ($query) use ($providerMessageId): void {
                $query->where('provider_message_id', $providerMessageId)
                    ->orWhere('wa_message_id', $providerMessageId);
            })
            ->latest('id')
            ->first();

        if (! $chatMessage) {
            return;
        }

        $pricingMetadata = is_array($message['pricing_analytics'] ?? null)
            ? $message['pricing_analytics']
            : (is_array($message['pricingAnalytics'] ?? null) ? $message['pricingAnalytics'] : []);

        $chatMessage->update([
            'delivery_status' => trim((string) ($message['status'] ?? '')) ?: $chatMessage->delivery_status,
            'pricing_metadata' => $pricingMetadata !== [] ? $pricingMetadata : $chatMessage->pricing_metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchConversationalAutoReply(TenantSettings $settings, array $payload): void
    {
        $message = is_array($payload['whatsappInboundMessage'] ?? null)
            ? $payload['whatsappInboundMessage']
            : (is_array($payload['whatsappMessage'] ?? null) ? $payload['whatsappMessage'] : []);

        $messageType = strtolower(trim((string) ($message['type'] ?? 'text')));
        $normalizedPayload = [
            'provider' => 'ycloud',
            'from' => $this->normalizePhone((string) ($message['from'] ?? '')),
            'message' => $this->resolveMessageBody($message) ?: '['.$messageType.']',
            'message_status' => 'received',
            'fromMe' => false,
            'isGroup' => false,
        ];

        if ($messageType !== '') {
            $normalizedPayload['mediaType'] = $messageType;
        }

        if (in_array($messageType, ['image', 'photo', 'video', 'audio', 'document'], true)) {
            $normalizedPayload['media'] = ['present' => true];
        }

        app(WaWebhookController::class)->sendConversationalReply(
            $normalizedPayload,
            (int) $settings->user_id,
            'message'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookLog(TenantSettings $settings, array $payload, string $eventType): void
    {
        $message = is_array($payload['whatsappInboundMessage'] ?? null)
            ? $payload['whatsappInboundMessage']
            : (is_array($payload['whatsappMessage'] ?? null) ? $payload['whatsappMessage'] : []);

        WaWebhookLog::create([
            'owner_id' => $settings->user_id,
            'event_type' => 'ycloud_'.$eventType,
            'session_id' => trim((string) ($settings->ycloud_phone_number_id ?? '')) ?: null,
            'sender' => $this->normalizePhone((string) ($message['from'] ?? '')) ?: null,
            'message' => $this->resolveMessageBody($message) ?: (trim((string) ($message['id'] ?? '')) ?: null),
            'status' => trim((string) ($message['status'] ?? $message['type'] ?? '')) ?: null,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveMessageBody(array $message): string
    {
        foreach ([
            'text.body',
            'button.payload',
            'button.text',
            'interactive.button_reply.title',
            'image.caption',
            'document.caption',
            'audio.caption',
        ] as $path) {
            $value = data_get($message, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 1000);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveContactName(array $message): ?string
    {
        foreach ([
            'customerProfile.name',
            'fromName',
            'profile.name',
        ] as $path) {
            $value = data_get($message, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '0')) {
            return '62'.substr($normalized, 1);
        }

        if (! str_starts_with($normalized, '62')) {
            return '62'.$normalized;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $paths
     */
    private function extractPayloadValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolvePayloadTimestamp(string $timestamp): Carbon
    {
        if ($timestamp === '') {
            return now();
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return now();
        }
    }
}
