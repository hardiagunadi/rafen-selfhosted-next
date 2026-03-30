<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    use HasFactory;

    public const TEMPLATE_ROTATION_SEPARATOR = '---';

    protected $fillable = [
        'user_id',
        'business_name',
        'business_logo',
        'invoice_logo',
        'business_phone',
        'business_email',
        'business_address',
        'npwp',
        'website',
        'invoice_prefix',
        'invoice_footer',
        'invoice_notes',
        'enable_qris_payment',
        'enable_va_payment',
        'enable_manual_payment',
        'tripay_api_key',
        'tripay_private_key',
        'tripay_merchant_code',
        'tripay_sandbox',
        'enabled_payment_channels',
        'payment_expiry_hours',
        'auto_isolate_unpaid',
        'grace_period_days',
        'wa_gateway_url',
        'wa_gateway_token',
        'wa_gateway_key',
        'wa_webhook_secret',
        'wa_notify_registration',
        'wa_notify_invoice',
        'wa_notify_payment',
        'wa_broadcast_enabled',
        'wa_blast_multi_device',
        'wa_blast_message_variation',
        'wa_blast_delay_min_ms',
        'wa_blast_delay_max_ms',
        'wa_antispam_enabled',
        'wa_antispam_delay_ms',
        'wa_antispam_max_per_minute',
        'wa_msg_randomize',
        'wa_template_registration',
        'wa_template_invoice',
        'wa_template_payment',
        'wa_notify_on_process',
        'wa_template_on_process',
        'billing_date',
        'isolir_page_title',
        'isolir_page_body',
        'isolir_page_contact',
        'isolir_page_bg_color',
        'isolir_page_accent_color',
        // Payment Gateways
        'active_gateway',
        'midtrans_server_key',
        'midtrans_client_key',
        'midtrans_merchant_id',
        'midtrans_sandbox',
        'duitku_merchant_code',
        'duitku_api_key',
        'duitku_sandbox',
        'ipaymu_va',
        'ipaymu_api_key',
        'ipaymu_sandbox',
        'xendit_secret_key',
        'xendit_webhook_token',
        'xendit_sandbox',
        'map_cache_enabled',
        'map_cache_center_lat',
        'map_cache_center_lng',
        'map_cache_radius_km',
        'map_cache_min_zoom',
        'map_cache_max_zoom',
        'map_cache_version',
        'module_hotspot_enabled',
        'shift_feature_enabled',
        'wa_shift_group_number',
        'genieacs_url',
        'genieacs_username',
        'genieacs_password',
        'genieacs_cr_username',
        'genieacs_cr_password',
        'portal_slug',
        // Platform Gateway
        'use_platform_gateway',
        'platform_payment_gateway_id',
        // Platform WA Device
        'wa_platform_device_id',
        // WA Ticket Group
        'wa_ticket_group_id',
        'wa_ticket_group_name',
    ];

    protected function casts(): array
    {
        return [
            'enable_qris_payment' => 'boolean',
            'enable_va_payment' => 'boolean',
            'enable_manual_payment' => 'boolean',
            'tripay_sandbox' => 'boolean',
            'enabled_payment_channels' => 'array',
            'payment_expiry_hours' => 'integer',
            'auto_isolate_unpaid' => 'boolean',
            'grace_period_days' => 'integer',
            'wa_notify_registration' => 'boolean',
            'wa_notify_invoice' => 'boolean',
            'wa_notify_payment' => 'boolean',
            'wa_notify_on_process' => 'boolean',
            'wa_broadcast_enabled' => 'boolean',
            'wa_blast_multi_device' => 'boolean',
            'wa_blast_message_variation' => 'boolean',
            'wa_blast_delay_min_ms' => 'integer',
            'wa_blast_delay_max_ms' => 'integer',
            'wa_antispam_enabled' => 'boolean',
            'wa_antispam_delay_ms' => 'integer',
            'wa_antispam_max_per_minute' => 'integer',
            'wa_msg_randomize' => 'boolean',
            'billing_date' => 'integer',
            'midtrans_sandbox' => 'boolean',
            'duitku_sandbox' => 'boolean',
            'ipaymu_sandbox' => 'boolean',
            'xendit_sandbox' => 'boolean',
            'map_cache_enabled' => 'boolean',
            'map_cache_center_lat' => 'float',
            'map_cache_center_lng' => 'float',
            'map_cache_radius_km' => 'float',
            'map_cache_min_zoom' => 'integer',
            'map_cache_max_zoom' => 'integer',
            'map_cache_version' => 'integer',
            'module_hotspot_enabled' => 'boolean',
            'shift_feature_enabled' => 'boolean',
            'use_platform_gateway' => 'boolean',
            'platform_payment_gateway_id' => 'integer',
            'wa_platform_device_id' => 'integer',
        ];
    }

    public function isShiftModuleEnabled(): bool
    {
        return (bool) $this->shift_feature_enabled;
    }

    public function getIsolirPageTitle(): string
    {
        return $this->isolir_page_title ?: ($this->business_name ? 'Layanan '.$this->business_name.' Dinonaktifkan' : 'Layanan Internet Dinonaktifkan');
    }

    public function getIsolirPageBody(): string
    {
        return $this->isolir_page_body ?: "Layanan internet Anda telah dinonaktifkan sementara karena belum melakukan pembayaran.\n\nSilakan segera lakukan pembayaran untuk mengaktifkan kembali layanan Anda.";
    }

    public function getIsolirPageContact(): string
    {
        $parts = array_filter([$this->business_phone, $this->business_email]);

        return $this->isolir_page_contact ?: implode(' | ', $parts);
    }

    protected $hidden = [
        'tripay_api_key',
        'tripay_private_key',
        'midtrans_server_key',
        'duitku_api_key',
        'ipaymu_api_key',
        'xendit_secret_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platformPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'platform_payment_gateway_id');
    }

    public function waPlatformDevice(): BelongsTo
    {
        return $this->belongsTo(WaMultiSessionDevice::class, 'wa_platform_device_id');
    }

    public function hasWaPlatformDevice(): bool
    {
        return ! empty($this->wa_platform_device_id);
    }

    public function hasTripayConfigured(): bool
    {
        return ! empty($this->tripay_api_key)
            && ! empty($this->tripay_private_key)
            && ! empty($this->tripay_merchant_code);
    }

    /**
     * URL login portal pelanggan untuk tenant ini.
     * Jika slug belum diset, fallback ke /portal/login (legacy).
     */
    public function portalLoginUrl(): string
    {
        if (! empty($this->admin_subdomain)) {
            $mainDomain = config('app.main_domain', 'watumalang.online');

            return 'https://'.$this->admin_subdomain.'.'.$mainDomain.'/portal/login';
        }

        return route('portal.login');
    }

    public function hasGenieacsConfigured(): bool
    {
        return ! empty($this->genieacs_url);
    }

    /**
     * CR username used by GenieACS to trigger Connection Request to CPE.
     * Falls back to a deterministic per-tenant value derived from user_id,
     * so every tenant gets a unique credential even if not explicitly configured.
     * Last resort: global .env value.
     */
    public function resolvedCrUsername(): string
    {
        if (! empty($this->genieacs_cr_username)) {
            return $this->genieacs_cr_username;
        }

        if ($this->user_id) {
            return 'rafen_cr_'.$this->user_id;
        }

        return config('genieacs.connection_request_username', 'rafen');
    }

    /**
     * CR password used by GenieACS to trigger Connection Request to CPE.
     * Auto-generated deterministically from owner_id + app key when not set,
     * so the value is stable across restarts without requiring manual config.
     */
    public function resolvedCrPassword(): string
    {
        if (! empty($this->genieacs_cr_password)) {
            return $this->genieacs_cr_password;
        }

        if ($this->user_id) {
            return substr(hash('sha256', config('app.key').':cr:'.$this->user_id), 0, 16);
        }

        return config('genieacs.connection_request_password', 'rafen2024');
    }

    public function hasWaConfigured(): bool
    {
        // Direct gateway config (per-tenant)
        if (trim((string) ($this->wa_gateway_url ?? '')) !== ''
            && trim((string) ($this->wa_gateway_token ?? '')) !== '') {
            return true;
        }

        // Platform multi-session — URL dan token dari config .env, bukan dari tenant settings
        $platformUrl = trim((string) config('wa.multi_session.public_url', ''));
        $platformToken = trim((string) config('wa.multi_session.auth_token', ''));

        return $platformUrl !== '' && $platformToken !== '';
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultTemplateVariants(string $type): array
    {
        return match ($type) {
            'registration' => [
                "Halo Bapak/Ibu {name},\n\nTerima kasih sudah bergabung bersama layanan kami.\nBerikut data registrasi Anda:\n- ID Pelanggan: {customer_id}\n- Username: {username}\n- Paket: {profile}\n- Tipe Layanan: {service}\n- Biaya Paket: {total}\n- Jatuh Tempo: {due_date}\n\nKalau ada pertanyaan, silakan hubungi CS kami di {cs_number}.",
                "Selamat datang Bapak/Ibu {name},\n\nPendaftaran internet Anda sudah kami catat dengan detail berikut:\n- ID Pelanggan: {customer_id}\n- Username: {username}\n- Paket: {profile}\n- Jenis Layanan: {service}\n- Biaya: {total}\n\nAgar layanan tetap aktif, mohon lakukan pembayaran sebelum jatuh tempo. Kami siap bantu di {cs_number}.",
                "Permisi Bapak/Ibu {name},\n\nRegistrasi Anda berhasil diproses.\nRingkasan akun:\n- ID Pelanggan: {customer_id}\n- Username: {username}\n- Paket: {profile}\n- Layanan: {service}\n- Harga Paket: {total}\n\nTerima kasih atas kepercayaannya. Jika perlu bantuan cepat, hubungi {cs_number}.",
            ],
            'invoice' => [
                "Halo Bapak/Ibu {name},\n\nTagihan Anda sudah terbit dengan nomor {invoice_no}.\n- ID Pelanggan: {customer_id}\n- Paket: {profile}\n- Tipe Layanan: {service}\n- Jatuh Tempo: {due_date}\n- Total Tagihan: {total}\n\nPembayaran bisa langsung melalui link berikut:\n{payment_link}\n\nAtau transfer ke rekening:\n{bank_account}\n\nJika sudah transfer, mohon sertakan ID pelanggan saat konfirmasi ke {cs_number}.",
                "Selamat siang Bapak/Ibu {name},\n\nIzin mengingatkan, invoice {invoice_no} sudah kami terbitkan.\n- ID Pelanggan: {customer_id}\n- Paket Langganan: {profile}\n- Jatuh Tempo: {due_date}\n- Tagihan: {total}\n\nBayar online:\n{payment_link}\n\nRekening pembayaran:\n{bank_account}\n\nKami siap bantu di {cs_number}.",
                "Halo Bapak/Ibu {name},\n\nBerikut ringkasan tagihan bulan ini:\n- No Invoice: {invoice_no}\n- ID Pelanggan: {customer_id}\n- Paket: {profile}\n- Tipe Layanan: {service}\n- Jatuh Tempo: {due_date}\n- Total: {total}\n\nLink pembayaran:\n{payment_link}\n\nJika transfer manual, gunakan rekening berikut:\n{bank_account}\n\nInfo lanjutan dapat menghubungi {cs_number}.",
            ],
            'payment' => [
                "Halo Bapak/Ibu {name},\n\nTerima kasih, pembayaran invoice {invoice_no} sudah kami terima.\n- ID Pelanggan: {customer_id}\n- Paket: {profile}\n- Tipe Layanan: {service}\n- Total Dibayar: {total}\n- Waktu Konfirmasi: {paid_at}\n\nBila layanan masih belum normal, silakan hubungi {cs_number}.",
                "Terima kasih Bapak/Ibu {name},\n\nPembayaran Anda untuk invoice {invoice_no} sudah berhasil dikonfirmasi.\n- ID Pelanggan: {customer_id}\n- Paket: {profile}\n- Jumlah: {total}\n- Dikonfirmasi pada: {paid_at}\n\nKalau ada kendala, tim kami siap membantu di {cs_number}.",
                "Kabar baik Bapak/Ibu {name},\n\nTagihan {invoice_no} sudah dinyatakan lunas.\n- ID Pelanggan: {customer_id}\n- Layanan: {service}\n- Paket: {profile}\n- Pembayaran: {total}\n- Waktu: {paid_at}\n\nTerima kasih atas pembayaran tepat waktunya. Bantuan cepat: {cs_number}.",
            ],
            'on_process' => [
                "Halo Bapak/Ibu {name},\n\nPendaftaran Anda sedang kami proses.\nDetail sementara:\n- ID Pelanggan: {customer_id}\n- Paket: {profile}\n- Tipe Layanan: {service}\n- Tagihan Awal: {total}\n\nSilakan lakukan pembayaran ke rekening berikut agar layanan dapat segera diaktifkan:\n{bank_account}\n\nMohon cantumkan ID Pelanggan saat transfer. Jika ada pertanyaan, hubungi {cs_number}.",
            ],
            default => [],
        };
    }

    public function getDefaultTemplate(string $type): string
    {
        return $this->getDefaultTemplateVariants($type)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function getTemplateVariants(string $type): array
    {
        $custom = match ($type) {
            'registration' => $this->wa_template_registration,
            'invoice' => $this->wa_template_invoice,
            'payment' => $this->wa_template_payment,
            'on_process' => $this->wa_template_on_process,
            default => null,
        };

        $customVariants = $this->splitTemplateVariants($custom);
        if ($customVariants !== []) {
            return $customVariants;
        }

        return $this->getDefaultTemplateVariants($type);
    }

    public function getTemplate(string $type): string
    {
        return $this->getTemplateVariants($type)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function splitTemplateVariants(?string $template): array
    {
        $template = trim((string) $template);
        if ($template === '') {
            return [];
        }

        $pattern = '/\R\s*'.preg_quote(self::TEMPLATE_ROTATION_SEPARATOR, '/').'\s*\R/u';
        $parts = preg_split($pattern, $template) ?: [];
        $variants = array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts), static fn (string $part): bool => $part !== ''));

        if ($variants === []) {
            return [$template];
        }

        return $variants;
    }

    public function hasMidtransConfigured(): bool
    {
        return ! empty($this->midtrans_server_key) && ! empty($this->midtrans_client_key);
    }

    public function hasDuitkuConfigured(): bool
    {
        return ! empty($this->duitku_merchant_code) && ! empty($this->duitku_api_key);
    }

    public function hasIPaymuConfigured(): bool
    {
        return ! empty($this->ipaymu_va) && ! empty($this->ipaymu_api_key);
    }

    public function hasXenditConfigured(): bool
    {
        return ! empty($this->xendit_secret_key);
    }

    public function hasActiveGateway(): bool
    {
        return match ($this->active_gateway ?? 'tripay') {
            'midtrans' => $this->hasMidtransConfigured(),
            'duitku' => $this->hasDuitkuConfigured(),
            'ipaymu' => $this->hasIPaymuConfigured(),
            'xendit' => $this->hasXenditConfigured(),
            default => $this->hasTripayConfigured(),
        };
    }

    public function getActiveGateway(): string
    {
        return $this->active_gateway ?? 'tripay';
    }

    public function isUsingPlatformGateway(): bool
    {
        return (bool) $this->use_platform_gateway && ! empty($this->platform_payment_gateway_id);
    }

    public function hasPlatformGatewayConfigured(): bool
    {
        return $this->isUsingPlatformGateway()
            && $this->platformPaymentGateway?->is_active === true;
    }

    public function hasPaymentGateway(): bool
    {
        if ($this->isUsingPlatformGateway() && $this->hasPlatformGatewayConfigured()) {
            return (bool) ($this->enable_qris_payment || $this->enable_va_payment);
        }

        return $this->hasActiveGateway() && ($this->enable_qris_payment || $this->enable_va_payment);
    }

    public function getTripayApiUrl(): string
    {
        return $this->tripay_sandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }

    public function getEnabledChannels(): array
    {
        return $this->enabled_payment_channels ?? [];
    }

    public function isHotspotModuleEnabled(): bool
    {
        return (bool) ($this->module_hotspot_enabled ?? true);
    }

    public static function getOrCreate(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'invoice_prefix' => 'INV',
                'enable_manual_payment' => true,
                'payment_expiry_hours' => 24,
                'auto_isolate_unpaid' => true,
                'grace_period_days' => 3,
                'module_hotspot_enabled' => true,
            ]
        );
    }
}
