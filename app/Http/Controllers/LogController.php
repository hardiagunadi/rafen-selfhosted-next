<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CpeDevice;
use App\Models\LoginLog;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\WaBlastLog;
use App\Models\WaWebhookLog;
use App\Services\GenieAcsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    private function denyTeknisi(): void
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }
    }

    // ── Log Login ─────────────────────────────────────────────────────────────

    public function loginIndex(): View
    {
        $this->denyTeknisi();

        return view('logs.login');
    }

    public function loginDatatable(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        // Build set of user IDs this user can see login logs for
        $visibleUserIds = $user->isSuperAdmin()
            ? null
            : array_merge([$user->id], $user->subUsers()->pluck('id')->all());

        $query = LoginLog::with('user')
            ->when($visibleUserIds !== null, fn($q) => $q->whereIn('user_id', $visibleUserIds))
            ->when($request->filled('event'), fn($q) => $q->where('event', $request->event))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn($q2) => $q2->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at');

        $total    = LoginLog::when($visibleUserIds !== null, fn($q) => $q->whereIn('user_id', $visibleUserIds))->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'         => $r->id,
                'event'      => $r->event,
                'email'      => $r->email ?? '-',
                'name'       => $r->user?->name ?? '-',
                'ip_address' => $r->ip_address ?? '-',
                'user_agent' => $r->user_agent ?? '-',
                'created_at' => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            ]),
        ]);
    }

    // ── Log Aktivitas User ────────────────────────────────────────────────────

    public function activityIndex(): View
    {
        $this->denyTeknisi();

        $user    = auth()->user();
        $tenants = $user->isSuperAdmin()
            ? \App\Models\User::where('role', 'administrator')->whereNull('parent_id')->orderBy('name')->get(['id', 'name'])
            : null;

        return view('logs.activity', compact('tenants'));
    }

    public function activityData(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        $query = ActivityLog::with(['user', 'owner'])
            ->when(! $user->isSuperAdmin(), fn($q) => $q->where('owner_id', $user->effectiveOwnerId()))
            ->when($user->isSuperAdmin() && $request->filled('owner_id'), fn($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->when($request->filled('subject_type'), fn($q) => $q->where('subject_type', $request->subject_type))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('subject_label', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($q3) => $q3->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->orderByDesc('created_at');

        $total    = (clone $query)->count();
        $filtered = $total;
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 25)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'            => $r->id,
                'created_at'    => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
                'user_name'     => $r->user?->name ?? '-',
                'user_email'    => $r->user?->email ?? '-',
                'action'        => $r->action,
                'subject_type'  => $r->subject_type,
                'subject_label' => $r->subject_label ?? '-',
                'ip_address'    => $r->ip_address ?? '-',
                'owner_name'    => $user->isSuperAdmin() ? ($r->owner?->name ?? '-') : null,
                'owner_email'   => $user->isSuperAdmin() ? ($r->owner?->email ?? '-') : null,
            ]),
        ]);
    }

    // ── Log BG Process (jobs & failed_jobs) ───────────────────────────────────

    public function bgProcessIndex(): View
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $stats = [
            'pending'    => \DB::table('jobs')->count(),
            'failed'     => \DB::table('failed_jobs')->count(),
            'batches'    => \DB::table('job_batches')->count(),
        ];

        return view('logs.bg-process', compact('stats'));
    }

    public function bgProcessDatatable(Request $request): JsonResponse
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $type = $request->input('type', 'failed');

        $search = $request->input('search.value', $request->input('search', ''));

        if ($type === 'pending') {
            $query = \DB::table('jobs')
                ->when($search !== '', fn($q) => $q->where('queue', 'like', '%'.$search.'%'))
                ->orderByDesc('created_at');

            $total    = \DB::table('jobs')->count();
            $filtered = $query->count();
            $rows     = $query->offset($request->integer('start'))
                ->limit(max(1, $request->integer('length', 20)))->get();

            $data = $rows->map(fn($r) => [
                'id'           => $r->id,
                'queue'        => $r->queue,
                'attempts'     => $r->attempts,
                'payload_name' => $this->extractJobName($r->payload),
                'created_at'   => date('Y-m-d H:i:s', $r->created_at),
                'available_at' => date('Y-m-d H:i:s', $r->available_at),
            ]);
        } else {
            $query = \DB::table('failed_jobs')
                ->when($search !== '', fn($q) => $q->where('queue', 'like', '%'.$search.'%')
                    ->orWhere('exception', 'like', '%'.$search.'%'))
                ->orderByDesc('failed_at');

            $total    = \DB::table('failed_jobs')->count();
            $filtered = $query->count();
            $rows     = $query->offset($request->integer('start'))
                ->limit(max(1, $request->integer('length', 20)))->get();

            $data = $rows->map(fn($r) => [
                'id'           => $r->id,
                'uuid'         => $r->uuid,
                'queue'        => $r->queue,
                'payload_name' => $this->extractJobName($r->payload),
                'exception'    => mb_substr($r->exception, 0, 200),
                'failed_at'    => $r->failed_at,
            ]);
        }

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    // ── Log Auth Radius ───────────────────────────────────────────────────────

    public function radiusAuthIndex(): View
    {
        $this->denyTeknisi();

        return view('logs.radius-auth');
    }

    public function radiusAuthDatatable(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        $ownedUsernames = $user->isSuperAdmin()
            ? null
            : PppUser::where('owner_id', $user->id)->pluck('username');

        $query = \DB::table('radpostauth')
            ->when($ownedUsernames !== null, fn($q) => $q->whereIn('username', $ownedUsernames))
            ->when($request->filled('reply'), fn($q) => $q->where('reply', $request->reply))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn($q2) => $q2->where('username', 'like', "%{$search}%")
                    ->orWhere('reply', 'like', "%{$search}%"));
            })
            ->orderByDesc('authdate');

        $total    = \DB::table('radpostauth')
            ->when($ownedUsernames !== null, fn($q) => $q->whereIn('username', $ownedUsernames))
            ->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'       => $r->id,
                'username' => $r->username,
                'reply'    => $r->reply,
                'authdate' => $r->authdate,
            ]),
        ]);
    }

    // ── Log Pengiriman WA ─────────────────────────────────────────────────────

    public function waPengirimanIndex(): View
    {
        $this->denyTeknisi();

        return view('logs.wa-pengiriman');
    }

    public function waBlastDatatable(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        $query = WaBlastLog::query()
            ->accessibleBy($user)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('event'), fn($q) => $q->where('event', $request->event))
            ->when($request->filled('sent_by_id'), fn($q) => $q->where('sent_by_id', $request->sent_by_id))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('phone', 'like', "%{$search}%")
                   ->orWhere('customer_name', 'like', "%{$search}%")
                   ->orWhere('username', 'like', "%{$search}%")
                   ->orWhere('invoice_number', 'like', "%{$search}%")
                   ->orWhere('sent_by_name', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total    = WaBlastLog::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'created_at'    => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
                'event'         => $r->event,
                'sent_by'       => $r->sent_by_name ?? ($r->sent_by_id ? '#' . $r->sent_by_id : 'Sistem'),
                'phone'         => $r->phone ?? '-',
                'customer_name' => $r->customer_name ?? '-',
                'username'      => $r->username ?? '-',
                'invoice_number'=> $r->invoice_number ?? '-',
                'status'        => $r->status,
                'reason'        => $r->reason ?? '-',
                'ref_id'        => $r->ref_id ?? '-',
                'message'       => $r->message ?? '',
            ]),
        ]);
    }

    public function waWebhookDatatable(Request $request): JsonResponse
    {
        $this->denyTeknisi();

        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        $query = WaWebhookLog::query()
            ->accessibleBy($user)
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->event_type))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('sender', 'like', "%{$search}%")
                   ->orWhere('message', 'like', "%{$search}%")
                   ->orWhere('session_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total    = WaWebhookLog::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(function ($r) {
                $payload = is_array($r->payload) ? $r->payload : [];
                $mediaType = $this->detectMediaType($payload);
                $payloadPreview = $payload !== [] ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

                return [
                    'created_at'      => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
                    'event_type'      => $r->event_type,
                    'session_id'      => $r->session_id ?? '-',
                    'sender'          => $r->sender ?? '-',
                    'message'         => $r->message ?? '-',
                    'status'          => $r->status ?? '-',
                    'media_type'      => $mediaType,
                    'has_payload'     => $payload !== [],
                    'payload_preview' => mb_substr($payloadPreview, 0, 4000),
                ];
            }),
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function detectMediaType(array $payload): ?string
    {
        $msg = $payload['message'] ?? $payload['data']['message'] ?? null;
        if (is_array($msg)) {
            if (isset($msg['imageMessage']))    return 'image';
            if (isset($msg['videoMessage']))    return 'video';
            if (isset($msg['audioMessage']))    return 'audio';
            if (isset($msg['documentMessage'])) return 'document';
            if (isset($msg['stickerMessage']))  return 'sticker';
            if (isset($msg['locationMessage'])) return 'location';
            if (isset($msg['contactMessage']))  return 'contact';
        }

        $type = strtolower((string) ($payload['type'] ?? $payload['messageType'] ?? ''));
        if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'ptt'], true)) {
            return $type;
        }

        return null;
    }

    private function extractJobName(string $payload): string
    {
        $data = json_decode($payload, true);
        if (isset($data['displayName'])) {
            return class_basename($data['displayName']);
        }
        if (isset($data['job'])) {
            return class_basename($data['job']);
        }

        return '-';
    }

    // ── Log GenieACS ──────────────────────────────────────────────────────────

    public function genieacsIndex(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $client = $this->genieacsClient();
        $stats  = ['faults' => 0, 'tasks' => 0, 'devices' => 0];

        try {
            $stats['faults']  = count($client->getFaults(500));
            $stats['tasks']   = count($client->getTasks(500));
            $stats['devices'] = count($client->listDevices());
        } catch (\Throwable) {
            // GenieACS unreachable — show zeroes, view shows warning
        }

        return view('logs.genieacs', compact('stats'));
    }

    public function genieacsData(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $tab    = $request->input('tab', 'faults');
        $client = $this->genieacsClient();

        // Build genieacs_device_id → customer name lookup
        $deviceMap = CpeDevice::query()
            ->accessibleBy($user)
            ->with('pppUser:id,customer_name,username')
            ->get(['genieacs_device_id', 'ppp_user_id', 'serial_number', 'manufacturer', 'model'])
            ->keyBy('genieacs_device_id');

        try {
            if ($tab === 'tasks') {
                $rows = collect($client->getTasks(500))->map(function (array $t) use ($deviceMap) {
                    $devId   = $t['device'] ?? '-';
                    $cpe     = $deviceMap->get($devId);
                    $detail  = $t['objectName'] ?? ($t['parameterNames'] ? implode(', ', (array) $t['parameterNames']) : '-');
                    return [
                        'device_id'     => $devId,
                        'customer_name' => $cpe?->pppUser?->customer_name ?? $cpe?->pppUser?->username ?? '-',
                        'task_name'     => $t['name'] ?? '-',
                        'detail'        => $detail,
                        'timestamp'     => isset($t['timestamp']) ? \Carbon\Carbon::parse($t['timestamp'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') : '-',
                    ];
                })->values()->all();
            } elseif ($tab === 'devices') {
                $threshold = config('genieacs.online_threshold_minutes', 70);
                $rows = collect($client->listDevices())->map(function (array $d) use ($deviceMap, $threshold) {
                    $devId   = $d['_id'] ?? '-';
                    $cpe     = $deviceMap->get($devId);
                    $lastInform = $d['_lastInform'] ?? null;
                    $isOnline   = $lastInform && \Carbon\Carbon::parse($lastInform)->diffInMinutes(now()) <= $threshold;
                    $ms      = $d['InternetGatewayDevice']['DeviceInfo'] ?? $d['Device']['DeviceInfo'] ?? [];
                    $serial  = $cpe?->serial_number ?? ($ms['SerialNumber']['_value'] ?? null);
                    // oui_serial: format {OUI}-{Serial} seperti di label modem (tanpa model di tengah)
                    $parts     = explode('-', $devId, 3);
                    $ouiSerial = count($parts) === 3 ? $parts[0].'-'.$parts[2] : $devId;
                    return [
                        'device_id'     => $devId,
                        'oui_serial'    => $ouiSerial,
                        'customer_name' => $cpe?->pppUser?->customer_name ?? $cpe?->pppUser?->username ?? '-',
                        'serial_number' => $serial ?? '-',
                        'manufacturer'  => $cpe?->manufacturer ?? ($ms['Manufacturer']['_value'] ?? '-'),
                        'model'         => $cpe?->model ?? ($ms['ModelName']['_value'] ?? '-'),
                        'status'        => $isOnline ? 'online' : 'offline',
                        'last_inform'   => $lastInform ? \Carbon\Carbon::parse($lastInform)->setTimezone(config('app.timezone'))->diffForHumans() : '-',
                    ];
                })->values()->all();
            } else {
                // faults (default)
                $rows = collect($client->getFaults(500))->map(function (array $f) use ($deviceMap) {
                    $devId = $f['device'] ?? '-';
                    $cpe   = $deviceMap->get($devId);
                    return [
                        'device_id'     => $devId,
                        'customer_name' => $cpe?->pppUser?->customer_name ?? $cpe?->pppUser?->username ?? '-',
                        'code'          => $f['code'] ?? '-',
                        'message'       => $f['message'] ?? '-',
                        'retries'       => $f['retries'] ?? 0,
                        'timestamp'     => isset($f['timestamp']) ? \Carbon\Carbon::parse($f['timestamp'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') : '-',
                    ];
                })->values()->all();
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'GenieACS tidak dapat dihubungi: ' . $e->getMessage()], 503);
        }

        return response()->json(['data' => $rows]);
    }

    public function genieacsStatus(): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $client    = $this->genieacsClient();
        $status    = $client->getStatus();
        $threshold = config('genieacs.online_threshold_minutes', 70);

        if ($status['online']) {
            try {
                $devices = $client->listDevices();
                $online  = 0;
                foreach ($devices as $d) {
                    $lastInform = $d['_lastInform'] ?? null;
                    if ($lastInform && \Carbon\Carbon::parse($lastInform)->diffInMinutes(now()) <= $threshold) {
                        $online++;
                    }
                }
                $status['total_devices']  = count($devices);
                $status['online_devices'] = $online;
                $status['pending_tasks']  = count($client->getTasks(500));
            } catch (\Throwable) {
            }
        }

        return response()->json($status);
    }

    public function genieacsDeleteTask(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $client   = $this->genieacsClient();
        $taskId   = $request->input('task_id');
        $deviceId = $request->input('device_id');

        try {
            if ($taskId) {
                $client->deleteTask($taskId);

                return response()->json(['status' => 'ok', 'message' => 'Task berhasil dihapus.']);
            } elseif ($deviceId) {
                $count = $client->deleteDeviceTasks($deviceId);

                return response()->json(['status' => 'ok', 'message' => "{$count} task perangkat berhasil dihapus."]);
            } else {
                $tasks   = $client->getTasks(500);
                $deleted = 0;
                foreach ($tasks as $task) {
                    if (isset($task['_id']) && $client->deleteTask($task['_id'])) {
                        $deleted++;
                    }
                }

                return response()->json(['status' => 'ok', 'message' => "{$deleted} task berhasil dihapus semua."]);
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function genieacsDeleteDevice(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $deviceId = $request->input('device_id');
        if (! $deviceId) {
            return response()->json(['status' => 'error', 'message' => 'device_id diperlukan.'], 422);
        }

        $client = $this->genieacsClient();
        try {
            // 1. Hapus semua task pending milik device ini
            $client->deleteDeviceTasks($deviceId);

            // 2. Unlink dari cpe_devices (MariaDB)
            \App\Models\CpeDevice::where('genieacs_device_id', $deviceId)
                ->accessibleBy($user)
                ->delete();

            // 3. Hapus device dari MongoDB GenieACS
            $success = $client->deleteDevice($deviceId);
            if ($success) {
                return response()->json(['status' => 'ok', 'message' => 'Device berhasil dihapus dari GenieACS.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus device.'], 500);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function genieacsConnectionRequest(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'noc', 'it_support'])) {
            abort(403);
        }

        $deviceId = $request->input('device_id');
        if (! $deviceId) {
            return response()->json(['status' => 'error', 'message' => 'device_id diperlukan.'], 422);
        }

        $client  = $this->genieacsClient();
        $cpe     = \App\Models\CpeDevice::where('genieacs_device_id', $deviceId)->first();
        $profile = $cpe?->param_profile ?? 'igd';
        try {
            $success = $client->sendConnectionRequest($deviceId, $profile);
            if ($success) {
                return response()->json(['status' => 'ok', 'message' => 'Connection request terkirim. Modem akan segera inform.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Connection request gagal — modem mungkin tidak dapat dijangkau.'], 502);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function genieacsClient(): GenieAcsClient
    {
        $user     = auth()->user();
        $settings = $user->isSuperAdmin()
            ? TenantSettings::first()
            : TenantSettings::where('user_id', $user->effectiveOwnerId())->first();

        return $settings
            ? GenieAcsClient::fromTenantSettings($settings)
            : new GenieAcsClient();
    }
}
