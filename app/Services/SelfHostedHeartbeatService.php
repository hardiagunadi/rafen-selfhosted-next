<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;
use RuntimeException;

class SelfHostedHeartbeatService
{
    public function __construct(
        private readonly LicenseActivationRequestService $licenseActivationRequestService,
        private readonly SelfHostedUpdateStatusService $updateStatusService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function makePayload(bool $refreshUpdateState = false): array
    {
        $basePayload = $this->licenseActivationRequestService->makePayload();
        $snapshot = $refreshUpdateState
            ? $this->updateStatusService->check()
            : $this->updateStatusService->snapshot();

        return array_merge($basePayload, [
            'update_channel' => $snapshot['channel'] ?? null,
            'current_version' => $snapshot['current_version'] ?? null,
            'current_commit' => $snapshot['current_commit'] ?? null,
            'current_ref' => $snapshot['current_ref'] ?? null,
            'latest_version' => $snapshot['latest_version'] ?? null,
            'latest_commit' => $snapshot['latest_commit'] ?? null,
            'latest_ref' => $snapshot['latest_ref'] ?? null,
            'manifest_url' => $snapshot['latest_manifest_url'] ?? $snapshot['manifest_url'] ?? null,
            'update_available' => (bool) ($snapshot['update_available'] ?? false),
            'last_checked_at' => $this->isoDateTime($snapshot['last_checked_at'] ?? null),
            'last_check_status' => $snapshot['last_check_status'] ?? null,
            'last_check_message' => $snapshot['last_check_message'] ?? null,
            'last_applied_at' => $this->isoDateTime($snapshot['last_applied_at'] ?? null),
            'last_apply_status' => $snapshot['last_apply_status'] ?? null,
            'last_apply_message' => $snapshot['last_apply_message'] ?? null,
            'rollback_ref' => $snapshot['rollback_ref'] ?? null,
        ]);
    }

    /**
     * @return array{payload: array<string, mixed>, response: mixed, url: string}
     */
    public function submit(bool $refreshUpdateState = false): array
    {
        $url = $this->resolveHeartbeatUrl();
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        if ($url === '' || $token === '') {
            $message = 'SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi. Jalur heartbeat ke SaaS belum siap.';
            $this->updateStatusService->recordHeartbeat('not_configured', $message);

            throw new RuntimeException($message);
        }

        $payload = $this->makePayload($refreshUpdateState);

        try {
            $response = Http::timeout(20)
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->failed()) {
                $message = $response->json('message') ?? $response->body();

                throw new RuntimeException('HTTP '.$response->status().' '.$message);
            }
        } catch (RuntimeException $exception) {
            $message = 'Heartbeat self-hosted gagal dikirim: '.$exception->getMessage();
            $this->updateStatusService->recordHeartbeat('failed', $message);

            throw new RuntimeException($message, 0, $exception);
        } catch (Throwable $exception) {
            $message = 'Heartbeat self-hosted gagal dikirim: '.$exception->getMessage();
            $this->updateStatusService->recordHeartbeat('failed', $message);

            throw new RuntimeException($message, 0, $exception);
        }

        $responsePayload = $response->json();
        $statusId = is_numeric($response->json('status_id'))
            ? (int) $response->json('status_id')
            : null;

        $this->updateStatusService->recordHeartbeat(
            status: 'success',
            message: 'Heartbeat status instance berhasil dikirim ke SaaS.',
            statusId: $statusId,
            response: is_array($responsePayload) ? $responsePayload : null,
        );

        return [
            'payload' => $payload,
            'response' => $responsePayload,
            'url' => $url,
        ];
    }

    /**
     * @return array{is_sent: bool, is_configured: bool, message: string|null, response?: mixed, url?: string}
     */
    public function submitBestEffort(bool $refreshUpdateState = false): array
    {
        try {
            $result = $this->submit($refreshUpdateState);
        } catch (RuntimeException $exception) {
            return [
                'is_sent' => false,
                'is_configured' => $this->resolveHeartbeatUrl() !== '' && trim((string) config('services.self_hosted_registry.token', '')) !== '',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'is_sent' => true,
            'is_configured' => true,
            'message' => null,
            'response' => $result['response'],
            'url' => $result['url'],
        ];
    }

    /**
     * @return array{
     *     endpoint: string|null,
     *     is_configured: bool,
     *     last_sent_at: mixed,
     *     last_successful_sent_at: mixed,
     *     last_status: string,
     *     last_message: string,
     *     last_status_id: mixed,
     *     is_stale: bool,
     *     stale_after_minutes: int,
     *     stale_reason: string|null
     * }
     */
    public function summary(): array
    {
        $snapshot = $this->updateStatusService->snapshot();
        $endpoint = $this->resolveHeartbeatUrl();
        $isConfigured = $endpoint !== '' && trim((string) config('services.self_hosted_registry.token', '')) !== '';
        $lastSentAt = $this->asCarbon($snapshot['last_heartbeat_at'] ?? null);
        $lastSuccessfulSentAt = $this->asCarbon($snapshot['last_successful_heartbeat_at'] ?? null);
        $staleAfterMinutes = max(1, (int) config('services.self_hosted_registry.heartbeat_stale_after_minutes', 60));
        $isStale = $isConfigured && (
            $lastSuccessfulSentAt === null
            || $lastSuccessfulSentAt->lt(now()->subMinutes($staleAfterMinutes))
        );

        return [
            'endpoint' => $endpoint !== '' ? $endpoint : null,
            'is_configured' => $isConfigured,
            'last_sent_at' => $lastSentAt,
            'last_successful_sent_at' => $lastSuccessfulSentAt,
            'last_status' => (string) ($snapshot['last_heartbeat_status'] ?? ($isConfigured ? 'never' : 'not_configured')),
            'last_message' => (string) ($snapshot['last_heartbeat_message'] ?? ($isConfigured
                ? 'Belum pernah mengirim heartbeat status ke SaaS.'
                : 'SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.')),
            'last_status_id' => $snapshot['last_heartbeat_status_id'] ?? null,
            'is_stale' => $isStale,
            'stale_after_minutes' => $staleAfterMinutes,
            'stale_reason' => $this->staleReason(
                isConfigured: $isConfigured,
                isStale: $isStale,
                lastSuccessfulSentAt: $lastSuccessfulSentAt,
                staleAfterMinutes: $staleAfterMinutes,
            ),
        ];
    }

    public function resolveHeartbeatUrl(): string
    {
        $registryUrl = trim((string) config('services.self_hosted_registry.url', ''));

        if ($registryUrl === '') {
            return '';
        }

        if (preg_match('#/heartbeats/?$#', $registryUrl) === 1) {
            return $registryUrl;
        }

        $rewrittenUrl = preg_replace(
            '#/install-registrations/?$#',
            '/heartbeats',
            $registryUrl,
            1,
            $replacementCount
        );

        if ($replacementCount === 1 && is_string($rewrittenUrl)) {
            return $rewrittenUrl;
        }

        return '';
    }

    private function isoDateTime(mixed $value): ?string
    {
        $value = $this->asCarbon($value);

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return null;
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return null;
    }

    private function staleReason(
        bool $isConfigured,
        bool $isStale,
        ?Carbon $lastSuccessfulSentAt,
        int $staleAfterMinutes,
    ): ?string {
        if (! $isConfigured || ! $isStale) {
            return null;
        }

        if ($lastSuccessfulSentAt === null) {
            return 'Belum ada heartbeat sukses yang pernah diterima SaaS untuk instance ini.';
        }

        return 'Heartbeat sukses terakhir lebih lama dari '.$staleAfterMinutes.' menit.';
    }
}
