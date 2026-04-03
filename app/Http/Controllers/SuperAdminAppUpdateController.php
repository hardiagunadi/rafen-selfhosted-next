<?php

namespace App\Http\Controllers;

use App\Services\SelfHostedHeartbeatService;
use App\Services\SelfHostedUpdateRunnerService;
use App\Services\SelfHostedUpdateStatusService;
use App\Services\SystemLicenseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SuperAdminAppUpdateController extends Controller
{
    public function index(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateRunnerService $runnerService,
        SelfHostedUpdateStatusService $updateStatusService,
        SystemLicenseService $systemLicenseService,
    ): View {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        return view('super-admin.settings.app-update', [
            'snapshot' => $updateStatusService->snapshot(),
            'heartbeatSummary' => $heartbeatService->summary(),
            'recentRuns' => $runnerService->recentRuns(),
        ]);
    }

    public function check(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateStatusService $updateStatusService,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        try {
            $snapshot = $updateStatusService->check();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('super-admin.settings.app-update')
                ->with('error', $exception->getMessage());
        }

        $message = ($snapshot['update_available'] ?? false)
            ? 'Cek update selesai. Release baru tersedia untuk instance ini.'
            : 'Cek update selesai. Instance ini sudah menggunakan release terbaru.';

        $heartbeatService->submitBestEffort();

        return redirect()
            ->route('super-admin.settings.app-update')
            ->with('success', $message);
    }

    public function refreshStatus(
        SelfHostedUpdateStatusService $updateStatusService,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        $updateStatusService->refreshLocalSnapshot();

        return redirect()
            ->route('super-admin.settings.app-update')
            ->with('success', 'Snapshot status lokal berhasil disegarkan tanpa panggilan network.');
    }

    public function checkAndHeartbeat(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateStatusService $updateStatusService,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        try {
            $snapshot = $updateStatusService->check();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('super-admin.settings.app-update')
                ->with('error', $exception->getMessage());
        }

        try {
            $heartbeatService->submit();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('super-admin.settings.app-update')
                ->with('error', $this->checkMessage($snapshot).' Heartbeat gagal dikirim: '.$exception->getMessage());
        }

        return redirect()
            ->route('super-admin.settings.app-update')
            ->with('success', $this->checkMessage($snapshot).' Heartbeat status instance berhasil dikirim ke SaaS.');
    }

    public function preflight(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateRunnerService $runnerService,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        $result = $runnerService->apply(
            dryRun: true,
            triggeredByUserId: auth()->id(),
        );

        $heartbeatService->submitBestEffort();

        return redirect()
            ->route('super-admin.settings.app-update')
            ->with(($result['status'] ?? 'failed') === 'dry_run' ? 'success' : 'error', $result['message'] ?? 'Simulasi apply gagal dijalankan.');
    }

    public function heartbeat(
        SelfHostedHeartbeatService $heartbeatService,
        SystemLicenseService $systemLicenseService,
    ): RedirectResponse {
        $this->ensureSelfHostedEnabled($systemLicenseService);

        try {
            $heartbeatService->submit();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('super-admin.settings.app-update')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('super-admin.settings.app-update')
            ->with('success', 'Heartbeat status instance berhasil dikirim ke SaaS.');
    }

    private function ensureSelfHostedEnabled(SystemLicenseService $systemLicenseService): void
    {
        if (! $systemLicenseService->isSelfHostedEnabled()) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function checkMessage(array $snapshot): string
    {
        return ($snapshot['update_available'] ?? false)
            ? 'Cek update selesai. Release baru tersedia untuk instance ini.'
            : 'Cek update selesai. Instance ini sudah menggunakan release terbaru.';
    }
}
