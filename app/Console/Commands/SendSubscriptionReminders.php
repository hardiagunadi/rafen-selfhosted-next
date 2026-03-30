<?php

namespace App\Console\Commands;

use App\Mail\TenantTrialExpiringSoon;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscription:send-reminders';

    protected $description = 'Kirim WA + email reminder perpanjangan subscription ke tenant yang akan habis (7 hari & 1 hari) dan trial expiring (3, 2, 1, 0 hari)';

    public function handle(): int
    {
        $this->sendSubscriptionReminders();
        $this->sendTrialExpiryReminders();

        return self::SUCCESS;
    }

    private function sendSubscriptionReminders(): void
    {
        $today = now()->startOfDay();

        // Kirim reminder untuk 7 hari dan 1 hari sebelum expired
        $reminderDays = [7, 1];

        $sent    = 0;
        $skipped = 0;

        foreach ($reminderDays as $daysLeft) {
            $targetDate = $today->copy()->addDays($daysLeft);

            $tenants = User::query()
                ->where('is_super_admin', false)
                ->whereNull('parent_id')
                ->where('subscription_status', 'active')
                ->whereNotNull('subscription_expires_at')
                ->whereDate('subscription_expires_at', $targetDate)
                ->get();

            foreach ($tenants as $tenant) {
                $result = $this->sendWaReminder($tenant, $daysLeft);
                $result ? $sent++ : $skipped++;
            }
        }

        $this->info("Subscription reminders: {$sent} sent, {$skipped} skipped.");
    }

    private function sendTrialExpiryReminders(): void
    {
        $today = now()->startOfDay();

        // Kirim reminder trial untuk 3, 2, 1 hari sebelum habis dan pada hari H (0)
        $reminderDays = [3, 2, 1, 0];
        $sent    = 0;
        $skipped = 0;

        foreach ($reminderDays as $daysLeft) {
            if ($daysLeft === 0) {
                // Hari H: trial_days_remaining = 0 DAN subscription_expires_at = today atau null dan registered_at = 14 hari lalu
                $tenants = User::query()
                    ->where('is_super_admin', false)
                    ->whereNull('parent_id')
                    ->where('subscription_status', 'expired')
                    ->where('trial_days_remaining', 0)
                    ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'pending']))
                    ->where(function ($q) use ($today) {
                        $q->whereDate('subscription_expires_at', $today)
                          ->orWhere(function ($q2) use ($today) {
                              $q2->whereNull('subscription_expires_at')
                                 ->whereDate('registered_at', $today->copy()->subDays(14));
                          });
                    })
                    ->get();
            } else {
                $targetDate = $today->copy()->addDays($daysLeft);
                $tenants = User::query()
                    ->where('is_super_admin', false)
                    ->whereNull('parent_id')
                    ->where('subscription_status', 'trial')
                    ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'pending']))
                    ->where(function ($q) use ($targetDate) {
                        $q->whereDate('subscription_expires_at', $targetDate)
                          ->orWhere(function ($q2) use ($targetDate) {
                              $q2->whereNull('subscription_expires_at')
                                 ->whereDate('registered_at', $targetDate->copy()->subDays(14));
                          });
                    })
                    ->get();
            }

            foreach ($tenants as $tenant) {
                if ($this->sendTrialEmail($tenant, $daysLeft)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        }

        $this->info("Trial expiry reminders: {$sent} sent, {$skipped} skipped.");
    }

    private function sendTrialEmail(User $tenant, int $daysLeft): bool
    {
        if (empty($tenant->email)) {
            return false;
        }

        try {
            Mail::to($tenant->email)->queue(new TenantTrialExpiringSoon($tenant, $daysLeft));
            Log::info('Trial expiry email sent', ['tenant_id' => $tenant->id, 'days_left' => $daysLeft]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Trial expiry email failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendWaReminder(User $tenant, int $daysLeft): bool
    {
        $phone = $tenant->phone ?? '';
        if (empty(trim($phone))) {
            return false;
        }

        try {
            $settings = TenantSettings::getOrCreate($tenant->id);
            $service  = WaGatewayService::forTenant($settings);

            if (! $service) {
                return false;
            }

            $expiryDate  = $tenant->subscription_expires_at->format('d/m/Y');
            $planName    = $tenant->subscriptionPlan?->name ?? 'Langganan';
            $renewUrl    = config('app.url') . '/subscription/renew';

            if ($daysLeft <= 1) {
                $message = "⚠️ *Pengingat Langganan*\n\n"
                    . "Yth. *{$tenant->name}*,\n\n"
                    . "Langganan *{$planName}* Anda akan berakhir *BESOK* ({$expiryDate}).\n\n"
                    . "Segera perpanjang agar layanan tidak terganggu:\n{$renewUrl}\n\n"
                    . "Terima kasih.";
            } else {
                $message = "🔔 *Pengingat Langganan*\n\n"
                    . "Yth. *{$tenant->name}*,\n\n"
                    . "Langganan *{$planName}* Anda akan berakhir dalam *{$daysLeft} hari* ({$expiryDate}).\n\n"
                    . "Perpanjang sekarang:\n{$renewUrl}\n\n"
                    . "Terima kasih.";
            }

            $service->sendMessage($phone, $message);

            Log::info('Subscription reminder sent', ['tenant_id' => $tenant->id, 'days_left' => $daysLeft]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Subscription reminder failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
