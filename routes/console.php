<?php

use App\Jobs\PollOltConnectionJob;
use App\Models\OltConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ping semua router MikroTik aktif setiap menit
Schedule::command('mikrotik:ping-once')
    ->everyMinute()
    ->withoutOverlapping();

// Sync active PPPoE & Hotspot sessions dari semua router setiap 5 menit
// withoutOverlapping(4): mutex expire 4 menit agar tidak block run berikutnya jika hang
Schedule::command('sessions:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->runInBackground();

// Sync radcheck/radreply dari ppp_users, hotspot_users, dan vouchers setiap 5 menit
Schedule::command('radius:sync-replies')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Cek atribut RADIUS di DB sudah terdaftar di dictionary, auto-fix jika ada yang baru (setiap hari jam 06:00)
Schedule::command('radius:check-dictionary --fix')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Reset status_bayar ke belum_bayar untuk user yang jatuh temponya sudah tiba (setiap menit, idempoten)
Schedule::command('billing:reset-status')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Isolir user PPP yang overdue dan belum bayar (setiap menit, gap ~1 menit dari jatuh tempo)
Schedule::command('billing:isolate-overdue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Generate invoice untuk user PPP yang jatuh tempo dalam 14 hari ke depan (setiap hari jam 07:00)
Schedule::command('invoice:generate-upcoming --days=14')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Deteksi voucher yang sudah digunakan berdasarkan radacct (setiap menit)
Schedule::command('vouchers:mark-used')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Hapus voucher expired dari DB dan RADIUS (setiap menit agar tidak menumpuk)
Schedule::command('vouchers:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Kirim push notification ke customer dengan tagihan jatuh tempo dalam 7 hari (jam 08:00)
Schedule::command('billing:notify-due-soon')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Kirim WA reminder perpanjangan subscription (7 hari & 1 hari sebelum expired) — jam 09:00
Schedule::command('subscription:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Cek versi terbaru baileys dari npm registry (setiap hari jam 08:30)
Schedule::command('wa-gateway:check-baileys-update')
    ->dailyAt('08:30')
    ->withoutOverlapping()
    ->runInBackground();

// Pastikan service WA Gateway lokal selalu aktif di background
Schedule::command('wa-gateway:ensure-running')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Deteksi session beku/disconnected: update last_status, restart frozen, alert disconnect
// Interval dipercepat ke 5 menit agar alert disconnect terkirim lebih cepat
Schedule::command('wa-gateway:refresh-sessions --stale-minutes=120')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Kirim reminder shift besok ke semua pegawai via WA (jam 19:00)
Schedule::command('shifts:send-reminders')
    ->dailyAt('19:00')
    ->withoutOverlapping()
    ->runInBackground();

// Auto-link GenieACS devices ke PPP users berdasarkan PPPoE username (setiap 5 menit)
Schedule::command('cpe:sync-genieacs')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Bangunkan CPE offline via TR-069 Connection Request (setiap 30 menit)
Schedule::command('cpe:recover-offline')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Refresh status system license dari disk setiap hari jam 00:01 (update status active/grace/restricted)
Schedule::command('license:refresh')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->runInBackground();

// Kirim heartbeat status instance self-hosted ke SaaS control plane setiap 30 menit
Schedule::command('self-hosted:heartbeat')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Hapus otomatis tenant trial yang expired > 7 hari dan belum pernah beli paket (setiap hari jam 02:00)
Schedule::command('tenants:cleanup-expired-trials')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Poll semua OLT aktif setiap 15 menit (MODE_QUICK: rx_onu_dbm, distance_m, status)
// Aman dari bentrok: PollOltConnectionJob implements ShouldBeUnique + Cache::lock (900 detik)
Schedule::call(function () {
    OltConnection::query()
        ->where('is_active', true)
        ->each(function (OltConnection $olt) {
            PollOltConnectionJob::dispatch(
                $olt->id,
                PollOltConnectionJob::MODE_QUICK
            );
        });
})
    // ->everyFifteenMinutes()
    ->everyThreeHours()
    ->name('olt:auto-poll')
    ->withoutOverlapping();
