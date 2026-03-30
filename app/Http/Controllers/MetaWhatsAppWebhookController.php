<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetaWhatsAppWebhookRequest;
use App\Models\WaWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub.mode') ?? $request->query('hub_mode') ?? '');
        $token = (string) ($request->query('hub.verify_token') ?? $request->query('hub_verify_token') ?? '');
        $challenge = (string) ($request->query('hub.challenge') ?? $request->query('hub_challenge') ?? '');
        $expectedToken = trim((string) config('services.meta_whatsapp.webhook_verify_token', ''));

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $token) && $challenge !== '') {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403)->header('Content-Type', 'text/plain');
    }

    public function receive(MetaWhatsAppWebhookRequest $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['status' => false, 'message' => 'Invalid signature.'], 401);
        }

        $payload = $request->validated();
        $this->persistPayloadEntries($payload);

        return response()->json(['status' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistPayloadEntries(array $payload): void
    {
        foreach ($payload['entry'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');

                foreach (($value['messages'] ?? []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    WaWebhookLog::create([
                        'event_type' => 'meta_message',
                        'session_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                        'sender' => data_get($message, 'from'),
                        'message' => $this->resolveMessageBody($message),
                        'status' => data_get($message, 'type'),
                        'payload' => $change,
                    ]);
                }

                foreach (($value['statuses'] ?? []) as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    WaWebhookLog::create([
                        'event_type' => 'meta_status',
                        'session_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                        'sender' => data_get($status, 'recipient_id'),
                        'message' => data_get($status, 'id'),
                        'status' => data_get($status, 'status'),
                        'payload' => $change,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveMessageBody(array $message): string
    {
        $textBody = trim((string) data_get($message, 'text.body', ''));
        if ($textBody !== '') {
            return mb_substr($textBody, 0, 1000);
        }

        $buttonText = trim((string) data_get($message, 'button.text', ''));
        if ($buttonText !== '') {
            return mb_substr($buttonText, 0, 1000);
        }

        $interactiveTitle = trim((string) data_get($message, 'interactive.button_reply.title', ''));
        if ($interactiveTitle !== '') {
            return mb_substr($interactiveTitle, 0, 1000);
        }

        $caption = trim((string) data_get($message, 'image.caption', ''));
        if ($caption !== '') {
            return mb_substr($caption, 0, 1000);
        }

        $type = trim((string) ($message['type'] ?? 'unknown'));

        return $type !== '' ? '['.$type.']' : '[unknown]';
    }

    private function hasValidSignature(Request $request): bool
    {
        $appSecret = trim((string) config('services.meta_whatsapp.app_secret', ''));
        if ($appSecret === '') {
            return true;
        }

        $header = trim((string) $request->header('X-Hub-Signature-256', ''));
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $incomingSignature = substr($header, strlen('sha256='));
        if ($incomingSignature === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $incomingSignature);
    }
}
