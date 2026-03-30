<?php

namespace App\Console\Commands;

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaMultiSessionDevice;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshWaSessions extends Command
{
    protected $signature = 'wa-gateway:refresh-sessions {--stale-minutes=20 : Restart sessions with no update for this many minutes}';

    protected $description = 'Detect disconnected WA sessions that need reconnect';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('wa.multi_session.host', '127.0.0.1'), '/');
        $port = (int) config('wa.multi_session.port', 3100);
        $token = (string) config('wa.multi_session.auth_token', '');
        $staleMin = max(5, (int) $this->option('stale-minutes'));
        $apiBase = "http://{$baseUrl}:{$port}";
        $threshold = now()->subMinutes($staleMin);

        // Ambil semua session_id: dari wa_multi_session_devices (aktif) + auth store DB
        $fromDevices = WaMultiSessionDevice::query()
            ->where('is_active', true)
            ->pluck('session_id');

        $fromAuthStore = DB::table('wa_multi_session_auth_store')
            ->selectRaw('DISTINCT session_id')
            ->pluck('session_id');

        $sessionIds = $fromDevices->merge($fromAuthStore)
            ->filter()
            ->unique()
            ->values();

        if ($sessionIds->isEmpty()) {
            $this->info('No active WA sessions found.');

            return self::SUCCESS;
        }

        $restarted = 0;
        $ok = 0;
        $alerted = 0;

        foreach ($sessionIds as $sessionId) {
            try {
                $response = Http::timeout(5)
                    ->withToken($token)
                    ->get("{$apiBase}/api/v2/sessions/status", ['session' => $sessionId]);

                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json('data', []);
                $status = $data['status'] ?? 'unknown';
                $updatedAt = $data['updated_at'] ?? null;
                $lastError = strtolower((string) ($data['last_error'] ?? ''));
                $isReconnectLoopReason = str_contains($lastError, 'loggedout')
                    || str_contains($lastError, 'connectionreplaced');

                // Update last_status di device record
                WaMultiSessionDevice::where('session_id', $sessionId)->update([
                    'last_status' => $status,
                    'last_seen_at' => now(),
                ]);

                // Sesi connected yang sedang idle bisa wajar tidak mengubah updated_at selama berjam-jam.
                // Jangan restart sesi hanya karena timestamp status lama karena justru bisa memicu
                // connectionReplaced pada perangkat yang sebenarnya masih sehat.
                $isQuietButConnected = $status === 'connected'
                    && $updatedAt !== null
                    && Carbon::parse($updatedAt)->lt($threshold);

                if ($isQuietButConnected) {
                    $quietConnectedCacheKey = 'wa_quiet_connected_'.md5($sessionId);

                    if (! Cache::has($quietConnectedCacheKey)) {
                        Cache::put($quietConnectedCacheKey, true, now()->addMinutes(60));
                        Log::info("wa-gateway:refresh-sessions: skipped restart for quiet connected session [{$sessionId}]", [
                            'last_updated' => $updatedAt,
                            'stale_minutes' => $staleMin,
                        ]);
                    }

                    $ok++;

                    continue;
                }

                // Auto-restart + alert jika disconnected/stopped lebih dari 10 menit
                // Skip auto-reconnect jika reason loggedOut agar tidak memicu loop logout berulang.
                $isLongDisconnected = in_array($status, ['disconnected', 'stopped'], true)
                    && $updatedAt !== null
                    && Carbon::parse($updatedAt)->lt(now()->subMinutes(10))
                    && ! $isReconnectLoopReason;

                if ($isLongDisconnected) {
                    // Coba auto-reconnect, rate-limit setiap 10 menit agar tidak loop
                    $reconnectCacheKey = 'wa_auto_reconnect_'.md5($sessionId);
                    if (! Cache::has($reconnectCacheKey)) {
                        Cache::put($reconnectCacheKey, true, now()->addMinutes(10));
                        $restart = Http::timeout(10)
                            ->withToken($token)
                            ->post("{$apiBase}/api/v2/sessions/restart", ['session' => $sessionId]);

                        if ($restart->successful()) {
                            $restarted++;
                            Log::info("wa-gateway:refresh-sessions: auto-reconnect disconnected session [{$sessionId}]");
                            $this->line("Auto-reconnected disconnected session: {$sessionId}");
                        } else {
                            Log::warning("wa-gateway:refresh-sessions: auto-reconnect failed [{$sessionId}]", [
                                'http_status' => $restart->status(),
                                'body' => $restart->body(),
                            ]);
                        }
                    }

                    // Kirim alert ke admin (rate-limit 60 menit/device)
                    $alertCacheKey = 'wa_disconnect_alert_'.md5($sessionId);
                    if (! Cache::has($alertCacheKey)) {
                        Cache::put($alertCacheKey, true, now()->addMinutes(60));
                        if ($this->sendSessionDisconnectAlert($sessionId)) {
                            $alerted++;
                            $this->line("Alert sent for disconnected session: {$sessionId}");
                        }
                    }
                }

                $ok++;
            } catch (\Throwable $e) {
                Log::warning("wa-gateway:refresh-sessions: error checking [{$sessionId}]", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Sessions checked: {$sessionIds->count()} | OK: {$ok} | Restarted: {$restarted} | Alerted: {$alerted}");

        return self::SUCCESS;
    }

    private function sendSessionDisconnectAlert(string $sessionId): bool
    {
        try {
            $device = WaMultiSessionDevice::where('session_id', $sessionId)->first();
            if (! $device) {
                return false;
            }

            $ownerId = $device->user_id;
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if (! $settings) {
                return false;
            }

            // Cari device lain milik tenant yang aktif sebagai pengirim
            $otherDevice = WaMultiSessionDevice::where('user_id', $ownerId)
                ->where('session_id', '!=', $sessionId)
                ->where('is_active', true)
                ->first();

            if (! $otherDevice) {
                return false;
            }

            $adminUser = User::find($ownerId);
            if (! $adminUser || empty(trim((string) ($adminUser->no_hp ?? '')))) {
                return false;
            }

            $service = WaGatewayService::forTenant($settings);
            $service->setSessionId($otherDevice->session_id);

            $deviceName = $device->device_name ?? $sessionId;
            $message = "⚠️ *WA Gateway Alert*\n\nDevice *{$deviceName}* terputus dari WhatsApp.\n\nSegera cek dan scan ulang QR di halaman Pengaturan > WA Gateway.";

            return $service->sendMessage($adminUser->no_hp, $message, [
                'event' => 'system_alert',
                'session' => $otherDevice->session_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("wa-gateway:refresh-sessions: gagal kirim alert untuk [{$sessionId}]", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
