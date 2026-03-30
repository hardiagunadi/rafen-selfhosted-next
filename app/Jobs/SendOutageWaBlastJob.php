<?php

namespace App\Jobs;

use App\Models\Outage;
use App\Models\OutageUpdate;
use App\Models\TenantSettings;
use App\Services\WaGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOutageWaBlastJob implements ShouldQueue
{
    use Queueable;

    /** Timeout 30 menit — cukup untuk blast ke ratusan pelanggan dengan anti-spam delay */
    public int $timeout = 1800;

    public function __construct(
        public int $outageId,
        public int $ownerId,
        public string $blastType, // 'initial' | 'resolved'
        public ?int $sentByUserId = null,
    ) {}

    public function handle(): void
    {
        $outage = Outage::with('affectedAreas.odp')->find($this->outageId);
        if (! $outage) {
            return;
        }

        $settings = TenantSettings::where('user_id', $this->ownerId)->first();
        if (! $settings) {
            return;
        }

        $waService = WaGatewayService::forTenant($settings);
        if (! $waService) {
            Log::info('SendOutageWaBlastJob: WA tidak dikonfigurasi, blast dilewati.', [
                'outage_id' => $this->outageId,
                'blast_type' => $this->blastType,
            ]);

            return;
        }

        $areaLabels = implode(', ', $outage->affectedAreaLabels());
        $greeting = $this->timeGreeting();

        $recipients = $outage->affectedPppUsers()
            ->get(['customer_name', 'nomor_hp'])
            ->map(fn ($u) => [
                'phone' => $u->nomor_hp,
                'message' => $this->buildMessage($outage, $areaLabels, $greeting, $u->customer_name),
                'name' => $u->customer_name,
            ])
            ->unique('phone')
            ->values()
            ->all();

        if (empty($recipients)) {
            Log::info('SendOutageWaBlastJob: tidak ada pelanggan terdampak.', [
                'outage_id' => $this->outageId,
            ]);

            return;
        }

        $result = $waService->sendBulk($recipients);

        if ($this->blastType === 'initial') {
            $outage->update([
                'wa_blast_sent_at' => now(),
                'wa_blast_count' => $result['success'],
            ]);
        } else {
            $outage->update(['resolution_wa_sent_at' => now()]);
        }

        OutageUpdate::create([
            'outage_id' => $this->outageId,
            'user_id' => null,
            'type' => 'note',
            'body' => 'Notifikasi WA ('.($this->blastType === 'initial' ? 'gangguan' : 'pemulihan').') terkirim ke '.$result['success'].' pelanggan. Gagal: '.$result['failed'].'.',
            'is_public' => false,
        ]);
    }

    private function timeGreeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour >= 4 && $hour < 11 => 'Selamat Pagi',
            $hour >= 11 && $hour < 15 => 'Selamat Siang',
            $hour >= 15 && $hour < 19 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
    }

    private function buildMessage(Outage $outage, string $areaLabels, string $greeting, string $customerName): string
    {
        $includeLink = $outage->include_status_link ?? true;
        $honorific = 'Bapak/Ibu';
        $salutation = "{$greeting}, {$honorific} {$customerName}";

        if ($this->blastType === 'initial') {
            $etaLine = $outage->estimated_resolved_at
                ? "\nEstimasi selesai: ".$outage->estimated_resolved_at->format('d/m/Y H:i')
                : '';

            $linkLine = $includeLink
                ? "\n\nPantau status perbaikan di:\n".url('/status/'.$outage->public_token)
                : '';

            return "{$salutation},\n\n"
                ."⚠️ *Informasi Gangguan Jaringan Internet*\n\n"
                ."Area terdampak: {$areaLabels}\n"
                .'Mulai: '.$outage->started_at->format('d/m/Y H:i')
                .$etaLine
                .$linkLine."\n\n"
                .'Mohon maaf atas ketidaknyamanannya. 🙏';
        }

        return "{$salutation},\n\n"
            ."✅ *Layanan Jaringan Telah Pulih*\n\n"
            ."Area: {$areaLabels}\n"
            .'Diselesaikan: '.$outage->resolved_at->format('d/m/Y H:i')."\n\n"
            ."Koneksi Anda dapat digunakan kembali.\n"
            ."Jika masih ada kendala, restart perangkat atau hubungi kami.\n\n"
            .'Terima kasih atas kesabaran Anda. 🙏';
    }
}
