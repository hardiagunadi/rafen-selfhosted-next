<?php

namespace App\Jobs;

use App\Models\TenantSettings;
use App\Models\WaMultiSessionDevice;
use App\Models\WaTicket;
use App\Services\WaGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTicketWaNotificationJob implements ShouldQueue
{
    use Queueable;

    /** Maksimal percobaan sebelum menyerah (misal: tiap 5 menit × 24 = 2 jam) */
    public int $tries = 24;

    /** Timeout per eksekusi */
    public int $timeout = 60;

    public function __construct(
        public int $ticketId,
        public int $ownerId,
        public string $notifyPhone,
        public ?string $groupId = null,
        public ?string $groupMsg = null,
    ) {}

    public function handle(): void
    {
        $ticket = WaTicket::find($this->ticketId);
        if (! $ticket) {
            return;
        }

        $settings = TenantSettings::where('user_id', $this->ownerId)->first();
        if (! $settings || ! $settings->hasWaConfigured()) {
            return;
        }

        // Cek apakah ada session yang connected
        if (! $this->hasConnectedSession($settings)) {
            Log::info('SendTicketWaNotificationJob: tidak ada session WA connected, akan retry.', [
                'ticket_id' => $this->ticketId,
                'attempt' => $this->attempts(),
            ]);
            // Retry 5 menit kemudian
            $this->release(300);

            return;
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return;
        }

        // Kirim ke pelanggan
        $publicUrl = $ticket->publicUrl();
        $msg = "Halo, tiket pengaduan Anda telah kami terima.\n\n"
            ."No. Tiket: #{$ticket->id}\n"
            ."Judul: {$ticket->title}\n\n"
            ."Pantau progres tiket Anda:\n{$publicUrl}\n\n"
            .'Tim kami akan segera menanganinya. Terima kasih.';

        $service->sendMessage($this->notifyPhone, $msg, ['event' => 'ticket_created']);

        // Kirim ke grup jika dikonfigurasi
        if ($this->groupId && $this->groupMsg) {
            $service->sendGroupMessage($this->groupId, $this->groupMsg, ['event' => 'ticket_group_notify']);
        }
    }

    /**
     * Cek apakah minimal satu WA session untuk tenant ini sedang connected.
     */
    private function hasConnectedSession(TenantSettings $settings): bool
    {
        // Gunakan public_url yang sama dengan WaGatewayService (bisa via proxy Nginx)
        $gatewayUrl = rtrim((string) config('wa.multi_session.public_url', ''), '/');
        if ($gatewayUrl === '') {
            $gatewayUrl = rtrim((string) ($settings->wa_gateway_url ?? ''), '/');
        }
        if ($gatewayUrl === '') {
            return false;
        }

        $token = trim((string) config('wa.multi_session.auth_token', ''));
        $key = trim((string) config('wa.multi_session.master_key', ''));
        if ($token === '') {
            $token = trim((string) ($settings->wa_gateway_token ?? ''));
            $key = trim((string) ($settings->wa_gateway_key ?? ''));
        }

        $devices = WaMultiSessionDevice::query()
            ->forOwner((int) $settings->user_id)
            ->where('is_active', true)
            ->get(['session_id']);

        // Jika tidak ada device terdaftar, coba cek session default tenant
        if ($devices->isEmpty()) {
            $sessionId = 'tenant-'.$settings->user_id;

            return $this->isSessionConnected($gatewayUrl, $token, $key, $sessionId);
        }

        foreach ($devices as $device) {
            $sid = trim((string) ($device->session_id ?? ''));
            if ($sid === '') {
                continue;
            }
            if ($this->isSessionConnected($gatewayUrl, $token, $key, $sid)) {
                return true;
            }
        }

        return false;
    }

    private function isSessionConnected(string $gatewayUrl, string $token, string $key, string $sessionId): bool
    {
        try {
            $headers = ['Authorization' => $token];
            if ($key !== '') {
                $headers['key'] = $key;
            }
            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get("{$gatewayUrl}/api/v2/sessions/status", ['session' => $sessionId]);

            if (! $response->successful()) {
                return false;
            }

            $status = strtolower((string) data_get($response->json(), 'data.status', ''));

            return $status === 'connected';
        } catch (\Throwable) {
            return false;
        }
    }

    public function retryUntil(): \DateTime
    {
        // Berhenti retry setelah 2 jam
        return now()->addHours(2);
    }
}
