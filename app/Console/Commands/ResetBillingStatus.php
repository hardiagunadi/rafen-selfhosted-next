<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use Illuminate\Console\Command;

class ResetBillingStatus extends Command
{
    protected $signature = 'billing:reset-status
                            {--dry-run : Tampilkan tanpa mengubah data}';

    protected $description = 'Reset status_bayar ke belum_bayar untuk user yang jatuh temponya sudah tiba/terlewati';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today  = now()->toDateString();

        $users = PppUser::query()
            ->where('status_bayar', 'sudah_bayar')
            ->whereNotNull('jatuh_tempo')
            ->where('jatuh_tempo', '<=', $today)
            ->get(['id', 'username', 'jatuh_tempo']);

        if ($users->isEmpty()) {
            $this->info("Tidak ada user yang perlu direset.");
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$users->count()} user dengan jatuh_tempo <= {$today} dan status_bayar = sudah_bayar.");

        foreach ($users as $user) {
            if ($dryRun) {
                $this->line("  [dry-run] {$user->username} — jatuh_tempo: {$user->jatuh_tempo}");
                continue;
            }

            PppUser::where('id', $user->id)->update(['status_bayar' => 'belum_bayar']);
            $this->line("  [OK] {$user->username} — direset ke belum_bayar (jatuh_tempo: {$user->jatuh_tempo})");
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info("Selesai. {$users->count()} user direset ke belum_bayar.");
        }

        return self::SUCCESS;
    }
}
