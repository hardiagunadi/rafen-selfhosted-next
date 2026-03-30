<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueSelfHostedLicenseRequest;
use App\Http\Requests\UpdateSystemLicensePublicKeyRequest;
use App\Services\LicenseIssuerService;
use App\Services\LicensePublicKeyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SuperAdminLicensePublicKeyController extends Controller
{
    public function index(
        LicensePublicKeyService $licensePublicKeyService,
        LicenseIssuerService $licenseIssuerService,
    ): View {
        return view('super-admin.settings.license-public-key', [
            'snapshot' => $licensePublicKeyService->getSnapshot(),
            'issuer' => $licenseIssuerService->getSnapshot(),
        ]);
    }

    public function update(
        UpdateSystemLicensePublicKeyRequest $request,
        LicensePublicKeyService $licensePublicKeyService,
    ): RedirectResponse {
        abort_unless(
            $licensePublicKeyService->isEditable(),
            403,
            'Public key lisensi dikelola melalui environment aplikasi.'
        );

        $licensePublicKeyService->store($request->validated('license_public_key'));

        return redirect()
            ->route('super-admin.settings.license-public-key')
            ->with('success', 'Public key lisensi berhasil disimpan ke file environment aplikasi.');
    }

    public function issue(
        IssueSelfHostedLicenseRequest $request,
        LicenseIssuerService $licenseIssuerService,
    ): StreamedResponse|RedirectResponse {
        try {
            $payload = $licenseIssuerService->issue(
                customerName: $request->validated('customer_name'),
                instanceName: $request->validated('instance_name'),
                fingerprint: $request->validated('fingerprint'),
                expiresAt: $request->validated('expires_at'),
                allowedHosts: $request->allowedHosts(),
                modules: $request->modules(),
                limits: $request->limits(),
                supportUntil: $request->validated('support_until'),
                graceDays: $request->graceDays(),
                accessMode: $request->accessMode(),
            );
        } catch (Throwable $throwable) {
            return redirect()
                ->route('super-admin.settings.license-public-key')
                ->withInput()
                ->with('error', $throwable->getMessage());
        }

        $filename = 'rafen-license-'
            .$this->slug((string) $payload['customer_name'])
            .'-'
            .$this->slug((string) $payload['instance_name'])
            .'.lic';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function slug(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'license';
    }
}
