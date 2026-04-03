<?php

namespace App\Services;

use App\Models\SystemLicense;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LicenseUnregisterService
{
    public function __construct(
        private readonly SystemLicenseService $licenseService,
        private readonly LicenseFingerprintService $fingerprintService,
    ) {}

    /**
     * Unregister lisensi aktif dari instance ini.
     *
     * Jika LICENSE_VENDOR_UNREGISTER_URL diset, notifikasi dikirim ke SaaS terlebih dahulu.
     * Jika notifikasi gagal, RuntimeException dilempar dan lisensi tidak dihapus.
     * Jika URL tidak diset (mode offline/air-gap), langsung hapus lokal.
     *
     * @throws RuntimeException jika notifikasi vendor gagal.
     */
    public function unregister(?string $reason = null): void
    {
        $license = $this->licenseService->getCurrent();
        $vendorUrl = (string) config('license.vendor_unregister_url', '');

        if ($vendorUrl !== '') {
            $this->notifyVendor($license, $reason);
        }

        $this->deleteLocally();
    }

    /**
     * @throws RuntimeException jika HTTP request ke vendor gagal atau return non-2xx.
     */
    private function notifyVendor(SystemLicense $license, ?string $reason): void
    {
        $url = (string) config('license.vendor_unregister_url');
        $apiKey = (string) config('license.vendor_api_key', '');

        $payload = [
            'license_id' => $license->license_id,
            'fingerprint' => $this->fingerprintService->generate(),
            'app_url' => (string) config('app.url'),
            'app_name' => (string) config('app.name'),
            'server_name' => php_uname('n'),
            'unregistered_at' => now()->toIso8601String(),
        ];

        if ($reason !== null && $reason !== '') {
            $payload['reason'] = $reason;
        }

        try {
            $request = Http::timeout(15)->acceptJson();

            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, $payload);

            if (! $response->successful()) {
                $body = $response->json('message') ?? $response->body();
                throw new RuntimeException(
                    "Vendor menolak unregister (HTTP {$response->status()}): {$body}"
                );
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LicenseUnregisterService: gagal menghubungi vendor.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal menghubungi server vendor untuk unregister: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function deleteLocally(): void
    {
        $path = (string) config('license.path');

        if (File::exists($path)) {
            File::delete($path);
        }

        SystemLicense::query()->delete();
    }
}
