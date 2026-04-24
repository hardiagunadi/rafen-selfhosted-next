<?php

namespace App\Services;

use App\Models\TenantSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

class YCloudWhatsAppService
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $phoneNumberId = null,
        private ?string $baseUrl = null,
        private ?string $wabaId = null,
        private ?string $businessNumber = null,
    ) {
        $this->apiKey = trim((string) ($this->apiKey ?? ''));
        $this->phoneNumberId = trim((string) ($this->phoneNumberId ?? ''));
        $this->baseUrl = rtrim((string) ($this->baseUrl ?? config('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2')), '/');
        $this->wabaId = trim((string) ($this->wabaId ?? ''));
        $this->businessNumber = trim((string) ($this->businessNumber ?? ''));
    }

    public static function forTenant(TenantSettings $settings): ?self
    {
        $service = new self(
            apiKey: trim((string) ($settings->ycloud_api_key ?? '')),
            phoneNumberId: trim((string) ($settings->ycloud_phone_number_id ?? '')),
            baseUrl: (string) config('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2'),
            wabaId: trim((string) ($settings->ycloud_waba_id ?? '')),
            businessNumber: trim((string) ($settings->ycloud_business_number ?? '')),
        );

        return $service->isConfigured() ? $service : null;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->phoneNumberId !== '';
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{ok: bool, status: int, message: string, data: array<mixed>, phone_numbers: array<int, array{id: string, phone_number: string, waba_id: string, verified_name: string, status: string}>}
     */
    public function listPhoneNumbers(?string $wabaId = null): array
    {
        if (! $this->hasApiKey()) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'API key YCloud belum diisi.',
                'data' => [],
                'phone_numbers' => [],
            ];
        }

        $resolvedWabaId = trim((string) ($wabaId ?? $this->wabaId));
        $query = array_filter([
            'filter.wabaId' => $resolvedWabaId !== '' ? $resolvedWabaId : null,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->withHeaders($this->buildHeaders())
                ->get($this->baseUrl.'/whatsapp/phoneNumbers', $query);

            $responseData = $response->json();
            $data = is_array($responseData) ? $responseData : [];

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Daftar nomor YCloud berhasil diambil.'
                    : $this->resolveErrorMessage($data, 'Gagal mengambil daftar nomor YCloud.'),
                'data' => $data,
                'phone_numbers' => $this->extractPhoneNumbers($data),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => $exception->getMessage(),
                'data' => [],
                'phone_numbers' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, status: int, message: string, recipient: string, data: array<mixed>, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}
     */
    public function sendTextMessage(string $to, string $body, bool $previewUrl = false): array
    {
        return $this->postMessage([
            'from' => $this->resolveFromValue(),
            'to' => $this->normalizeRecipient($to),
            'type' => 'text',
            'text' => [
                'body' => $body,
                'preview_url' => $previewUrl,
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{ok: bool, status: int, message: string, recipient: string, data: array<mixed>, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}
     */
    public function sendTemplateMessage(string $to, string $templateName, string $language = 'id', array $components = []): array
    {
        return $this->postMessage([
            'from' => $this->resolveFromValue(),
            'to' => $this->normalizeRecipient($to),
            'type' => 'template',
            'template' => array_filter([
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components !== [] ? $components : null,
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * @return array{ok: bool, status: int, message: string, recipient: string, data: array<mixed>, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}
     */
    public function sendImageMessage(string $to, string $imageUrl, string $caption = ''): array
    {
        return $this->postMessage([
            'from' => $this->resolveFromValue(),
            'to' => $this->normalizeRecipient($to),
            'type' => 'image',
            'image' => array_filter([
                'link' => $imageUrl,
                'caption' => $caption !== '' ? $caption : null,
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }

    public function markAsRead(string $inboundMessageId): bool
    {
        if (! $this->isConfigured() || trim($inboundMessageId) === '') {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->baseUrl.'/whatsapp/inboundMessages/'.urlencode($inboundMessageId).'/markAsRead');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function showTypingIndicator(string $inboundMessageId): bool
    {
        if (! $this->isConfigured() || trim($inboundMessageId) === '') {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->baseUrl.'/whatsapp/inboundMessages/'.urlencode($inboundMessageId).'/typingIndicator');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function normalizeRecipient(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if ($normalized === '') {
            return $normalized;
        }

        if (str_starts_with($normalized, '0')) {
            return '62'.substr($normalized, 1);
        }

        if (! str_starts_with($normalized, '62')) {
            return '62'.$normalized;
        }

        return $normalized;
    }

    public function defaultTemplateNameForKey(string $templateKey): string
    {
        return match ($templateKey) {
            'registration' => 'registration_utility',
            'invoice_created' => 'invoice_created_utility',
            'invoice_paid' => 'invoice_paid_utility',
            'on_process' => 'on_process_utility',
            'voucher_code' => 'voucher_code_utility',
            default => 'utility_template',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int, message: string, recipient: string, data: array<mixed>, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}
     */
    private function postMessage(array $payload): array
    {
        $recipient = (string) ($payload['to'] ?? '');

        if (! $this->isConfigured()) {
            return $this->failedResponse($recipient, 'YCloud WhatsApp belum dikonfigurasi.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->withHeaders($this->buildHeaders())
                ->post($this->baseUrl.'/whatsapp/messages/sendDirectly', $payload);

            $responseData = $response->json();
            $data = is_array($responseData) ? $responseData : [];

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Message queued.'
                    : $this->resolveErrorMessage($data, 'Gagal mengirim message ke YCloud.'),
                'recipient' => $recipient,
                'data' => $data,
                'provider_message_id' => $this->resolveProviderMessageId($data),
                'delivery_status' => $this->resolveDeliveryStatus($data, $response->successful()),
                'pricing_metadata' => is_array(data_get($data, 'pricing_analytics'))
                    ? data_get($data, 'pricing_analytics')
                    : (is_array(data_get($data, 'pricingAnalytics')) ? data_get($data, 'pricingAnalytics') : []),
            ];
        } catch (Throwable $exception) {
            return $this->failedResponse($recipient, $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->apiKey,
        ];
    }

    private function resolveFromValue(): string
    {
        return $this->phoneNumberId;
    }

    /**
     * @return array<int, array{id: string, phone_number: string, waba_id: string, verified_name: string, status: string}>
     */
    private function extractPhoneNumbers(array $data): array
    {
        $items = data_get($data, 'data', []);
        if (! is_array($items)) {
            return [];
        }

        $phoneNumbers = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $phoneNumbers[] = [
                'id' => (string) ($item['id'] ?? ''),
                'phone_number' => (string) ($item['displayPhoneNumber'] ?? $item['phoneNumber'] ?? ''),
                'waba_id' => (string) data_get($item, 'wabaId', ''),
                'verified_name' => (string) ($item['verifiedName'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
            ];
        }

        return array_values(array_filter($phoneNumbers, fn (array $item): bool => $item['id'] !== ''));
    }

    private function resolveProviderMessageId(array $data): ?string
    {
        $messageId = data_get($data, 'whatsappMessage.id')
            ?? data_get($data, 'message.id')
            ?? data_get($data, 'id');

        $resolved = trim((string) $messageId);

        return $resolved !== '' ? $resolved : null;
    }

    private function resolveDeliveryStatus(array $data, bool $successful): ?string
    {
        $status = data_get($data, 'whatsappMessage.status')
            ?? data_get($data, 'message.status')
            ?? data_get($data, 'status');

        $resolved = trim((string) $status);

        if ($resolved !== '') {
            return $resolved;
        }

        return $successful ? 'accepted' : null;
    }

    /**
     * @return array{ok: bool, status: int, message: string, recipient: string, data: array<mixed>, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}
     */
    private function failedResponse(string $recipient, string $message): array
    {
        return [
            'ok' => false,
            'status' => 0,
            'message' => $message,
            'recipient' => $recipient,
            'data' => [],
            'provider_message_id' => null,
            'delivery_status' => null,
            'pricing_metadata' => [],
        ];
    }

    private function resolveErrorMessage(array $data, string $defaultMessage): string
    {
        $candidates = [
            data_get($data, 'error.message'),
            data_get($data, 'error.whatsappApiError.message'),
            data_get($data, 'error.whatsappApiError.error_user_msg'),
            data_get($data, 'message'),
        ];

        foreach ($candidates as $candidate) {
            $message = trim((string) $candidate);
            if ($message !== '') {
                return $message;
            }
        }

        return $defaultMessage;
    }
}
