<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TeknisiSetoran;
use App\Models\TenantSettings;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use App\Services\WaNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPaidInvoiceSideEffectsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $invoiceId,
        public int $ownerId,
        public int $paidByUserId,
        public ?int $pppUserId,
        public bool $wasOnProcess,
        public bool $wasIsolir,
        public bool $hasCashReceived,
        public string $paidDate,
    ) {
        $this->onConnection('sync');
    }

    /**
     * Execute the job.
     */
    public function handle(
        RadiusReplySynchronizer $radiusReplySynchronizer,
        IsolirSynchronizer $isolirSynchronizer,
    ): void {
        $invoice = Invoice::query()->find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $pppUser = null;

        if ($this->pppUserId !== null) {
            $pppUser = PppUser::query()->find($this->pppUserId);
        }

        if ($pppUser) {
            try {
                $radiusReplySynchronizer->syncSingleUser($pppUser);
            } catch (Throwable $exception) {
                Log::warning('Invoice paid side effects: radius sync failed', [
                    'invoice_id' => $this->invoiceId,
                    'ppp_user_id' => $this->pppUserId,
                    'error' => $exception->getMessage(),
                ]);
            }

            if ($this->wasIsolir) {
                try {
                    $isolirSynchronizer->deisolate($pppUser);
                } catch (Throwable $exception) {
                    Log::warning('Invoice paid side effects: deisolate failed', [
                        'invoice_id' => $this->invoiceId,
                        'ppp_user_id' => $this->pppUserId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($this->hasCashReceived) {
            try {
                TeknisiSetoran::createOrRecalculateForUser(
                    $this->paidByUserId,
                    $this->ownerId,
                    $this->paidDate,
                );
            } catch (Throwable $exception) {
                Log::warning('Invoice paid side effects: recalculate teknisi setoran failed', [
                    'invoice_id' => $this->invoiceId,
                    'paid_by' => $this->paidByUserId,
                    'owner_id' => $this->ownerId,
                    'paid_date' => $this->paidDate,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $settings = TenantSettings::getOrCreate($this->ownerId);

            if ($this->wasOnProcess && $pppUser) {
                WaNotificationService::notifyRegistration($settings, $pppUser->fresh()->load('profile'));
            }

            WaNotificationService::notifyInvoicePaid($settings, $invoice->fresh()->load('pppUser'));

            if ($pppUser) {
                \App\Services\PushNotificationService::sendToCustomer(
                    $pppUser->fresh(),
                    'Pembayaran Dikonfirmasi',
                    'Tagihan '.$invoice->invoice_number.' telah lunas. Terima kasih!',
                    ['url' => route('bayar', $invoice->payment_token), 'tag' => 'paid-'.$invoice->id, 'icon' => '/branding/notify-payment.png']
                );
            }
        } catch (Throwable $exception) {
            Log::warning('Invoice paid side effects: notification dispatch failed', [
                'invoice_id' => $this->invoiceId,
                'owner_id' => $this->ownerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
