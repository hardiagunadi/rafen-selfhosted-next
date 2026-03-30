<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunSelfHostedToolkitActionRequest;
use App\Services\SelfHostedToolkitService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SuperAdminSelfHostedToolkitController extends Controller
{
    use LogsActivity;

    public function index(SelfHostedToolkitService $toolkitService): View
    {
        return view('super-admin.self-hosted-toolkit.index', [
            'actions' => $toolkitService->actionsWithHistory(),
            'historyFile' => $toolkitService->historyPath(),
            'worktreeStatus' => $toolkitService->worktreeStatus(),
        ]);
    }

    public function run(
        RunSelfHostedToolkitActionRequest $request,
        SelfHostedToolkitService $toolkitService,
    ): JsonResponse {
        try {
            $result = $toolkitService->run($request->validated('action'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Self-hosted toolkit action failed unexpectedly.', [
                'action' => $request->validated('action'),
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Toolkit gagal dijalankan: '.$exception->getMessage(),
            ], 500);
        }

        $this->logActivity(
            'super_admin_self_hosted_toolkit_run',
            'SystemCommand',
            null,
            $result['command'],
            (int) $request->user()->id,
            [
                'action' => $result['action'],
                'success' => $result['success'],
                'exit_code' => $result['exit_code'],
                'duration_ms' => $result['duration_ms'],
            ]
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? 'Aksi self-hosted toolkit berhasil dijalankan.'
                : 'Aksi self-hosted toolkit selesai dengan error.',
            'result' => $result,
        ]);
    }

    public function download(string $action, SelfHostedToolkitService $toolkitService): BinaryFileResponse
    {
        try {
            $download = $toolkitService->downloadArtifact($action);
        } catch (RuntimeException $exception) {
            abort(404, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Self-hosted toolkit artifact download failed unexpectedly.', [
                'action' => $action,
                'user_id' => auth()->id(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            abort(500, 'Gagal menyiapkan artifact toolkit untuk diunduh.');
        }

        $this->logActivity(
            'super_admin_self_hosted_toolkit_download',
            'SystemCommandArtifact',
            null,
            $download['download_name'],
            (int) auth()->id(),
            [
                'action' => $action,
                'artifact_path' => $download['source_path'],
            ]
        );

        return response()->download(
            $download['path'],
            $download['download_name'],
            $download['headers'] ?? []
        )->deleteFileAfterSend($download['delete_after_send'] ?? false);
    }
}
