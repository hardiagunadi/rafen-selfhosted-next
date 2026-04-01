<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadSystemLicenseRequest;
use App\Services\LicenseActivationRequestService;
use App\Services\ServerHealthService;
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

    public function update(
        UploadSystemLicenseRequest $request,
        SystemLicenseService $systemLicenseService,
        ServerHealthService $serverHealthService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        $license = $systemLicenseService->storeUploadedLicense($request->file('license_file'));

        if ($license->is_valid) {
            return $this->withServiceBootstrapFeedback(
                redirect()->route('super-admin.settings.license'),
                'Lisensi sistem berhasil diunggah dan diverifikasi.',
                $serverHealthService->startInactiveLicensedServices()
            );
        }

        return redirect()
            ->route('super-admin.settings.license')
            ->with('error', $license->validation_error ?: 'Lisensi sistem gagal diverifikasi.');
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

    /**
     * @param  array{
     *     attempted:int,
     *     started:list<string>,
     *     already_running:list<string>,
     *     failed:list<array{name:string,message:string}>
     * }  $summary
     */
    private function withServiceBootstrapFeedback(
        RedirectResponse $redirect,
        string $successMessage,
        array $summary
    ): RedirectResponse
    {
        $message = $successMessage;

        if ($summary['started'] !== []) {
            $message .= sprintf(
                ' %d layanan berlisensi berhasil dijalankan otomatis: %s.',
                count($summary['started']),
                implode(', ', $summary['started'])
            );
        }

        $redirect->with('success', $message);

        if ($summary['failed'] !== []) {
            $redirect->with('warning', 'Lisensi aktif, tetapi beberapa layanan gagal dijalankan otomatis: '.implode('; ', array_map(
                fn (array $item): string => $item['name'].' ('.$item['message'].')',
                $summary['failed']
            )));
        }

        return $redirect;
    }
}
