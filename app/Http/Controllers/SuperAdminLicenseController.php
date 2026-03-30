<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemLicensePublicKeyRequest;
use App\Http\Requests\UploadSystemLicenseRequest;
use App\Services\LicenseActivationRequestService;
use App\Services\SystemLicenseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SuperAdminLicenseController extends Controller
{
    public function index(SystemLicenseService $systemLicenseService): View
    {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        return view('super-admin.settings.license', [
            'snapshot' => $systemLicenseService->getSnapshot(),
        ]);
    }

    public function update(UploadSystemLicenseRequest $request, SystemLicenseService $systemLicenseService): RedirectResponse
    {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        $license = $systemLicenseService->storeUploadedLicense($request->file('license_file'));

        if ($license->is_valid) {
            return redirect()
                ->route('super-admin.settings.license')
                ->with('success', 'Lisensi sistem berhasil diunggah dan diverifikasi.');
        }

        return redirect()
            ->route('super-admin.settings.license')
            ->with('error', $license->validation_error ?: 'Lisensi sistem gagal diverifikasi.');
    }

    public function updatePublicKey(
        UpdateSystemLicensePublicKeyRequest $request,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);
        abort_unless(
            $systemLicenseService->getSnapshot()['is_public_key_editable'] ?? false,
            403,
            'Public key lisensi dikelola melalui environment aplikasi.'
        );

        $license = $systemLicenseService->storePublicKey($request->validated('license_public_key'));
        $message = 'Public key lisensi berhasil disimpan ke file environment aplikasi.';

        if ($license->is_valid) {
            $message .= ' Lisensi yang sudah diunggah juga berhasil diverifikasi ulang.';
        }

        return redirect()
            ->route('super-admin.settings.license')
            ->with('success', $message);
    }

    public function activationRequest(
        LicenseActivationRequestService $activationRequestService,
        SystemLicenseService $systemLicenseService,
    ): StreamedResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        $payload = $activationRequestService->makePayload();
        $filename = 'rafen-activation-request-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function ensureSelfHostedEnabled(SystemLicenseService $systemLicenseService): void
    {
        if (! $systemLicenseService->isSelfHostedEnabled()) {
            throw new NotFoundHttpException;
        }
    }
}
