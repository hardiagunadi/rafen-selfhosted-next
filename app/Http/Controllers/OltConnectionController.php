<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetectOltModelRequest;
use App\Http\Requests\DetectOltOidRequest;
use App\Http\Requests\FetchOltOnuAlarmsRequest;
use App\Http\Requests\RebootOltOnuRequest;
use App\Http\Requests\StoreOltConnectionRequest;
use App\Http\Requests\UpdateOltConnectionRequest;
use App\Jobs\PollOltConnectionJob;
use App\Models\CpeDevice;
use App\Models\OltConnection;
use App\Models\OltOnuOptic;
use App\Services\HsgqCliClient;
use App\Services\HsgqSnmpCollector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class OltConnectionController extends Controller
{
    private const RX_ONU_SAFE_LIMIT_DBM = -27.0;

    public function __construct(
        private HsgqSnmpCollector $collector,
        private HsgqCliClient $cliClient,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        $connections = OltConnection::query()
            ->accessibleBy($user)
            ->withCount('onuOptics')
            ->latest()
            ->get();

        return view('olt_connections.index', compact('connections'));
    }

    public function create(): View
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        return view('olt_connections.create', [
            'hsgqModels' => HsgqSnmpCollector::availableModels(),
        ]);
    }

    public function store(StoreOltConnectionRequest $request): RedirectResponse
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        $data = $request->validated();
        $data['owner_id'] = auth()->user()->effectiveOwnerId();
        $data['is_active'] = $request->boolean('is_active', true);

        $oltConnection = OltConnection::query()->create($data);

        return redirect()
            ->route('olt-connections.show', $oltConnection)
            ->with('status', 'Koneksi OLT HSGQ berhasil ditambahkan.');
    }

    public function show(OltConnection $oltConnection, Request $request): View
    {
        $this->authorizeAccess($oltConnection);

        $search = trim((string) $request->input('search'));
        $selectedPortId = trim((string) $request->input('port_id'));
        $selectedStatus = $this->normalizeSelectedStatus((string) $request->input('status'));

        $summaryRows = $this->buildSummaryRows($oltConnection);

        $availablePortIds = $summaryRows
            ->pluck('port_id')
            ->values();

        $activeSummary = $summaryRows->sum('total');
        $onlineSummary = $summaryRows->sum('online');
        $offlineSummary = $summaryRows->sum('offline');
        $totalOnuStored = $oltConnection->onuOptics()->count();

        return view('olt_connections.show', compact(
            'oltConnection',
            'totalOnuStored',
            'search',
            'availablePortIds',
            'selectedPortId',
            'selectedStatus',
            'summaryRows',
            'activeSummary',
            'onlineSummary',
            'offlineSummary',
        ));
    }

    public function pollingStatus(OltConnection $oltConnection): JsonResponse
    {
        $this->authorizeAccess($oltConnection);

        $summaryRows = $this->buildSummaryRows($oltConnection);
        $totalOnuStored = $oltConnection->onuOptics()->count();

        return response()->json([
            'is_polling' => $oltConnection->isPollingInProgress(),
            'last_poll_success' => $oltConnection->last_poll_success,
            'poll_message' => $oltConnection->pollingDisplayMessage(),
            'poll_progress_percent' => $oltConnection->pollingProgressPercent(),
            'last_polled_at' => $oltConnection->last_polled_at?->format('Y-m-d H:i:s'),
            'summary' => [
                'total_onu_stored' => $totalOnuStored,
                'active' => $summaryRows->sum('total'),
                'online' => $summaryRows->sum('online'),
                'offline' => $summaryRows->sum('offline'),
                'rows' => $summaryRows->values()->all(),
            ],
        ]);
    }

    /**
     * @return Collection<int, array{
     *     port_id: string,
     *     total: int,
     *     online: int,
     *     offline: int,
     *     tx_olt_dbm: float|null
     * }>
     */
    private function buildSummaryRows(OltConnection $oltConnection): Collection
    {
        return $oltConnection->onuOptics()
            ->whereNotNull('pon_interface')
            ->get(['pon_interface', 'status', 'tx_olt_dbm'])
            ->groupBy('pon_interface')
            ->map(function ($items, string $portId): array {
                $total = $items->count();
                $online = $items->filter(fn (OltOnuOptic $item): bool => $this->isOnlineStatus($item->status))->count();
                $txOltDbm = $items->first(fn (OltOnuOptic $item): bool => $item->tx_olt_dbm !== null)?->tx_olt_dbm;

                return [
                    'port_id' => $portId,
                    'total' => $total,
                    'online' => $online,
                    'offline' => max(0, $total - $online),
                    'tx_olt_dbm' => $txOltDbm !== null ? (float) $txOltDbm : null,
                ];
            })
            ->sortBy(function (array $row): int {
                return (int) preg_replace('/\D+/', '', $row['port_id']);
            })
            ->values();
    }

    public function datatable(OltConnection $oltConnection, Request $request): JsonResponse
    {
        $this->authorizeAccess($oltConnection);

        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        $selectedPortId = trim((string) $request->input('port_id'));
        $selectedStatus = $this->normalizeSelectedStatus((string) $request->input('status'));

        $query = $this->buildOnuOpticsQuery($oltConnection, $search, $selectedPortId, $selectedStatus);
        $total = $oltConnection->onuOptics()->count();
        $filtered = (clone $query)->count();

        $this->applyDatatableOrdering($query, $request);

        $rows = $query
            ->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 50)))
            ->get();

        // Build MAC → CpeDevice lookup for OLT↔CPE linking
        // OLT serial_number format: "9C 6F 52 3B 13 33" → normalize to "9c6f523b1333"
        $oltMacs = $rows->map(fn ($o) => strtolower(str_replace(' ', '', $o->serial_number ?? '')))->filter()->unique()->values();
        $cpeByMac = CpeDevice::query()
            ->accessibleBy(auth()->user())
            ->with('pppUser:id,customer_name,username')
            ->whereNotNull('mac_address')
            ->get(['id', 'mac_address', 'ppp_user_id'])
            ->keyBy(fn ($c) => strtolower(str_replace(':', '', $c->mac_address)));

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(function (OltOnuOptic $onuOptic) use ($cpeByMac): array {
                $macNorm = strtolower(str_replace(' ', '', $onuOptic->serial_number ?? ''));
                $cpe     = $cpeByMac->get($macNorm);
                return [
                    'onu_index'       => $onuOptic->onu_index,
                    'pon_interface'   => $onuOptic->pon_interface ?? '-',
                    'onu_number'      => $onuOptic->onu_number ?? '-',
                    'onu_id'          => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                    'serial_number'   => $this->formatMacIdentifier($onuOptic->serial_number),
                    'onu_name'        => $onuOptic->onu_name ?? '-',
                    'distance_m'      => $onuOptic->distance_m !== null ? number_format((int) $onuOptic->distance_m).' m' : '-',
                    'rx_onu_dbm'      => $onuOptic->rx_onu_dbm !== null ? number_format((float) $onuOptic->rx_onu_dbm, 2).' dBm' : '-',
                    'rx_onu_alert'    => $onuOptic->rx_onu_dbm !== null
                        ? (float) $onuOptic->rx_onu_dbm < self::RX_ONU_SAFE_LIMIT_DBM
                        : false,
                    'status'          => $this->formatStatusLabel($onuOptic->status),
                    'status_badge'    => $this->formatStatusBadge($onuOptic->status),
                    'last_seen_at'    => $onuOptic->last_seen_at?->format('Y-m-d H:i:s') ?? '-',
                    'cpe_ppp_user_id' => $cpe?->ppp_user_id,
                    'cpe_customer'    => $cpe?->pppUser?->customer_name ?? null,
                    'cpe_username'    => $cpe?->pppUser?->username ?? null,
                ];
            }),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statusFilterValues(string $status): array
    {
        return match ($status) {
            'online' => ['1', 'online', 'ONLINE', 'up', 'UP', 'true', 'TRUE'],
            'offline' => ['2', 'offline', 'OFFLINE', 'down', 'DOWN', 'false', 'FALSE'],
            default => [],
        };
    }

    private function isOnlineStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array($status, $this->statusFilterValues('online'), true);
    }

    private function normalizeSelectedStatus(string $status): string
    {
        return in_array($status, ['', 'online', 'offline'], true) ? $status : '';
    }

    private function buildOnuOpticsQuery(
        OltConnection $oltConnection,
        string $search,
        string $selectedPortId,
        string $selectedStatus
    ): Builder {
        $query = $oltConnection->onuOptics()->getQuery();

        return $this->applyOnuOpticsFilters($query, $search, $selectedPortId, $selectedStatus);
    }

    private function applyOnuOpticsFilters(
        Builder $query,
        string $search,
        string $selectedPortId,
        string $selectedStatus
    ): Builder {
        return $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $childQuery) use ($search): void {
                    $childQuery->where('onu_index', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%')
                        ->orWhere('onu_name', 'like', '%'.$search.'%')
                        ->orWhere('pon_interface', 'like', '%'.$search.'%')
                        ->orWhere('onu_number', 'like', '%'.$search.'%');

                    if (preg_match('/^(?:PON)?(\d+)\/(\d+)$/i', $search, $matches) === 1) {
                        $childQuery->orWhere(function (Builder $onuIdQuery) use ($matches): void {
                            $onuIdQuery->where('pon_interface', 'PON'.(int) $matches[1])
                                ->where('onu_number', (string) (int) $matches[2]);
                        });
                    }
                });
            })
            ->when($selectedPortId !== '', function (Builder $builder) use ($selectedPortId): void {
                $builder->where('pon_interface', $selectedPortId);
            })
            ->when($selectedStatus !== '', function (Builder $builder) use ($selectedStatus): void {
                $builder->whereIn('status', $this->statusFilterValues($selectedStatus));
            });
    }

    private function applyDatatableOrdering(Builder $query, Request $request): void
    {
        $columnMap = [
            0 => ['pon_interface'],
            1 => ['pon_interface', 'onu_number'],
            2 => ['serial_number'],
            3 => ['onu_name'],
            4 => ['distance_m'],
            5 => ['rx_onu_dbm'],
            6 => ['status'],
            7 => ['last_seen_at'],
        ];

        $orders = $request->input('order', []);

        if (! is_array($orders) || $orders === []) {
            $query->orderBy('pon_interface')->orderBy('onu_number');

            return;
        }

        foreach (array_slice($orders, 0, 2) as $order) {
            $columnIndex = isset($order['column']) ? (int) $order['column'] : null;
            $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if ($columnIndex === null || ! array_key_exists($columnIndex, $columnMap)) {
                continue;
            }

            foreach ($columnMap[$columnIndex] as $column) {
                $query->orderBy($column, $direction);
            }
        }
    }

    private function formatOnuId(?string $ponInterface, ?string $onuNumber): string
    {
        if ($ponInterface === null || $onuNumber === null) {
            return '-';
        }

        return preg_replace('/^PON/i', '', $ponInterface).'/'.$onuNumber;
    }

    private function formatMacIdentifier(?string $identifier): string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return '-';
        }

        $normalized = preg_replace('/[^A-Fa-f0-9]/', '', $identifier);

        if ($normalized !== null && strlen($normalized) === 12) {
            return strtolower(implode(':', str_split($normalized, 2)));
        }

        return $identifier;
    }

    private function formatStatusLabel(?string $status): string
    {
        $statusValue = strtolower((string) $status);

        return match ($statusValue) {
            '1' => 'ONLINE',
            '2' => 'OFFLINE',
            default => $status ? strtoupper((string) $status) : '-',
        };
    }

    private function formatStatusBadge(?string $status): string
    {
        return '<span class="badge badge-'.$this->statusCssClass($status).'">'.$this->formatStatusLabel($status).'</span>';
    }

    private function statusCssClass(?string $status): string
    {
        $statusValue = strtolower((string) $status);

        if (in_array($statusValue, ['1', 'up', 'online'], true) || str_contains($statusValue, 'up') || str_contains($statusValue, 'online')) {
            return 'success';
        }

        if (in_array($statusValue, ['2', 'down', 'offline'], true) || str_contains($statusValue, 'down') || str_contains($statusValue, 'offline')) {
            return 'danger';
        }

        return 'secondary';
    }

    public function edit(OltConnection $oltConnection): View
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        return view('olt_connections.edit', [
            'oltConnection' => $oltConnection,
            'hsgqModels' => HsgqSnmpCollector::availableModels(),
        ]);
    }

    public function update(UpdateOltConnectionRequest $request, OltConnection $oltConnection): RedirectResponse
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $oltConnection->is_active);

        // Don't overwrite existing CLI password if the placeholder was submitted
        if (isset($data['cli_password']) && str_starts_with((string) $data['cli_password'], '•')) {
            unset($data['cli_password']);
        }

        // Normalize cli_protocol default
        if (! isset($data['cli_protocol']) || $data['cli_protocol'] === null) {
            $data['cli_protocol'] = 'none';
        }

        $oltConnection->update($data);

        return redirect()
            ->route('olt-connections.show', $oltConnection)
            ->with('status', 'Konfigurasi OLT HSGQ berhasil diperbarui.');
    }

    public function destroy(OltConnection $oltConnection): RedirectResponse
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);
        $oltConnection->delete();

        return redirect()
            ->route('olt-connections.index')
            ->with('status', 'Koneksi OLT HSGQ dihapus.');
    }

    public function poll(OltConnection $oltConnection, Request $request): JsonResponse|RedirectResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $mode = $this->normalizePollingMode((string) $request->input('mode'));

        if (app()->runningUnitTests()) {
            PollOltConnectionJob::dispatchSync($oltConnection->id, $mode);
            $latestConnection = $oltConnection->fresh();

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => $latestConnection?->last_poll_success === false ? 'error' : 'ok',
                    'message' => $latestConnection?->last_poll_success === false
                        ? 'Polling OLT gagal: '.($latestConnection->last_poll_message ?? 'Terjadi kesalahan SNMP.')
                        : ($mode === PollOltConnectionJob::MODE_QUICK ? 'Quick polling OLT HSGQ berhasil. ' : 'Polling OLT HSGQ berhasil. ')
                            .($latestConnection->last_poll_message ?? ''),
                    'is_polling' => (bool) $latestConnection?->isPollingInProgress(),
                    'last_poll_success' => $latestConnection?->last_poll_success,
                ], $latestConnection?->last_poll_success === false ? 422 : 200);
            }

            if ($latestConnection?->last_poll_success === false) {
                return redirect()
                    ->route('olt-connections.show', $oltConnection)
                    ->with('error', 'Polling OLT gagal: '.($latestConnection->last_poll_message ?? 'Terjadi kesalahan SNMP.'));
            }

            return redirect()
                ->route('olt-connections.show', $oltConnection)
                ->with('status', ($mode === PollOltConnectionJob::MODE_QUICK ? 'Quick polling OLT HSGQ berhasil. ' : 'Polling OLT HSGQ berhasil. ')
                    .($latestConnection->last_poll_message ?? ''));
        }

        $oltConnection->update([
            'last_poll_success' => null,
            'last_poll_message' => OltConnection::POLLING_RUNNING_PREFIX.' 0% Menunggu antrean polling...',
        ]);

        if ((string) config('queue.default') === 'sync') {
            PollOltConnectionJob::dispatchAfterResponse($oltConnection->id, $mode);
        } else {
            PollOltConnectionJob::dispatch($oltConnection->id, $mode);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => $mode === PollOltConnectionJob::MODE_QUICK
                    ? 'Quick polling OLT dijadwalkan di background.'
                    : 'Polling OLT dijadwalkan di background.',
                'is_polling' => true,
                'last_poll_success' => null,
            ]);
        }

        return redirect()
            ->route('olt-connections.show', $oltConnection)
            ->with('status', ($mode === PollOltConnectionJob::MODE_QUICK
                ? 'Quick polling OLT dijadwalkan di background.'
                : 'Polling OLT dijadwalkan di background.')
                .' Refresh halaman beberapa saat lagi untuk hasil terbaru.');
    }

    public function autoDetectOid(DetectOltOidRequest $request): JsonResponse
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        try {
            $detected = $this->collector->detectMappingFromModel($request->validated());

            return response()->json([
                'status' => 'ok',
                'message' => 'Mapping OID berhasil dideteksi dari model OLT.',
                'data' => $detected,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function autoDetectModel(DetectOltModelRequest $request): JsonResponse
    {
        if (! $this->canManageOltConnections()) {
            abort(403);
        }

        try {
            $detected = $this->collector->detectModelFromSnmp($request->validated());

            return response()->json([
                'status' => 'ok',
                'message' => $detected['matched_model'] !== null
                    ? 'Model OLT berhasil dideteksi dari SNMP.'
                    : 'SNMP terhubung, namun model belum ada pada profil. Isi model dan OID manual.',
                'data' => $detected,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function rebootOnu(OltConnection $oltConnection, RebootOltOnuRequest $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $onuIndex = (string) $request->validated('onu_index');
        $onuExists = $oltConnection->onuOptics()
            ->where('onu_index', $onuIndex)
            ->exists();

        if (! $onuExists) {
            return response()->json([
                'message' => 'ONU tidak ditemukan pada data OLT ini.',
            ], 404);
        }

        try {
            $this->collector->rebootOnu($oltConnection, $onuIndex);

            return response()->json([
                'status' => 'ok',
                'message' => 'Perintah restart ONU berhasil dikirim ke OLT.',
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function onuStatus(OltConnection $oltConnection, Request $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $validated = $request->validate([
            'onu_index' => ['required', 'regex:/^[0-9]+(?:\.[0-9]+)*$/'],
        ], [
            'onu_index.required' => 'ONU index wajib diisi.',
            'onu_index.regex' => 'Format ONU index tidak valid.',
        ]);

        $onuOptic = $oltConnection->onuOptics()
            ->where('onu_index', (string) $validated['onu_index'])
            ->first();

        if (! $onuOptic) {
            return response()->json([
                'message' => 'Data ONU tidak ditemukan pada OLT ini.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
                'onu_index' => (string) $onuOptic->onu_index,
                'onu_id' => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                'status' => $this->formatStatusLabel($onuOptic->status),
                'last_seen_at' => $onuOptic->last_seen_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function onuAlarms(OltConnection $oltConnection, FetchOltOnuAlarmsRequest $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $onuIndex = (string) $request->validated('onu_index');
        $onuOptic = $oltConnection->onuOptics()
            ->where('onu_index', $onuIndex)
            ->first();

        if (! $onuOptic) {
            return response()->json([
                'message' => 'Data ONU tidak ditemukan pada OLT ini.',
            ], 404);
        }

        try {
            $alarmData = $this->collector->fetchOnuAlarmLogs(
                $oltConnection,
                $onuIndex,
                $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                $onuOptic->serial_number,
            );

            return response()->json([
                'status' => 'ok',
                'data' => [
                    'onu_index' => (string) $onuOptic->onu_index,
                    'onu_id' => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                    'serial_number' => $this->formatMacIdentifier($onuOptic->serial_number),
                    'entries' => $alarmData['entries'],
                    'source_oids' => $alarmData['source_oids'],
                    'notice' => $alarmData['notice'],
                    'fetched_at' => now()->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function onuHistory(OltConnection $oltConnection, Request $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $validated = $request->validate([
            'onu_index' => ['required', 'regex:/^[0-9]+(?:\.[0-9]+)*$/'],
        ]);

        $onuOptic = $oltConnection->onuOptics()
            ->where('onu_index', (string) $validated['onu_index'])
            ->first();

        if (! $onuOptic) {
            return response()->json([
                'message' => 'Data ONU tidak ditemukan pada OLT ini.',
            ], 404);
        }

        // 96 entri = 24 jam × 4 (polling tiap 15 menit)
        $histories = $onuOptic->histories()->limit(8)->get([
            'polled_at', 'rx_onu_dbm', 'tx_onu_dbm', 'rx_olt_dbm', 'distance_m', 'status',
        ]);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'onu_index' => (string) $onuOptic->onu_index,
                'onu_id' => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                'onu_name' => $onuOptic->onu_name,
                'histories' => $histories,
            ],
        ]);
    }

    private function authorizeAccess(OltConnection $oltConnection): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        if ((int) $oltConnection->owner_id !== (int) $user->effectiveOwnerId()) {
            abort(403);
        }
    }

    private function canManageOltConnections(): bool
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc'], true);
    }

    private function canPollOltNow(): bool
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'teknisi'], true);
    }

    public function onuWifiConfig(OltConnection $oltConnection, Request $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        if (! $this->cliClient->isConfigured($oltConnection)) {
            return response()->json([
                'message' => 'Protokol CLI (Telnet/SSH) belum dikonfigurasi pada OLT ini. Isi protokol, port, username, dan password CLI di pengaturan OLT.',
            ], 422);
        }

        $validated = $request->validate([
            'onu_index' => ['required', 'regex:/^[0-9]+(?:\.[0-9]+)*$/'],
        ], [
            'onu_index.required' => 'ONU index wajib diisi.',
            'onu_index.regex' => 'Format ONU index tidak valid.',
        ]);

        $onuOptic = $oltConnection->onuOptics()
            ->where('onu_index', (string) $validated['onu_index'])
            ->first();

        if (! $onuOptic) {
            return response()->json([
                'message' => 'Data ONU tidak ditemukan pada OLT ini.',
            ], 404);
        }

        try {
            $wifiConfig = $this->cliClient->getOnuWifiConfig(
                $oltConnection,
                (string) $onuOptic->pon_interface,
                (string) $onuOptic->onu_number,
            );

            return response()->json([
                'status' => 'ok',
                'data' => array_merge($wifiConfig, [
                    'onu_index' => (string) $onuOptic->onu_index,
                    'onu_id' => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                    'onu_name' => $onuOptic->onu_name,
                ]),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function onuWifiUpdate(OltConnection $oltConnection, Request $request): JsonResponse
    {
        if (! $this->canPollOltNow()) {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        if (! $this->cliClient->isConfigured($oltConnection)) {
            return response()->json([
                'message' => 'Protokol CLI (Telnet/SSH) belum dikonfigurasi pada OLT ini.',
            ], 422);
        }

        $validated = $request->validate([
            'onu_index' => ['required', 'regex:/^[0-9]+(?:\.[0-9]+)*$/'],
            'ssid' => ['required', 'string', 'min:1', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'max:63'],
        ], [
            'onu_index.required' => 'ONU index wajib diisi.',
            'ssid.required' => 'SSID wajib diisi.',
            'ssid.max' => 'SSID maksimal 32 karakter.',
            'password.required' => 'Password WiFi wajib diisi.',
            'password.min' => 'Password WiFi minimal 8 karakter.',
            'password.max' => 'Password WiFi maksimal 63 karakter.',
        ]);

        $onuOptic = $oltConnection->onuOptics()
            ->where('onu_index', (string) $validated['onu_index'])
            ->first();

        if (! $onuOptic) {
            return response()->json([
                'message' => 'Data ONU tidak ditemukan pada OLT ini.',
            ], 404);
        }

        try {
            $this->cliClient->setOnuWifi(
                $oltConnection,
                (string) $onuOptic->pon_interface,
                (string) $onuOptic->onu_number,
                (string) $validated['ssid'],
                (string) $validated['password'],
            );

            return response()->json([
                'status' => 'ok',
                'message' => 'Konfigurasi WiFi ONU '.$this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number).' berhasil diperbarui.',
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function normalizePollingMode(string $mode): string
    {
        return in_array($mode, [PollOltConnectionJob::MODE_FULL, PollOltConnectionJob::MODE_QUICK], true)
            ? $mode
            : PollOltConnectionJob::MODE_FULL;
    }
}
