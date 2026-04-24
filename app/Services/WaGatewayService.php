<?php

namespace App\Services;

use App\Models\TenantSettings;
use App\Models\WaBlastLog;
use App\Models\WaMultiSessionDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    private const STICKY_SENDER_TTL_DAYS = 30;

    /** Delay in milliseconds between messages (anti-spam) */
    private int $delayMs = 0;

    /** Minimal interval antar pengiriman per tenant untuk antrean lintas proses */
    private int $dispatchIntervalMs = 1200;

    /** Minimum blast delay floor — tidak boleh di bawah ini agar tidak spam */
    private const BLAST_DELAY_FLOOR_MS = 2000;

    /** Max messages per minute (0 = unlimited) */
    private int $maxPerMinute = 0;

    /** Counter for rate limiting */
    private int $sentThisMinute = 0;

    private float $minuteStart = 0;

    /**
     * Append random invisible characters at end of each message so identical
     * template messages sent to many recipients produce unique hashes, helping
     * bypass WhatsApp's duplicate-content detection.
     *
     * Technique: combinations of Unicode zero-width characters (U+200B, U+200C,
     * U+200D) are invisible to recipients but make the raw string unique.
     */
    private bool $randomize = false;

    private bool $blastMultiDevice = true;

    private bool $blastNaturalVariation = true;

    private int $blastDelayMinMs = 2000;

    private int $blastDelayMaxMs = 4000;

    private ?int $ownerId = null;

    private ?int $sentById = null;

    private ?string $sentByName = null;

    private ?string $sessionId = null;

    private ?int $platformDeviceId = null;

    public function __construct(
        private string $url,
        private string $token,
        private string $key = ''
    ) {
        $this->minuteStart = microtime(true);
    }

    public static function forTenant(TenantSettings $settings): ?self
    {
        $gatewayUrl = trim((string) config('wa.multi_session.public_url', ''));
        if ($gatewayUrl === '') {
            $gatewayUrl = trim((string) ($settings->wa_gateway_url ?? ''));
        }

        if ($gatewayUrl === '') {
            return null;
        }

        $token = trim((string) config('wa.multi_session.auth_token', ''));
        $key = trim((string) config('wa.multi_session.master_key', ''));

        if ($token === '') {
            $token = trim((string) ($settings->wa_gateway_token ?? ''));
            $key = trim((string) ($settings->wa_gateway_key ?? ''));
        }

        if ($token === '') {
            return null;
        }

        $instance = new self(
            rtrim($gatewayUrl, '/'),
            $token,
            $key
        );

        $configuredDelayMs = max(0, (int) ($settings->wa_antispam_delay_ms ?? 1200));
        $instance->dispatchIntervalMs = max(900, $configuredDelayMs > 0 ? $configuredDelayMs : 1200);

        if ($settings->wa_antispam_enabled) {
            $instance->delayMs = $configuredDelayMs;
            $instance->maxPerMinute = max(0, (int) ($settings->wa_antispam_max_per_minute ?? 20));
        }

        $instance->randomize = (bool) ($settings->wa_msg_randomize ?? true);
        $instance->blastMultiDevice = (bool) ($settings->wa_blast_multi_device ?? true);
        $instance->blastNaturalVariation = (bool) ($settings->wa_blast_message_variation ?? true);
        // Blast delay tidak boleh lebih kecil dari antispam delay maupun BLAST_DELAY_FLOOR_MS.
        // Ini berlaku terlepas dari jumlah device — multi-device tidak boleh mempercepat pengiriman.
        $blastMinFloor = max(self::BLAST_DELAY_FLOOR_MS, $configuredDelayMs);
        $savedMin = (int) ($settings->wa_blast_delay_min_ms ?? 0);
        $savedMax = (int) ($settings->wa_blast_delay_max_ms ?? 0);
        $instance->blastDelayMinMs = max($blastMinFloor, $savedMin > 0 ? $savedMin : $blastMinFloor);
        $instance->blastDelayMaxMs = max($instance->blastDelayMinMs + 1000, $savedMax > 0 ? $savedMax : ($instance->blastDelayMinMs + 1200));
        $instance->ownerId = $settings->user_id ?? null;

        // Jika tenant sudah di-approve untuk pakai platform device, gunakan session-nya
        if (! empty($settings->wa_platform_device_id)) {
            $platformDevice = WaMultiSessionDevice::find($settings->wa_platform_device_id);
            if ($platformDevice && $platformDevice->is_active) {
                $instance->platformDeviceId = (int) $platformDevice->id;
                $instance->sessionId = $platformDevice->session_id;
            } else {
                $instance->sessionId = $instance->resolveDefaultTenantSession();
            }
        } else {
            $instance->sessionId = $instance->resolveDefaultTenantSession();
        }

        // Auto-set sent_by dari user yang sedang login (jika ada)
        if ($authUser = auth()->user()) {
            $instance->sentById = $authUser->id;
            $instance->sentByName = $authUser->name;
        }

        return $instance;
    }

    /**
     * Buat WaGatewayService menggunakan kredensial global dari .env (WA_MULTI_SESSION_*).
     * Digunakan untuk notifikasi platform-level seperti pendaftaran tenant baru.
     * Session diambil dari device default milik super admin, atau fallback ke device
     * pertama yang aktif di sistem.
     */
    public static function forSuperAdmin(): ?self
    {
        $gatewayUrl = trim((string) config('wa.multi_session.public_url', ''));
        $token = trim((string) config('wa.multi_session.auth_token', ''));
        $key = trim((string) config('wa.multi_session.master_key', ''));

        if ($gatewayUrl === '' || $token === '') {
            return null;
        }

        $instance = new self($gatewayUrl, $token, $key);
        $instance->randomize = true;

        // Hanya gunakan device yang ditandai is_platform_device=true oleh super admin.
        // Tidak boleh fallback ke device milik tenant untuk menghindari penyalahgunaan session.
        $candidates = WaMultiSessionDevice::query()
            ->where('is_platform_device', true)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->get();

        if ($candidates->isEmpty()) {
            // Belum ada platform device yang dikonfigurasi
            $instance->sessionId = null;

            return $instance;
        }

        $device = null;
        $baseUrl = rtrim(config('wa.multi_session.host', '127.0.0.1'), '/');
        $port = (int) config('wa.multi_session.port', 3100);

        foreach ($candidates as $candidate) {
            try {
                $resp = Http::timeout(3)
                    ->withToken($token)
                    ->get("http://{$baseUrl}:{$port}/api/v2/sessions/status", ['session' => $candidate->session_id]);

                $status = $resp->json('data.status', 'unknown');

                if (in_array($status, ['connected', 'idle'], true)) {
                    $device = $candidate;
                    break;
                }
            } catch (\Throwable) {
                // skip, coba kandidat berikutnya
            }
        }

        // Fallback ke platform device pertama jika semua tidak bisa dicek statusnya
        if (! $device) {
            $device = $candidates->first();
        }

        $instance->sessionId = $device?->session_id;

        return $instance;
    }

    public function setSessionId(?string $sessionId): self
    {
        $trimmed = trim((string) $sessionId);
        $this->sessionId = $trimmed !== '' ? $trimmed : null;

        return $this;
    }

    /**
     * Build request headers — Authorization token is required, key is optional.
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (! empty($this->token)) {
            $headers['Authorization'] = $this->token;
        }

        if (! empty($this->key)) {
            $headers['key'] = $this->key;
        }

        if (! empty($this->sessionId)) {
            $headers['X-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }

    /**
     * Apply anti-spam rate limit before sending a message.
     */
    private function applyAntiSpamDelay(): void
    {
        // Rate limit: max messages per minute
        if ($this->maxPerMinute > 0) {
            $elapsed = microtime(true) - $this->minuteStart;

            if ($elapsed >= 60) {
                $this->sentThisMinute = 0;
                $this->minuteStart = microtime(true);
            } elseif ($this->sentThisMinute >= $this->maxPerMinute) {
                $waitSeconds = (int) ceil(60 - $elapsed);
                Log::info("WA Anti-Spam: rate limit reached ({$this->maxPerMinute}/min), waiting {$waitSeconds}s");
                sleep($waitSeconds);
                $this->sentThisMinute = 0;
                $this->minuteStart = microtime(true);
            }
        }
    }

    private function applyDispatchQueueDelay(array $context = []): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($this->ownerId === null) {
            return;
        }

        $lockPath = storage_path('framework/cache/wa-dispatch-'.$this->ownerId.'.lock');
        $lockDir = dirname($lockPath);

        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            $nowMs = $this->currentTimeMs();
            rewind($handle);
            $raw = stream_get_contents($handle);
            $nextAllowedMs = is_string($raw) && is_numeric(trim($raw)) ? (int) trim($raw) : 0;

            if ($nextAllowedMs > $nowMs) {
                $waitMs = $nextAllowedMs - $nowMs;
                Log::info('WA queue: waiting dispatch slot', [
                    'owner_id' => $this->ownerId,
                    'wait_ms' => $waitMs,
                    'event' => $context['event'] ?? null,
                ]);
                usleep($waitMs * 1000);
                $nowMs = $this->currentTimeMs();
            }

            $jitterMs = 250;
            try {
                $jitterMs = random_int(120, 520);
            } catch (\Throwable) {
                $jitterMs = 250;
            }

            $reserveUntilMs = $nowMs + $this->resolveDispatchIntervalMs() + $jitterMs;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $reserveUntilMs);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function resolveDispatchIntervalMs(): int
    {
        if ($this->dispatchIntervalMs > 0) {
            return $this->dispatchIntervalMs;
        }

        if ($this->delayMs > 0) {
            return max(900, $this->delayMs);
        }

        return 1200;
    }

    private function currentTimeMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * Append a unique invisible suffix made of zero-width Unicode characters.
     *
     * Uses U+200B (ZWSP), U+200C (ZWNJ), U+200D (ZWJ) as a base-3 encoding of
     * a random 16-bit value → produces 10 invisible chars that look identical to
     * the user but yield a different byte sequence (and thus a different hash)
     * for every recipient.
     */
    private function appendRandomRef(string $message): string
    {
        $zwChars = ["\u{200B}", "\u{200C}", "\u{200D}"];
        $value = random_int(0, 59048); // 3^10 - 1

        $suffix = '';
        for ($i = 0; $i < 10; $i++) {
            $suffix .= $zwChars[$value % 3];
            $value = (int) ($value / 3);
        }

        return $message.$suffix;
    }

    /**
     * Normalize phone number to Indonesian format (62xxx)
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Validate that a normalized phone number looks like a valid WA number.
     * Rules: starts with 62, total length 10–15 digits, digits only.
     */
    public function isValidPhone(string $normalized): bool
    {
        return (bool) preg_match('/^62\d{8,13}$/', $normalized);
    }

    /**
     * Send a single WhatsApp message.
     */
    public function sendMessage(string $phone, string $message, array $context = []): bool
    {
        // Simpan teks pesan asli (sebelum randomize) ke context agar tercatat di log
        $context['message'] = mb_substr($message, 0, 4000);

        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', $context);
            $this->writeLog('skip', '', '', $context, 'Nomor HP kosong');

            return false;
        }

        if (trim($this->token) === '') {
            $reason = 'Token perangkat WA belum diisi.';
            Log::warning('WA skip: token perangkat kosong', $context);
            $this->writeLog('failed', $phone, '', $context, $reason);

            return false;
        }

        $normalized = $this->normalizePhone($phone);

        if (! $this->isValidPhone($normalized)) {
            $reason = 'Format nomor tidak valid sebagai nomor WA (harus 62xxxxxxxx, 10-15 digit)';
            Log::info('WA skip: format nomor tidak valid sebagai nomor WA', array_merge($context, [
                'phone_raw' => $phone,
                'phone_normalized' => $normalized,
                'reason' => $reason,
            ]));
            $this->writeLog('skip', $phone, $normalized, $context, $reason);

            return false;
        }

        $phone = $normalized;
        $selectedSessionId = $this->resolveSessionId($context);
        $contextSessionId = trim((string) ($context['session_id'] ?? ''));

        if ($contextSessionId === '') {
            $stickySessionId = $this->resolveStickySessionForPhone($phone);
            if ($stickySessionId !== null) {
                $selectedSessionId = $stickySessionId;
            }
        }

        // Bot/auto-reply tidak perlu antrian dispatch — respons harus instan
        $instantEvents = ['bot_reply', 'auto_reply_outbound'];
        if (! in_array($context['event'] ?? null, $instantEvents, true)) {
            $this->applyDispatchQueueDelay($context);
            $this->applyAntiSpamDelay();
        }

        if ($this->randomize) {
            $message = $this->appendRandomRef($message);
        }

        $contextRefId = trim((string) ($context['ref_id'] ?? ''));
        $refId = $contextRefId !== ''
            ? $contextRefId
            : ('rafen-'.date('YmdHis').'-'.bin2hex(random_bytes(4)));

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.'/api/v2/send-message', [
                    'data' => [
                        [
                            'session' => $selectedSessionId,
                            'phone' => $phone,
                            'message' => $message,
                            'isGroup' => false,
                            'ref_id' => $refId,
                        ],
                    ],
                ]);

            $this->sentThisMinute++;

            if ($response->successful()) {
                $body = $response->json();
                $msgData = $body['data']['messages'][0] ?? [];
                $msgRefId = $msgData['ref_id'] ?? $refId;
                $msgStatus = $msgData['status'] ?? null;
                $statusValue = strtolower((string) $msgStatus);
                $isGatewayStatusOk = (bool) ($body['status'] ?? false);
                // 'queued' = gateway v2 menerima dan akan mengirim (sukses)
                $isMessageFailed = in_array($statusValue, ['failed', 'error', 'undelivered'], true);

                if ($isGatewayStatusOk && ! $isMessageFailed) {
                    Log::info('WA sent', array_merge($context, [
                        'phone' => $phone,
                        'session_id' => $selectedSessionId,
                        'ref_id' => $msgRefId,
                        'msg_status' => $msgStatus,
                        'note' => 'Gateway hanya konfirmasi pesan diterima server (fire-and-forget). Delivery ke perangkat tidak dapat dikonfirmasi — nomor mungkin tidak terdaftar WA jika pelanggan tidak menerima.',
                    ]));

                    $this->rememberStickySessionForPhone($phone, $selectedSessionId);
                    $this->writeLog('sent', $phone, $phone, $context, null, $msgRefId);

                    return true;
                }

                // Prioritaskan error detail dari pesan individual, fallback ke body message
                $msgError = trim((string) ($msgData['error'] ?? ''));
                $failureReason = $msgError !== ''
                    ? $msgError
                    : (string) ($body['message'] ?? 'Gateway menolak pengiriman.');
                if ($isMessageFailed && $statusValue !== '' && $msgError === '') {
                    $failureReason = 'Status gateway: '.$statusValue;
                }

                Log::warning('WA Gateway: send-message rejected', array_merge($context, [
                    'phone' => $phone,
                    'ref_id' => $msgRefId,
                    'msg_status' => $msgStatus,
                    'response' => $body,
                ]));
                $this->writeLog('failed', $phone, $phone, $context, $failureReason, $msgRefId);

                return false;
            }

            $errReason = 'HTTP error dari gateway: '.$response->status();
            Log::warning('WA Gateway: send-message HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $phone,
            ]);
            $this->writeLog('failed', $phone, $phone, $context, $errReason, $refId);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WA Gateway: send-message exception', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
            $this->writeLog('failed', $phone, $phone, $context, 'Exception: '.$e->getMessage(), $refId);

            return false;
        }
    }

    /**
     * Write a log entry to wa_blast_logs table.
     */
    private function writeLog(string $status, string $phone, string $phoneNormalized, array $context, ?string $reason, ?string $refId = null): void
    {
        try {
            WaBlastLog::create([
                'owner_id' => $this->ownerId,
                'sent_by_id' => $this->sentById,
                'sent_by_name' => $this->sentByName,
                'event' => $context['event'] ?? 'unknown',
                'provider' => 'local',
                'phone' => $phone ?: null,
                'phone_normalized' => $phoneNormalized ?: null,
                'status' => $status,
                'reason' => $reason,
                'invoice_number' => $context['invoice_number'] ?? null,
                'invoice_id' => $context['invoice_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'username' => $context['username'] ?? null,
                'customer_name' => $context['name'] ?? null,
                'message' => $context['message'] ?? null,
                'ref_id' => $refId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA: gagal menulis wa_blast_logs', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send a WhatsApp image message (public URL) with optional caption.
     */
    public function sendImage(string $phone, string $mediaUrl, string $caption = '', array $context = []): bool
    {
        if (empty(trim($phone))) {
            return false;
        }

        $normalized = $this->normalizePhone($phone);
        if (! $this->isValidPhone($normalized)) {
            return false;
        }

        $instantEvents = ['bot_reply', 'auto_reply_outbound'];
        if (! in_array($context['event'] ?? null, $instantEvents, true)) {
            $this->applyDispatchQueueDelay($context);
            $this->applyAntiSpamDelay();
        }

        $refId = 'rafen-'.date('YmdHis').'-'.bin2hex(random_bytes(4));

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.'/api/v2/send-image', [
                    'data' => [
                        [
                            'session' => $this->resolveSessionId($context),
                            'phone' => $normalized,
                            'media_url' => $mediaUrl,
                            'caption' => $caption,
                            'isGroup' => false,
                            'ref_id' => $refId,
                        ],
                    ],
                ]);

            return $response->successful() && ($response->json('status') === true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Send bulk WhatsApp messages with anti-spam delay.
     *
     * @param  array<array{phone: string, message: string}>  $recipients
     * @return array{success: int, failed: int, results: array}
     */
    public function sendBulk(array $recipients): array
    {
        $success = 0;
        $failed = 0;
        $results = [];
        $sessionProfiles = $this->resolveBlastSessionProfiles();
        $sessionPool = array_values(array_map(
            fn (array $profile): string => (string) $profile['session_id'],
            $sessionProfiles
        ));
        $sessionPoolCount = count($sessionPool);
        $startOffset = $this->resolveBlastStartOffset($sessionPoolCount);
        $sessionCooldownUntil = [];
        $sessionFailureCount = [];
        $sessionWarmupRemaining = [];

        foreach ($sessionProfiles as $profile) {
            if (($profile['warmup_active'] ?? false) === true) {
                $sessionWarmupRemaining[(string) $profile['session_id']] = (int) ($profile['warmup_max_per_batch'] ?? 1);
            }
        }

        $variationBatchSeed = $this->resolveBlastVariationBatchSeed();

        foreach ($recipients as $index => $recipient) {
            $phone = $recipient['phone'] ?? '';
            $normalizedRecipientPhone = $this->normalizePhone((string) $phone);
            $message = $this->applyBlastMessageVariation(
                (string) ($recipient['message'] ?? ''),
                $recipient['name'] ?? null,
                (int) $index,
                $normalizedRecipientPhone,
                $variationBatchSeed
            );
            $preferredStickySessionId = $this->isValidPhone($normalizedRecipientPhone)
                ? $this->resolveStickySessionForPhone($normalizedRecipientPhone)
                : null;
            $context = ['event' => 'blast', 'name' => $recipient['name'] ?? null];

            if (empty($message)) {
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => 'Pesan kosong'];

                continue;
            }

            $sent = false;
            $usedSession = null;
            $sessionCandidates = $this->buildBlastSessionCandidates(
                $sessionPool,
                (int) $index,
                $startOffset,
                $sessionCooldownUntil,
                $sessionWarmupRemaining,
                $preferredStickySessionId
            );

            foreach ($sessionCandidates as $sessionId) {
                $usedSession = $sessionId;
                $sent = $this->sendMessage($phone, $message, array_merge($context, ['session_id' => $sessionId]));

                if ($sent) {
                    $sessionFailureCount[$sessionId] = 0;
                    if (array_key_exists($sessionId, $sessionWarmupRemaining)) {
                        $sessionWarmupRemaining[$sessionId] = max(0, ((int) $sessionWarmupRemaining[$sessionId]) - 1);
                    }
                    break;
                }

                $sessionFailureCount[$sessionId] = ((int) ($sessionFailureCount[$sessionId] ?? 0)) + 1;

                if ($sessionPoolCount > 1) {
                    $cooldownSeconds = min(300, 45 * $sessionFailureCount[$sessionId]);
                    $sessionCooldownUntil[$sessionId] = time() + $cooldownSeconds;

                    Log::warning('WA blast: cooling down failed session', [
                        'session_id' => $sessionId,
                        'owner_id' => $this->ownerId,
                        'cooldown_seconds' => $cooldownSeconds,
                        'failure_count' => $sessionFailureCount[$sessionId],
                    ]);
                }
            }

            if ($sent) {
                $success++;
                $results[] = ['phone' => $this->normalizePhone($phone), 'status' => true, 'session' => $usedSession];
            } else {
                $normalized = $this->normalizePhone($phone);
                $reason = empty(trim($phone))
                    ? 'Nomor HP kosong'
                    : (! $this->isValidPhone($normalized) ? 'Format nomor tidak valid sebagai nomor WA' : 'Gagal terkirim');
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => $reason, 'session' => $usedSession];
            }

            $this->applyBlastInterMessageDelay();
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    private function resolveBlastStartOffset(int $sessionPoolCount): int
    {
        if ($sessionPoolCount < 2) {
            return 0;
        }

        try {
            return random_int(0, $sessionPoolCount - 1);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<int, string>  $sessionPool
     * @param  array<string, int>  $sessionCooldownUntil
     * @param  array<string, int>  $sessionWarmupRemaining
     * @return array<int, string>
     */
    private function buildBlastSessionCandidates(
        array $sessionPool,
        int $recipientIndex,
        int $startOffset,
        array $sessionCooldownUntil,
        array $sessionWarmupRemaining,
        ?string $preferredStickySessionId
    ): array {
        if ($sessionPool === []) {
            return [$this->resolveSessionId()];
        }

        $sessionPoolCount = count($sessionPool);
        $orderedSessions = [];
        for ($attempt = 0; $attempt < $sessionPoolCount; $attempt++) {
            $sessionIndex = ($startOffset + $recipientIndex + $attempt) % $sessionPoolCount;
            $sessionId = trim((string) ($sessionPool[$sessionIndex] ?? ''));

            if ($sessionId !== '') {
                $orderedSessions[] = $sessionId;
            }
        }

        $orderedSessions = array_values(array_unique($orderedSessions));
        if ($orderedSessions === []) {
            return [$this->resolveSessionId()];
        }

        $now = time();
        $availableSessionsByHealth = array_values(array_filter(
            $orderedSessions,
            fn (string $sessionId): bool => ((int) ($sessionCooldownUntil[$sessionId] ?? 0)) <= $now
        ));

        if ($availableSessionsByHealth === []) {
            return $orderedSessions;
        }

        $availableSessions = array_values(array_filter($availableSessionsByHealth, function (string $sessionId) use ($sessionWarmupRemaining): bool {
            if (! array_key_exists($sessionId, $sessionWarmupRemaining)) {
                return true;
            }

            return ((int) $sessionWarmupRemaining[$sessionId]) > 0;
        }));

        $candidates = $availableSessions !== [] ? $availableSessions : $availableSessionsByHealth;
        $stickySessionId = trim((string) $preferredStickySessionId);

        if ($stickySessionId !== '' && in_array($stickySessionId, $candidates, true)) {
            $candidates = array_values(array_unique(array_merge([$stickySessionId], $candidates)));
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function resolveBlastSessionProfiles(): array
    {
        $fallback = [[
            'session_id' => $this->resolveSessionId(),
            'warmup_active' => false,
            'warmup_max_per_batch' => 0,
        ]];

        if ($this->platformDeviceId !== null) {
            $platformDevice = WaMultiSessionDevice::query()
                ->whereKey($this->platformDeviceId)
                ->where('is_active', true)
                ->first(['session_id', 'meta']);

            if (! $platformDevice) {
                return $fallback;
            }

            $sessionId = trim((string) ($platformDevice->session_id ?? ''));
            if ($sessionId === '') {
                return $fallback;
            }

            $warmup = $this->resolveWarmupConfig($platformDevice->meta ?? null);

            return [[
                'session_id' => $sessionId,
                'warmup_active' => $warmup['active'],
                'warmup_max_per_batch' => $warmup['max_per_batch'],
            ]];
        }

        if (! $this->blastMultiDevice || $this->ownerId === null) {
            return $fallback;
        }

        $devices = WaMultiSessionDevice::query()
            ->forOwner($this->ownerId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['session_id', 'meta']);

        if ($devices->count() < 2) {
            return $fallback;
        }

        $connectedSessions = [];
        foreach ($devices as $device) {
            $sessionId = trim((string) ($device->session_id ?? ''));
            if ($sessionId === '') {
                continue;
            }

            $status = $this->sessionStatus($sessionId);
            $connectionStatus = strtolower((string) data_get($status, 'data.status', ''));

            if (($status['status'] ?? false) === true && $connectionStatus === 'connected') {
                $warmup = $this->resolveWarmupConfig($device->meta ?? null);
                $connectedSessions[] = [
                    'session_id' => $sessionId,
                    'warmup_active' => $warmup['active'],
                    'warmup_max_per_batch' => $warmup['max_per_batch'],
                ];
            }
        }

        if ($connectedSessions === []) {
            return $fallback;
        }

        $unique = [];
        foreach ($connectedSessions as $profile) {
            $sid = (string) ($profile['session_id'] ?? '');
            if ($sid === '' || array_key_exists($sid, $unique)) {
                continue;
            }

            $unique[$sid] = $profile;
        }

        return array_values($unique);
    }

    /**
     * @return array{active: bool, max_per_batch: int}
     */
    private function resolveWarmupConfig(mixed $meta): array
    {
        $metaData = [];

        if (is_array($meta)) {
            $metaData = $meta;
        } elseif (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                $metaData = $decoded;
            }
        }

        $now = time();
        $isWarmupFlag = (bool) ($metaData['is_warmup'] ?? false);
        $warmupUntil = trim((string) ($metaData['warmup_until'] ?? ''));
        $warmupUntilTs = $warmupUntil !== '' ? strtotime($warmupUntil) : false;
        $isWarmupByTime = $warmupUntilTs !== false && $warmupUntilTs > $now;
        $hasExpired = $warmupUntilTs !== false && $warmupUntilTs <= $now;

        $manualMaxPerBatch = (int) ($metaData['warmup_max_per_batch'] ?? 1);
        $manualMaxPerBatch = max(1, min(100, $manualMaxPerBatch));

        $active = ($isWarmupFlag || $isWarmupByTime) && ! $hasExpired;
        $isAutoWarmup = (bool) ($metaData['warmup_auto'] ?? false);
        if ($active && $isAutoWarmup) {
            $startedAt = trim((string) ($metaData['warmup_started_at'] ?? ''));
            $startedAtTs = $startedAt !== '' ? strtotime($startedAt) : false;
            if ($startedAtTs === false || $startedAtTs > $now) {
                $startedAtTs = $now;
            }

            $daysRunning = (int) floor(($now - $startedAtTs) / 86400) + 1;
            $manualMaxPerBatch = $this->resolveAutoWarmupMaxPerBatch(max(1, $daysRunning));
        }

        return [
            'active' => $active,
            'max_per_batch' => $manualMaxPerBatch,
        ];
    }

    private function resolveAutoWarmupMaxPerBatch(int $daysRunning): int
    {
        return match (true) {
            $daysRunning <= 2 => 1,
            $daysRunning <= 4 => 2,
            $daysRunning <= 7 => 3,
            $daysRunning <= 10 => 5,
            $daysRunning <= 14 => 8,
            default => 12,
        };
    }

    private function applyBlastInterMessageDelay(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        // Selalu terapkan minimum floor — tidak boleh 0 meski setting tenant kosong
        $minMs = max(self::BLAST_DELAY_FLOOR_MS, $this->blastDelayMinMs);
        $maxMs = max($minMs + 1000, $this->blastDelayMaxMs);

        try {
            $delayMs = random_int($minMs, $maxMs);
        } catch (\Throwable) {
            $delayMs = $minMs;
        }

        usleep($delayMs * 1000);
    }

    private function applyBlastMessageVariation(string $message, ?string $name, int $index, string $normalizedPhone, int $variationBatchSeed): string
    {
        $baseMessage = trim($message);
        if ($baseMessage === '' || ! $this->blastNaturalVariation) {
            return $baseMessage;
        }

        $rawName = trim((string) $name);
        $honorific = 'Bapak/Ibu';
        $recipient = $rawName !== '' ? $honorific.' '.$rawName : $honorific;

        $openings = [
            'Halo '.$recipient.',',
            'Permisi '.$recipient.',',
            'Selamat '.$this->resolveBlastTimeGreeting().' '.$recipient.',',
        ];
        $closings = [
            'Terima kasih atas perhatian Anda.',
            'Jika ada pertanyaan, silakan balas pesan ini.',
            'Kami siap membantu jika diperlukan.',
        ];

        $opening = $openings[$this->resolveBlastVariationIndex($normalizedPhone, $index, $variationBatchSeed, count($openings), 'opening')];
        $closing = $closings[$this->resolveBlastVariationIndex($normalizedPhone, $index, $variationBatchSeed, count($closings), 'closing')];

        $segments = [];

        if (! $this->messageStartsWithGreeting($baseMessage)) {
            $segments[] = $opening;
        }

        $segments[] = $baseMessage;

        if (! $this->messageEndsWithProfessionalClosing($baseMessage)) {
            $segments[] = $closing;
        }

        return implode("\n\n", $segments);
    }

    private function resolveBlastVariationBatchSeed(): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        try {
            return random_int(1, PHP_INT_MAX);
        } catch (\Throwable) {
            return (int) hrtime(true);
        }
    }

    private function resolveBlastVariationIndex(string $normalizedPhone, int $fallbackIndex, int $variationBatchSeed, int $totalOptions, string $scope): int
    {
        if ($totalOptions <= 1) {
            return 0;
        }

        $variationKey = trim($normalizedPhone) !== '' ? $normalizedPhone : (string) $fallbackIndex;
        $hashSource = $scope.'|'.$variationBatchSeed.'|'.$variationKey;
        $hash = abs((int) crc32($hashSource));

        return $hash % $totalOptions;
    }

    private function resolveBlastTimeGreeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour >= 4 && $hour < 11 => 'pagi',
            $hour >= 11 && $hour < 15 => 'siang',
            $hour >= 15 && $hour < 19 => 'sore',
            default => 'malam',
        };
    }

    private function messageStartsWithGreeting(string $message): bool
    {
        $firstLine = trim((string) strtok($message, "\n"));
        if ($firstLine === '') {
            return false;
        }

        $normalizedFirstLine = mb_strtolower($firstLine);

        return (bool) preg_match('/^(halo|hai|hi|permisi|yth\.?|selamat\s+(pagi|siang|sore|malam)|bapak\/ibu|bpk\/ibu|assalamu[\'\s]?alaikum)\b/u', $normalizedFirstLine);
    }

    private function messageEndsWithProfessionalClosing(string $message): bool
    {
        $normalizedTail = mb_strtolower(trim(mb_substr($message, -220)));
        if ($normalizedTail === '') {
            return false;
        }

        $closingPhrases = [
            'terima kasih',
            'mohon maaf',
            'hormat kami',
            'kami siap membantu',
            'silakan balas pesan ini',
            'hubungi kami',
            'jika masih ada kendala',
            'wassalam',
            'wasalam',
            '🙏',
        ];

        foreach ($closingPhrases as $closingPhrase) {
            if (str_contains($normalizedTail, $closingPhrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test connectivity by checking device info.
     * Tries several common endpoints; reports auth errors vs network errors distinctly.
     */
    public function testConnection(): array
    {
        $candidates = [
            '/api/device/info',
            '/api/v2/device/info',
            '/api/v2/sessions/status?session='.$this->resolveSessionId(),
            '/api/devices',
            '/status',
        ];

        $lastError = '';

        foreach ($candidates as $path) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->buildHeaders())
                    ->get($this->url.$path);

                if ($response->successful()) {
                    return [
                        'status' => true,
                        'message' => 'Koneksi berhasil (endpoint: '.$path.')',
                        'http_status' => $response->status(),
                        'data' => $response->json(),
                    ];
                }

                // 401/403 = gateway reachable but token wrong
                if (in_array($response->status(), [401, 403])) {
                    return [
                        'status' => false,
                        'message' => 'Gateway dapat dijangkau tetapi token/key ditolak (HTTP '.$response->status().'). Periksa Token atau Key Anda.',
                        'http_status' => $response->status(),
                        'data' => $response->body(),
                    ];
                }

                if ($response->status() === 404 && str_contains(strtolower($response->body()), 'token not found')) {
                    return [
                        'status' => false,
                        'message' => 'Gateway dapat dijangkau tetapi token perangkat tidak ditemukan. Periksa Token WhatsApp Anda.',
                        'http_status' => $response->status(),
                        'data' => $response->body(),
                    ];
                }

                $lastError = 'HTTP '.$response->status().' pada '.$path;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if (str_contains($e->getMessage(), 'Could not resolve') ||
                    str_contains($e->getMessage(), 'Connection refused') ||
                    str_contains($e->getMessage(), 'timed out')) {
                    return [
                        'status' => false,
                        'message' => 'Tidak dapat terhubung ke gateway: '.$e->getMessage(),
                        'http_status' => 0,
                        'network_error' => true,
                    ];
                }
            }
        }

        return [
            'status' => false,
            'message' => 'Gateway tidak merespons pada endpoint yang diketahui. '.$lastError,
            'http_status' => 0,
        ];
    }

    public function startSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/start', $sessionId);
    }

    public function stopSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/stop', $sessionId);
    }

    public function restartSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/restart', $sessionId);
    }

    public function sessionStatus(?string $sessionId = null): array
    {
        $targetSession = $sessionId ?: $this->resolveSessionId();

        try {
            $response = Http::timeout(10)
                ->withHeaders($this->buildHeaders())
                ->get($this->url.'/api/v2/sessions/status', [
                    'session' => $targetSession,
                ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'status' => true,
                    'message' => 'Status sesi berhasil diambil.',
                    'data' => $body['data'] ?? $body,
                    'http_status' => $response->status(),
                ];
            }

            return [
                'status' => false,
                'message' => 'Gagal membaca status sesi (HTTP '.$response->status().').',
                'data' => $response->json() ?? $response->body(),
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Tidak dapat membaca status sesi: '.$e->getMessage(),
                'http_status' => 0,
                'network_error' => true,
            ];
        }
    }

    /**
     * Send a WhatsApp message to a group by group JID.
     * The group JID must end with @g.us (e.g. "120363xxxxxxx@g.us").
     */
    public function sendGroupMessage(string $groupId, string $message, array $context = []): bool
    {
        if (empty(trim($groupId))) {
            return false;
        }

        if (trim($this->token) === '') {
            return false;
        }

        $refId = 'rafen-grp-'.date('YmdHis').'-'.bin2hex(random_bytes(4));
        $context['message'] = mb_substr($message, 0, 4000);

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.'/api/v2/send-message', [
                    'data' => [
                        [
                            'session' => $this->resolveSessionId($context),
                            'phone' => $groupId,
                            'message' => $message,
                            'isGroup' => true,
                            'ref_id' => $refId,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $isOk = (bool) ($body['status'] ?? false);
                $msgStatus = strtolower((string) ($body['data']['messages'][0]['status'] ?? ''));
                $isFailed = in_array($msgStatus, ['failed', 'error', 'undelivered'], true);

                if ($isOk && ! $isFailed) {
                    Log::info('WA group message sent', array_merge($context, ['group_id' => $groupId, 'ref_id' => $refId]));
                    $this->writeLog('sent', $groupId, $groupId, $context, null, $refId);

                    return true;
                }
            }

            $this->writeLog('failed', $groupId, $groupId, $context, 'Gateway rejected group message', $refId);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WA group message exception', ['error' => $e->getMessage(), 'group_id' => $groupId]);
            $this->writeLog('failed', $groupId, $groupId, $context, 'Exception: '.$e->getMessage(), $refId);

            return false;
        }
    }

    /**
     * Fetch list of WhatsApp groups joined by the configured session.
     * Returns array of ['id' => '...@g.us', 'subject' => 'Group Name', 'size' => N]
     *
     * @return array<int, array{id: string, subject: string, size: int}>
     */
    public function getGroups(): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->get($this->url.'/api/v2/groups', [
                    'session' => $this->resolveSessionId(),
                ]);

            if ($response->successful()) {
                return $response->json('data.groups') ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('WA getGroups exception', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function callSessionEndpoint(string $path, ?string $sessionId = null): array
    {
        $targetSession = $sessionId ?: $this->resolveSessionId();

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.$path, [
                    'session' => $targetSession,
                ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'status' => true,
                    'message' => (string) ($body['message'] ?? 'Berhasil.'),
                    'data' => $body['data'] ?? $body,
                    'http_status' => $response->status(),
                ];
            }

            return [
                'status' => false,
                'message' => 'Permintaan gagal (HTTP '.$response->status().').',
                'data' => $response->json() ?? $response->body(),
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Tidak dapat menghubungi gateway sesi: '.$e->getMessage(),
                'http_status' => 0,
                'network_error' => true,
            ];
        }
    }

    private function resolveSessionId(array $context = []): string
    {
        $contextSession = trim((string) ($context['session_id'] ?? ''));

        if ($contextSession !== '') {
            return $contextSession;
        }

        if (! empty($this->sessionId)) {
            return $this->sessionId;
        }

        if ($this->ownerId !== null) {
            return 'tenant-'.$this->ownerId;
        }

        return 'default';
    }

    private function resolveStickySessionForPhone(string $normalizedPhone): ?string
    {
        if ($this->ownerId === null || ! $this->isValidPhone($normalizedPhone)) {
            return null;
        }

        $value = Cache::get($this->stickyCacheKey($normalizedPhone));
        $sessionId = trim((string) $value);

        return $sessionId !== '' ? $sessionId : null;
    }

    private function rememberStickySessionForPhone(string $normalizedPhone, string $sessionId): void
    {
        if ($this->ownerId === null || ! $this->isValidPhone($normalizedPhone)) {
            return;
        }

        $trimmedSession = trim($sessionId);
        if ($trimmedSession === '') {
            return;
        }

        Cache::put(
            $this->stickyCacheKey($normalizedPhone),
            $trimmedSession,
            now()->addDays(self::STICKY_SENDER_TTL_DAYS)
        );
    }

    private function stickyCacheKey(string $normalizedPhone): string
    {
        return self::stickyCacheKeyFor((int) $this->ownerId, $normalizedPhone);
    }

    public static function clearStickySenderForPhone(int $ownerId, string $phone): bool
    {
        $normalizedPhone = self::normalizePhoneStatic($phone);
        $isValid = (bool) preg_match('/^62\d{8,13}$/', $normalizedPhone);

        if ($ownerId <= 0 || ! $isValid) {
            return false;
        }

        $cacheKey = self::stickyCacheKeyFor($ownerId, $normalizedPhone);
        $exists = Cache::has($cacheKey);
        Cache::forget($cacheKey);

        return $exists;
    }

    private static function stickyCacheKeyFor(int $ownerId, string $normalizedPhone): string
    {
        return 'wa_sticky_sender_'.$ownerId.'_'.md5($normalizedPhone);
    }

    private static function normalizePhoneStatic(string $phone): string
    {
        $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
        $normalizedPhone = ltrim((string) $normalizedPhone, '+');

        if (str_starts_with($normalizedPhone, '0')) {
            $normalizedPhone = '62'.substr($normalizedPhone, 1);
        }

        return $normalizedPhone;
    }

    private function resolveDefaultTenantSession(): ?string
    {
        if ($this->ownerId === null) {
            return null;
        }

        $defaultDevice = WaMultiSessionDevice::query()
            ->forOwner($this->ownerId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultDevice) {
            return $defaultDevice->session_id;
        }

        return 'tenant-'.$this->ownerId;
    }
}
