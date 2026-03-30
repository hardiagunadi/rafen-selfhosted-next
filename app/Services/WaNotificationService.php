<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaNotificationService
{
    /**
     * @var array<string, int>
     */
    private static array $fallbackRotationCounters = [];

    /**
     * Notifikasi saat pelanggan PPP atau Hotspot baru didaftarkan.
     */
    public static function notifyRegistration(TenantSettings $settings, PppUser|HotspotUser $user): void
    {
        if (! $settings->wa_notify_registration) {
            return;
        }

        $phone = $user->nomor_hp ?? '';
        $context = ['event' => 'registration', 'user_id' => $user->id, 'username' => $user->username];

        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', $context);

            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $isPpp = $user instanceof PppUser;
            $serviceLabel = $isPpp ? 'PPPoE' : 'Hotspot';
            $profileName = $isPpp
                ? ($user->profile->name ?? '-')
                : ($user->hotspotProfile->name ?? '-');
            $dueDate = $isPpp ? ($user->jatuh_tempo ? \Carbon\Carbon::parse($user->jatuh_tempo)->format('d/m/Y') : '-') : '-';
            $customerId = $user->customer_id ?? $user->username ?? '-';
            $harga = $isPpp
                ? ($user->profile ? 'Rp '.number_format($user->profile->harga ?? 0, 0, ',', '.') : '-')
                : '-';
            $csNumber = $settings->business_phone ?? '-';
            $customerName = self::normalizeCustomerName($user->customer_name ?? null);

            $template = self::pickTemplateVariant($settings, 'registration');

            $portalUrl = $settings->portalLoginUrl();
            $passwordClientarea = ($isPpp ? ($user->password_clientarea ?? '-') : '-');

            $message = self::renderTemplate($template, [
                'name' => $customerName,
                'username' => $user->username,
                'service' => $serviceLabel,
                'profile' => $profileName,
                'due_date' => $dueDate,
                'customer_id' => $customerId,
                'total' => $harga,
                'cs_number' => $csNumber,
                'portal_url' => $portalUrl,
                'password_clientarea' => $passwordClientarea,
            ]);

            $service->sendMessage($phone, $message, $context);
        } catch (\Throwable $e) {
            Log::warning('WA notifyRegistration failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }

    /**
     * Notifikasi saat invoice baru dibuat (tagihan terbit).
     */
    public static function notifyInvoiceCreated(TenantSettings $settings, Invoice $invoice, PppUser $user): void
    {
        if (! $settings->wa_notify_invoice) {
            return;
        }

        $phone = $user->nomor_hp ?? '';
        $context = ['event' => 'invoice_created', 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'user_id' => $user->id];

        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', $context);

            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $template = self::pickTemplateVariant($settings, 'invoice');

            $customerId = $invoice->customer_id ?? ($user->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? '-';
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : '-';
            $csNumber = $settings->business_phone ?? '-';
            $customerName = self::normalizeCustomerName($invoice->customer_name ?? null);

            // Bank accounts
            $bankAccounts = $user->owner?->bankAccounts()->active()->get()
                ?? \App\Models\BankAccount::where('user_id', $invoice->owner_id)->where('is_active', true)->get();
            $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");

            // Payment link (generate token jika belum ada)
            if (empty($invoice->payment_token)) {
                $invoice->update(['payment_token' => \App\Models\Invoice::generatePaymentToken()]);
            }
            $paymentLink = route('customer.invoice', $invoice->payment_token);

            $message = self::renderTemplate($template, [
                'name' => $customerName,
                'invoice_no' => $invoice->invoice_number,
                'total' => 'Rp '.number_format($invoice->total, 0, ',', '.'),
                'due_date' => $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                'customer_id' => $customerId,
                'profile' => $profileName,
                'service' => $serviceType,
                'cs_number' => $csNumber,
                'bank_account' => $bankLines,
                'payment_link' => $paymentLink,
            ]);

            $service->sendMessage($phone, $message, $context);
        } catch (\Throwable $e) {
            Log::warning('WA notifyInvoiceCreated failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
        }
    }

    /**
     * Notifikasi saat pelanggan didaftarkan dengan status ON PROCESS.
     * Berisi info tagihan + instruksi pembayaran agar layanan bisa diaktifkan.
     */
    public static function notifyOnProcess(TenantSettings $settings, PppUser|HotspotUser $user, ?Invoice $invoice = null): void
    {
        if (! ($settings->wa_notify_on_process ?? true)) {
            return;
        }

        $phone = $user->nomor_hp ?? '';
        $context = ['event' => 'on_process', 'user_id' => $user->id, 'username' => $user->username];

        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', $context);

            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $template = self::pickTemplateVariant($settings, 'on_process');

            $isPpp = $user instanceof PppUser;
            $profileName = $isPpp
                ? ($user->profile->name ?? '-')
                : ($user->hotspotProfile->name ?? '-');
            $serviceLabel = $isPpp ? 'PPPoE' : 'Hotspot';
            $customerId = $user->customer_id ?? $user->username ?? '-';
            $csNumber = $settings->business_phone ?? '-';
            $customerName = self::normalizeCustomerName($user->customer_name ?? null);

            $bankAccounts = $user->owner?->bankAccounts()->active()->get()
                ?? \App\Models\BankAccount::where('user_id', $settings->user_id)->where('is_active', true)->get();
            $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");

            $total = $invoice ? 'Rp '.number_format($invoice->total, 0, ',', '.') : '-';

            $message = self::renderTemplate($template, [
                'name' => $customerName,
                'customer_id' => $customerId,
                'profile' => $profileName,
                'service' => $serviceLabel,
                'total' => $total,
                'cs_number' => $csNumber,
                'bank_account' => $bankLines,
            ]);

            $service->sendMessage($phone, $message, $context);
        } catch (\Throwable $e) {
            Log::warning('WA notifyOnProcess failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }

    /**
     * Notifikasi saat invoice sudah dibayar / pembayaran dikonfirmasi.
     */
    public static function notifyInvoicePaid(TenantSettings $settings, Invoice $invoice): void
    {
        if (! $settings->wa_notify_payment) {
            return;
        }

        $context = ['event' => 'invoice_paid', 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number];

        if (! $invoice->pppUser) {
            Log::info('WA skip: pelanggan (pppUser) tidak ditemukan', $context);

            return;
        }

        $phone = $invoice->pppUser->nomor_hp ?? '';

        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', array_merge($context, ['user_id' => $invoice->pppUser->id]));

            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $template = self::pickTemplateVariant($settings, 'payment');

            $paidAt = $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
            $customerId = $invoice->customer_id ?? ($invoice->pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? '-';
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : '-';
            $csNumber = $settings->business_phone ?? '-';
            $customerName = self::normalizeCustomerName($invoice->customer_name ?? null);

            $message = self::renderTemplate($template, [
                'name' => $customerName,
                'invoice_no' => $invoice->invoice_number,
                'total' => 'Rp '.number_format($invoice->total, 0, ',', '.'),
                'paid_at' => $paidAt,
                'customer_id' => $customerId,
                'profile' => $profileName,
                'service' => $serviceType,
                'cs_number' => $csNumber,
            ]);

            $service->sendMessage($phone, $message, array_merge($context, ['user_id' => $invoice->pppUser->id]));
        } catch (\Throwable $e) {
            Log::warning('WA notifyInvoicePaid failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
        }
    }

    private static function pickTemplateVariant(TenantSettings $settings, string $type): string
    {
        $variants = $settings->getTemplateVariants($type);
        if ($variants === []) {
            return '';
        }

        if (count($variants) === 1) {
            return $variants[0];
        }

        $index = self::nextRotationIndex($settings, $type, count($variants));

        return $variants[$index];
    }

    private static function nextRotationIndex(TenantSettings $settings, string $type, int $variantCount): int
    {
        $ownerId = (int) ($settings->user_id ?? 0);
        $counterKey = "wa:template-rotation:{$ownerId}:{$type}";
        $counter = 0;

        try {
            $previous = (int) Cache::get($counterKey, 0);
            $counter = $previous >= 1_000_000 ? 1 : ($previous + 1);
            Cache::put($counterKey, $counter, now()->addDays(30));
        } catch (\Throwable) {
            $fallbackKey = "{$ownerId}:{$type}";
            $previous = self::$fallbackRotationCounters[$fallbackKey] ?? 0;
            $counter = $previous >= 1_000_000 ? 1 : ($previous + 1);
            self::$fallbackRotationCounters[$fallbackKey] = $counter;
        }

        if ($counter <= 0) {
            $counter = 1;
        }

        return ($counter - 1) % max(1, $variantCount);
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    private static function renderTemplate(string $template, array $replacements): string
    {
        $search = [];
        $replace = [];

        foreach ($replacements as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }

    /**
     * Notifikasi WA selamat datang ke nomor HP tenant yang baru terdaftar.
     * Menggunakan device WA global (super admin / device aktif pertama di sistem).
     * Berlaku untuk pendaftaran mandiri maupun didaftarkan super admin.
     */
    public static function notifyNewTenantRegistered(\App\Models\User $tenant, string $subdomain): void
    {
        try {
            $phone = self::normalizePhone(trim((string) ($tenant->phone ?? '')));
            if ($phone === '') {
                Log::info('WA notifyNewTenantRegistered skip: nomor HP tenant kosong', ['tenant_id' => $tenant->id]);

                return;
            }

            $service = WaGatewayService::forSuperAdmin();
            if (! $service) {
                Log::info('WA notifyNewTenantRegistered skip: no WA device configured');

                return;
            }

            $mainDomain  = config('app.main_domain', 'rafen.id');
            $loginUrl    = 'https://' . $subdomain . '.' . $mainDomain . '/login';

            $message = "*Selamat Datang di RAFEN Manager!* 🎉\n\n"
                . "Halo {$tenant->name},\n\n"
                . "Akun Anda telah berhasil didaftarkan.\n\n"
                . "*Detail Akun:*\n"
                . "Email: {$tenant->email}\n"
                . "Subdomain: {$subdomain}.{$mainDomain}\n\n"
                . "*Link Login:*\n"
                . $loginUrl . "\n\n"
                . "Anda mendapatkan *14 hari trial gratis*. Silakan login dan mulai kelola jaringan ISP Anda.\n\n"
                . "Butuh bantuan? Hubungi tim support kami.";

            $service->sendMessage($phone, $message, ['event' => 'new_tenant_registered', 'tenant_id' => $tenant->id]);
        } catch (\Throwable $e) {
            Log::warning('WA notifyNewTenantRegistered failed', ['error' => $e->getMessage(), 'tenant_id' => $tenant->id]);
        }
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }

    private static function normalizeCustomerName(?string $name): string
    {
        $cleaned = trim((string) $name);

        return $cleaned !== '' ? $cleaned : 'Pelanggan';
    }
}
