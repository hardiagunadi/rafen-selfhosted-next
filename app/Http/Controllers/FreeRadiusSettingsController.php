<?php

namespace App\Http\Controllers;

use App\Services\HotspotRadiusSynchronizer;
use App\Services\RadiusClientsSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use SplFileObject;
use Throwable;

class FreeRadiusSettingsController extends Controller
{
    public function __construct(private Filesystem $filesystem) {}

    public function index(): View
    {
        $clientsPath = (string) config('radius.clients_path');
        $logPath = (string) config('radius.log_path');
        $syncStatus = $this->resolveSyncStatus($clientsPath);
        $logPayload = $this->readLogTail($logPath, 200);

        return view('settings.freeradius', [
            'clientsPath' => $clientsPath,
            'logPath' => $logPath,
            'syncStatus' => $syncStatus,
            'logPayload' => $logPayload,
        ]);
    }

    public function sync(RadiusClientsSynchronizer $synchronizer): RedirectResponse
    {
        try {
            $synchronizer->sync();

            return redirect()
                ->route('settings.freeradius')
                ->with('status', 'Sinkronisasi FreeRADIUS berhasil.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('settings.freeradius')
                ->with('error', 'Sinkronisasi FreeRADIUS gagal: '.$exception->getMessage());
        }
    }

    public function syncReplies(RadiusReplySynchronizer $ppSynchronizer, HotspotRadiusSynchronizer $hotspotSynchronizer): RedirectResponse
    {
        try {
            $pppCount     = $ppSynchronizer->sync();
            $hotspotCount = $hotspotSynchronizer->sync();

            return redirect()
                ->route('settings.freeradius')
                ->with('status', "Sync radcheck/radreply berhasil: {$pppCount} PPP + {$hotspotCount} Hotspot/Voucher.");
        } catch (Throwable $exception) {
            return redirect()
                ->route('settings.freeradius')
                ->with('error', 'Sync radcheck/radreply gagal: '.$exception->getMessage());
        }
    }

    /**
     * @return array{status: string, updated_at: ?string, size: ?int, message: string}
     */
    private function resolveSyncStatus(string $clientsPath): array
    {
        if ($clientsPath === '') {
            return [
                'status' => 'unknown',
                'updated_at' => null,
                'size' => null,
                'message' => 'Path clients.conf belum diatur.',
            ];
        }

        if (! $this->filesystem->exists($clientsPath)) {
            $parent = dirname($clientsPath);
            if ($this->filesystem->isDirectory($parent) && ! $this->filesystem->isReadable($parent)) {
                return [
                    'status' => 'denied',
                    'updated_at' => null,
                    'size' => null,
                    'message' => 'Akses ke direktori clients dibatasi. Periksa izin webserver.',
                ];
            }

            return [
                'status' => 'missing',
                'updated_at' => null,
                'size' => null,
                'message' => 'File clients belum ditemukan.',
            ];
        }

        if (! $this->filesystem->isReadable($clientsPath)) {
            return [
                'status' => 'denied',
                'updated_at' => null,
                'size' => null,
                'message' => 'File clients tidak dapat dibaca. Periksa izin webserver.',
            ];
        }

        $size = $this->filesystem->size($clientsPath);
        $updatedAt = Carbon::createFromTimestamp($this->filesystem->lastModified($clientsPath))
            ->format('Y-m-d H:i:s');

        if ($size === 0) {
            return [
                'status' => 'empty',
                'updated_at' => $updatedAt,
                'size' => $size,
                'message' => 'File clients masih kosong.',
            ];
        }

        return [
            'status' => 'ok',
            'updated_at' => $updatedAt,
            'size' => $size,
            'message' => 'File clients terisi.',
        ];
    }

    /**
     * @return array{lines: array<int, string>, error: ?string}
     */
    private function readLogTail(string $path, int $limit): array
    {
        if ($path === '') {
            return [
                'lines' => [],
                'error' => 'Path log belum diatur.',
            ];
        }

        if (! $this->filesystem->exists($path)) {
            return [
                'lines' => [],
                'error' => 'File log tidak ditemukan.',
            ];
        }

        try {
            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::DROP_NEW_LINE);
            $buffer = [];

            foreach ($file as $line) {
                if ($line === null) {
                    continue;
                }

                $buffer[] = $line;
                if (count($buffer) > $limit) {
                    array_shift($buffer);
                }
            }

            return [
                'lines' => $buffer,
                'error' => null,
            ];
        } catch (FileNotFoundException $exception) {
            return [
                'lines' => [],
                'error' => 'File log tidak ditemukan.',
            ];
        } catch (Throwable $exception) {
            return [
                'lines' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }
}
