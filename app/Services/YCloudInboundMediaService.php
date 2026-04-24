<?php

namespace App\Services;

use App\Models\TenantSettings;
use App\Models\WaChatMessage;
use App\Models\WaWebhookLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YCloudInboundMediaService
{
    /**
     * @param  array<string, mixed>  $message
     * @return array{type: string|null, path: string|null, mime: string|null, filename: string|null}
     */
    public function resolveInboundMedia(array $message, ?string $apiKey = null): array
    {
        $messageType = trim((string) ($message['type'] ?? ''));
        if ($messageType === '') {
            return $this->emptyMedia();
        }

        $mediaPayload = data_get($message, $messageType);
        if (! is_array($mediaPayload)) {
            return $this->emptyMedia();
        }

        $mediaLink = trim((string) ($mediaPayload['link'] ?? ''));
        $mediaMime = trim((string) ($mediaPayload['mime_type'] ?? ''));
        $mediaFilename = trim((string) ($mediaPayload['filename'] ?? ''));
        $providerMediaId = trim((string) ($mediaPayload['id'] ?? ''));
        $providerMessageId = trim((string) ($message['id'] ?? ''));

        if ($mediaFilename === '') {
            $mediaFilename = $this->defaultMediaFilename($messageType, $providerMediaId, $mediaMime);
        }

        if ($mediaLink === '') {
            return [
                'type' => $messageType,
                'path' => null,
                'mime' => $mediaMime !== '' ? $mediaMime : null,
                'filename' => $mediaFilename !== '' ? mb_substr($mediaFilename, 0, 255) : null,
            ];
        }

        $storedPath = $this->downloadInboundMedia(
            mediaLink: $mediaLink,
            messageType: $messageType,
            filename: $mediaFilename,
            providerMediaId: $providerMediaId,
            providerMessageId: $providerMessageId,
            mimeType: $mediaMime,
            createdAt: null,
            apiKey: $apiKey,
        );

        return [
            'type' => $messageType,
            'path' => $storedPath,
            'mime' => $mediaMime !== '' ? $mediaMime : null,
            'filename' => $mediaFilename !== '' ? mb_substr($mediaFilename, 0, 255) : null,
        ];
    }

    public function hydrateChatMessage(WaChatMessage $chatMessage): WaChatMessage
    {
        if (! $this->shouldHydrate($chatMessage)) {
            return $chatMessage;
        }

        $message = $this->findInboundMessagePayload($chatMessage);
        if ($message === null) {
            return $chatMessage;
        }

        $media = $this->resolveInboundMediaFromLogMessage($chatMessage, $message);
        if ($media === $this->emptyMedia()) {
            return $chatMessage;
        }

        $updates = array_filter([
            'media_type' => $media['type'] ?? $chatMessage->media_type,
            'media_path' => $media['path'] ?? $chatMessage->media_path,
            'media_mime' => $media['mime'] ?? $chatMessage->media_mime,
            'media_filename' => $media['filename'] ?? $chatMessage->media_filename,
        ], fn ($value) => $value !== null && $value !== '');

        if ($updates === []) {
            return $chatMessage;
        }

        $chatMessage->fill($updates);
        $chatMessage->save();

        return $chatMessage->refresh();
    }

    private function shouldHydrate(WaChatMessage $chatMessage): bool
    {
        if ($chatMessage->provider !== 'ycloud' || $chatMessage->direction !== 'inbound') {
            return false;
        }

        $messageType = trim((string) ($chatMessage->media_type ?: $chatMessage->message_type));
        if (! in_array($messageType, ['image', 'document', 'audio', 'video'], true)) {
            return false;
        }

        if ($chatMessage->media_path && Storage::disk('public')->exists($chatMessage->media_path)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{type: string|null, path: string|null, mime: string|null, filename: string|null}
     */
    private function resolveInboundMediaFromLogMessage(WaChatMessage $chatMessage, array $message): array
    {
        $messageType = trim((string) ($message['type'] ?? $chatMessage->message_type ?? ''));
        if ($messageType === '') {
            return $this->emptyMedia();
        }

        $mediaPayload = data_get($message, $messageType);
        if (! is_array($mediaPayload)) {
            return $this->emptyMedia();
        }

        $mediaLink = trim((string) ($mediaPayload['link'] ?? ''));
        $mediaMime = trim((string) ($mediaPayload['mime_type'] ?? $chatMessage->media_mime ?? ''));
        $mediaFilename = trim((string) ($mediaPayload['filename'] ?? $chatMessage->media_filename ?? ''));
        $providerMediaId = trim((string) ($mediaPayload['id'] ?? ''));
        $providerMessageId = trim((string) ($message['id'] ?? $chatMessage->provider_message_id ?? $chatMessage->wa_message_id ?? ''));

        if ($mediaFilename === '') {
            $mediaFilename = $this->defaultMediaFilename($messageType, $providerMediaId, $mediaMime);
        }

        if ($mediaLink === '') {
            return [
                'type' => $messageType,
                'path' => null,
                'mime' => $mediaMime !== '' ? $mediaMime : null,
                'filename' => $mediaFilename !== '' ? mb_substr($mediaFilename, 0, 255) : null,
            ];
        }

        $storedPath = $this->downloadInboundMedia(
            mediaLink: $mediaLink,
            messageType: $messageType,
            filename: $mediaFilename,
            providerMediaId: $providerMediaId,
            providerMessageId: $providerMessageId,
            mimeType: $mediaMime,
            createdAt: $chatMessage->created_at,
            apiKey: $this->resolveApiKeyForChatMessage($chatMessage),
        );

        return [
            'type' => $messageType,
            'path' => $storedPath,
            'mime' => $mediaMime !== '' ? $mediaMime : null,
            'filename' => $mediaFilename !== '' ? mb_substr($mediaFilename, 0, 255) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findInboundMessagePayload(WaChatMessage $chatMessage): ?array
    {
        $providerMessageId = trim((string) ($chatMessage->provider_message_id ?: $chatMessage->wa_message_id ?? ''));
        if ($providerMessageId === '') {
            return null;
        }

        $logs = $this->candidateInboundLogs($chatMessage, $providerMessageId);

        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $message = is_array($payload['whatsappInboundMessage'] ?? null)
                ? $payload['whatsappInboundMessage']
                : (is_array($payload['whatsappMessage'] ?? null) ? $payload['whatsappMessage'] : null);

            if (! is_array($message)) {
                continue;
            }

            $messageId = trim((string) ($message['id'] ?? ''));
            if ($messageId === $providerMessageId) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<int, WaWebhookLog>
     */
    private function candidateInboundLogs(WaChatMessage $chatMessage, string $providerMessageId): array
    {
        $logs = WaWebhookLog::query()
            ->where('owner_id', $chatMessage->owner_id)
            ->where('event_type', 'ycloud_whatsapp.inbound_message.received')
            ->where('message', $providerMessageId)
            ->latest('id')
            ->get()
            ->all();

        if ($logs !== []) {
            return $logs;
        }

        $windowStart = $chatMessage->created_at?->copy()->subDay();
        $windowEnd = $chatMessage->created_at?->copy()->addDay();

        $query = WaWebhookLog::query()
            ->where('owner_id', $chatMessage->owner_id)
            ->where('event_type', 'ycloud_whatsapp.inbound_message.received')
            ->latest('id');

        if ($windowStart instanceof Carbon && $windowEnd instanceof Carbon) {
            $query->whereBetween('created_at', [$windowStart, $windowEnd]);
        }

        return $query->limit(250)->get()->all();
    }

    /**
     * @return array{type: null, path: null, mime: null, filename: null}
     */
    private function emptyMedia(): array
    {
        return [
            'type' => null,
            'path' => null,
            'mime' => null,
            'filename' => null,
        ];
    }

    private function defaultMediaFilename(string $messageType, string $providerMediaId, string $mimeType): string
    {
        $extension = $this->resolveMediaExtension($messageType, $mimeType, '');
        $baseName = $providerMediaId !== '' ? $providerMediaId : Str::random(16);

        return $extension !== '' ? $baseName.'.'.$extension : $baseName;
    }

    private function downloadInboundMedia(
        string $mediaLink,
        string $messageType,
        string $filename,
        string $providerMediaId,
        string $providerMessageId,
        string $mimeType,
        ?Carbon $createdAt,
        ?string $apiKey = null,
    ): ?string {
        try {
            $request = Http::timeout(30);

            $resolvedApiKey = trim((string) $apiKey);
            if ($resolvedApiKey !== '') {
                $request = $request->withHeaders([
                    'X-API-Key' => $resolvedApiKey,
                ]);
            }

            $response = $request->get($mediaLink);
            if (! $response->successful()) {
                Log::warning('YCloud media download gagal', [
                    'status' => $response->status(),
                    'message_type' => $messageType,
                    'provider_media_id' => $providerMediaId,
                    'provider_message_id' => $providerMessageId,
                ]);

                return null;
            }

            $resolvedMime = trim((string) $response->header('Content-Type', $mimeType));
            $extension = $this->resolveMediaExtension($messageType, $resolvedMime, $filename);
            $safeFilename = $this->sanitizeFilename($filename, $providerMediaId, $extension);
            $directory = 'wa-media/ycloud/'.($createdAt?->format('Y/m') ?? now()->format('Y/m'));
            $relativePath = $directory.'/'.($providerMessageId !== '' ? $providerMessageId.'-' : '').$safeFilename;

            Storage::disk('public')->put($relativePath, $response->body());

            return $relativePath;
        } catch (\Throwable $exception) {
            Log::warning('YCloud media download exception', [
                'error' => $exception->getMessage(),
                'message_type' => $messageType,
                'provider_media_id' => $providerMediaId,
                'provider_message_id' => $providerMessageId,
            ]);

            return null;
        }
    }

    private function resolveApiKeyForChatMessage(WaChatMessage $chatMessage): ?string
    {
        return TenantSettings::query()
            ->where('user_id', $chatMessage->owner_id)
            ->value('ycloud_api_key');
    }

    private function sanitizeFilename(string $filename, string $providerMediaId, string $extension): string
    {
        $originalName = trim(pathinfo($filename, PATHINFO_FILENAME));
        $safeName = Str::slug($originalName, '-');
        if ($safeName === '') {
            $safeName = $providerMediaId !== '' ? $providerMediaId : Str::random(16);
        }

        $resolvedExtension = trim($extension);
        if ($resolvedExtension === '') {
            $resolvedExtension = trim(pathinfo($filename, PATHINFO_EXTENSION));
        }

        return $resolvedExtension !== ''
            ? $safeName.'.'.Str::lower($resolvedExtension)
            : $safeName;
    }

    private function resolveMediaExtension(string $messageType, string $mimeType, string $filename): string
    {
        $filenameExtension = trim(pathinfo($filename, PATHINFO_EXTENSION));
        if ($filenameExtension !== '') {
            return Str::lower($filenameExtension);
        }

        $mimeExtension = match (Str::lower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'video/mp4' => 'mp4',
            default => '',
        };

        if ($mimeExtension !== '') {
            return $mimeExtension;
        }

        return match ($messageType) {
            'image' => 'jpg',
            'document' => 'bin',
            'audio' => 'ogg',
            'video' => 'mp4',
            default => 'bin',
        };
    }
}
