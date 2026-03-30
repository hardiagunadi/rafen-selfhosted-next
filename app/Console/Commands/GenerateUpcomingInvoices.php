<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\PushNotificationService;
use App\Services\WaNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateUpcomingInvoices extends Command
{
    protected $signature = 'invoice:generate-upcoming
                            {--days=14 : Jumlah hari sebelum jatuh tempo untuk generate invoice}
                            {--dry-run : Tampilkan tanpa membuat invoice}';

    protected $description = 'Generate invoice untuk user PPP yang jatuh temponya dalam N hari ke depan';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = $this->option('dry-run');
        $now = now();
        $windowEnd = $now->copy()->addDays($days)->endOfDay();

        $this->info("Mencari user dengan jatuh_tempo <= {$windowEnd->toDateString()}...");

        $users = PppUser::query()
            ->whereNotNull('jatuh_tempo')
            ->where('jatuh_tempo', '<=', $windowEnd->toDateString())
            ->with(['profile', 'owner'])
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $dueDate = Carbon::parse($user->jatuh_tempo)->endOfDay();

            if ($this->hasInvoiceForDueDate($user, $dueDate)) {
                $skipped++;
                $this->line("  [skip] {$user->username} — invoice untuk jatuh tempo {$dueDate->toDateString()} sudah ada.");

                continue;
            }

            if (! $user->profile) {
                $skipped++;
                $this->line("  [skip] {$user->username} — tidak ada profil PPP.");

                continue;
            }

            if ($dryRun) {
                $this->info("  [dry-run] Akan generate invoice untuk: {$user->username} (jatuh_tempo: {$dueDate->toDateString()})");
                $generated++;

                continue;
            }

            $invoice = $this->createInvoice($user);
            $generated++;
            $this->info("  [OK] Invoice dibuat untuk: {$user->username} (jatuh_tempo: {$dueDate->toDateString()})");

            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            WaNotificationService::notifyInvoiceCreated($settings, $invoice, $user);
            try {
                PushNotificationService::sendToCustomer(
                    $user,
                    'Tagihan Baru Terbit',
                    'Tagihan '.$invoice->invoice_number.' sebesar Rp '.number_format((float) $invoice->total, 0, ',', '.').' jatuh tempo '.optional($invoice->due_date)->format('d/m/Y').'.',
                    ['url' => route('bayar', $invoice->payment_token), 'tag' => 'invoice-'.$invoice->id, 'icon' => '/branding/notify-invoice.png']
                );
            } catch (\Throwable) {
            }
        }

        $this->newLine();
        $this->info("Selesai. Generated: {$generated}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function hasInvoiceForDueDate(PppUser $user, Carbon $dueDate): bool
    {
        return Invoice::query()
            ->where('ppp_user_id', $user->id)
            ->whereDate('due_date', $dueDate->toDateString())
            ->exists();
    }

    private function createInvoice(PppUser $user): Invoice
    {
        $profile = $user->profile;

        $promoMonths = (int) ($user->durasi_promo_bulan ?? 0);
        $promoActive = $user->promo_aktif
            && $promoMonths > 0
            && $user->created_at
            && $user->created_at->diffInMonths(now()) < $promoMonths;

        $basePrice = $promoActive ? $profile->harga_promo : $profile->harga_modal;

        // Tagihkan PPN hanya jika flag aktif di user
        $ppnPercent = $user->tagihkan_ppn ? (float) $profile->ppn : 0.0;
        $ppnAmount = round($basePrice * ($ppnPercent / 100), 2);
        $total = $basePrice + $ppnAmount;
        $dueDate = Carbon::parse($user->jatuh_tempo)->endOfDay();

        $prefix = TenantSettings::getOrCreate($user->owner_id)->invoice_prefix ?? 'INV';

        return Invoice::create([
            'invoice_number' => Invoice::generateNumber($user->owner_id, $prefix),
            'ppp_user_id' => $user->id,
            'ppp_profile_id' => $user->ppp_profile_id,
            'owner_id' => $user->owner_id,
            'customer_id' => $user->customer_id,
            'customer_name' => $user->customer_name,
            'tipe_service' => $user->tipe_service,
            'paket_langganan' => $profile->name,
            'harga_dasar' => $basePrice,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'total' => $total,
            'promo_applied' => $promoActive,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'payment_token' => Invoice::generatePaymentToken(),
        ]);
    }
}
