<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class MetaWhatsAppCloudApiService
{
    public function __construct(
        private ?string $accessToken = null,
        private ?string $phoneNumberId = null,
        private ?string $apiVersion = null,
    ) {
        $this->accessToken = trim((string) ($this->accessToken ?? config('services.meta_whatsapp.access_token', '')));
        $this->phoneNumberId = trim((string) ($this->phoneNumberId ?? config('services.meta_whatsapp.phone_number_id', '')));
        $this->apiVersion = trim((string) ($this->apiVersion ?? config('services.meta_whatsapp.api_version', 'v23.0')));
    }

    public function isConfigured(): bool
    {
        return $this->accessToken !== '' && $this->phoneNumberId !== '';
    }

    /**
     * @return array{ok: bool, status: int, message: string, data: array<mixed>, recipient: string}
     */
    public function sendTextMessage(string $to, string $body, bool $previewUrl = false): array
    {
        $recipient = $this->normalizeRecipient($to);

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => 'Meta WhatsApp Cloud API belum dikonfigurasi.',
                'data' => [],
                'recipient' => $recipient,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->withToken($this->accessToken)
                ->post($this->endpoint(), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => $previewUrl,
                        'body' => $body,
                    ],
                ]);

            $responseData = $response->json();

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Message queued.' : 'Gagal mengirim message ke Meta.',
                'data' => is_array($responseData) ? $responseData : [],
                'recipient' => $recipient,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'status' => 0,
                'message' => $exception->getMessage(),
                'data' => [],
                'recipient' => $recipient,
            ];
        }
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );
    }

    private function normalizeRecipient(string $phone): string
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
}
