<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyDueSoonPush extends Command
{
    protected $signature   = 'billing:notify-due-soon';
    protected $description = 'Kirim push notification ke customer dengan tagihan jatuh tempo dalam 7 hari';

    public function handle(): int
    {
        $today   = now()->toDateString();
        $in7days = now()->addDays(7)->toDateString();

        $invoices = Invoice::query()
            ->where('status', 'unpaid')
            ->whereBetween('due_date', [$today, $in7days])
            ->whereNotNull('payment_token')
            ->with('pppUser')
            ->get();

        $sent    = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->pppUser) {
                $skipped++;
                continue;
            }

            $cacheKey = 'push-due-soon-'.$invoice->id.'-'.date('Y-m-d');

            if (Cache::has($cacheKey)) {
                $skipped++;
                continue;
            }

            try {
                $dueDate = $invoice->due_date
                    ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y')
                    : '-';

                PushNotificationService::sendToCustomer(
                    $invoice->pppUser,
                    'Tagihan Jatuh Tempo',
                    'Tagihan '.$invoice->invoice_number.' sebesar Rp '.number_format((float) $invoice->total, 0, ',', '.').' jatuh tempo '.$dueDate.'. Segera bayar.',
                    ['url' => route('bayar', $invoice->payment_token), 'tag' => 'due-soon-'.$invoice->id, 'icon' => '/branding/notify-invoice.png']
                );

                Cache::put($cacheKey, true, now()->addHours(25));
                $sent++;
            } catch (\Throwable $e) {
                $this->error('  [ERR] invoice_id='.$invoice->id.': '.$e->getMessage());
                $skipped++;
            }
        }

        $this->info("billing:notify-due-soon selesai. Sent: {$sent}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
