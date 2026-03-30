@extends('layouts.admin')

@section('title', 'Detail OLT HSGQ')

@section('content')
@php
    $currentUser = auth()->user();
    $canManageOlt = $currentUser->isSuperAdmin() || in_array($currentUser->role, ['administrator', 'noc'], true);
    $canPollOlt = $canManageOlt || $currentUser->role === 'teknisi';
    $showNocOnlyOidFields = $currentUser->role === 'noc';
    $isPollingInProgress = $oltConnection->isPollingInProgress();
    $pollingProgressPercent = $oltConnection->pollingProgressPercent();
    $pollingMessage = $oltConnection->pollingDisplayMessage();
    $quickRefreshSeconds = max(15, (int) config('olt.polling.live_refresh_seconds', 30));
    $fullRefreshSeconds = max($quickRefreshSeconds, (int) config('olt.polling.full_refresh_seconds', 300));
    $autoTriggerIntervalMs = $quickRefreshSeconds * 1000;
    $fullTriggerIntervalMs = $fullRefreshSeconds * 1000;

    if ($isPollingInProgress) {
        $pollingBadgeClass = 'badge-info';
        $pollingBadgeLabel = 'Sedang Polling';
    } elseif ($oltConnection->last_poll_success === null) {
        $pollingBadgeClass = 'badge-secondary';
        $pollingBadgeLabel = 'Belum Pernah Polling';
    } elseif ($oltConnection->last_poll_success) {
        $pollingBadgeClass = 'badge-success';
        $pollingBadgeLabel = 'Sukses';
    } else {
        $pollingBadgeClass = 'badge-danger';
        $pollingBadgeLabel = 'Gagal';
    }

    if ($isPollingInProgress) {
        $pollingAlertClass = 'alert-info';
    } elseif ($oltConnection->last_poll_success === true) {
        $pollingAlertClass = 'alert-success';
    } elseif ($oltConnection->last_poll_success === false) {
        $pollingAlertClass = 'alert-danger';
    } else {
        $pollingAlertClass = 'alert-secondary';
    }
@endphp
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="mb-0">{{ $oltConnection->name }}</h4>
            <small class="text-muted">Monitoring OLT Vendor {{ strtoupper($oltConnection->vendor) }}</small>
        </div>
        <div class="btn-group mt-2 mt-md-0">
            <a href="{{ route('olt-connections.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Daftar OLT
            </a>
            @if($canPollOlt)
                <form action="{{ route('olt-connections.poll', $oltConnection) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-sync-alt mr-1"></i>Polling Sekarang
                    </button>
                </form>
            @endif
            @if($canManageOlt)
                <a href="{{ route('olt-connections.edit', $oltConnection) }}" class="btn btn-sm btn-warning text-white">
                    <i class="fas fa-pen mr-1"></i>Edit
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-4">
                <div><strong>Host:</strong> {{ $oltConnection->host }}:{{ $oltConnection->snmp_port }}</div>
                <div><strong>Model:</strong> {{ $oltConnection->olt_model ?: '-' }}</div>
                <div><strong>SNMP:</strong> v{{ $oltConnection->snmp_version }} / timeout {{ $oltConnection->snmp_timeout }}s / retry {{ $oltConnection->snmp_retries }}</div>
                <div><strong>Status:</strong>
                    @if($oltConnection->is_active)
                        <span class="badge badge-success">Aktif</span>
                    @else
                        <span class="badge badge-secondary">Nonaktif</span>
                    @endif
                </div>
            </div>
            <div class="col-lg-4">
                <div><strong>Polling terakhir:</strong> <span id="polling-last-polled-at">{{ $oltConnection->last_polled_at?->format('Y-m-d H:i:s') ?? '-' }}</span></div>
                <div><strong>Hasil polling:</strong> <span id="polling-status-badge" class="badge {{ $pollingBadgeClass }}">{{ $pollingBadgeLabel }}</span></div>
                <div class="mt-2 {{ $isPollingInProgress && $pollingProgressPercent !== null ? '' : 'd-none' }}" id="polling-progress-wrapper">
                    <small class="text-muted d-block mb-1" id="polling-progress-label">Progres: {{ $pollingProgressPercent ?? 0 }}%</small>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" role="progressbar"
                            id="polling-progress-bar"
                            style="width: {{ $pollingProgressPercent ?? 0 }}%;"
                            aria-valuenow="{{ $pollingProgressPercent ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div><strong>Total ONU tersimpan:</strong> <span id="total-onu-stored">{{ number_format($totalOnuStored) }}</span></div>
            </div>
            @if($showNocOnlyOidFields)
                <div class="col-lg-4">
                    <div><strong>OID MAC / Identifier:</strong> <code>{{ $oltConnection->oid_serial ?: '-' }}</code></div>
                    <div><strong>OID Rx ONU:</strong> <code>{{ $oltConnection->oid_rx_onu ?: '-' }}</code></div>
                    <div><strong>OID Distance:</strong> <code>{{ $oltConnection->oid_distance ?: '-' }}</code></div>
                    <div><strong>OID Status:</strong> <code>{{ $oltConnection->oid_status ?: '-' }}</code></div>
                    <div><strong>OID Reboot ONU:</strong> <code>{{ $oltConnection->oid_reboot_onu ?: '-' }}</code></div>
                </div>
            @endif
        </div>
        <div id="polling-message-alert" class="alert {{ $pollingAlertClass }} mt-3 mb-0 {{ $pollingMessage ? '' : 'd-none' }}">
            {{ $pollingMessage ?? '' }}
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-2 mb-md-0">Data Redaman ONU</h3>
        <div class="d-flex flex-wrap align-items-center">
            <div class="input-group input-group-sm mr-2 mb-2 mb-md-0" style="min-width: 180px;">
                <select id="onu-port-filter" class="form-control">
                    <option value="">Semua Port ID</option>
                    @foreach($availablePortIds as $portId)
                        <option value="{{ $portId }}" @selected($selectedPortId === $portId)>{{ $portId }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group input-group-sm" style="min-width: 260px;">
                <input type="text" id="onu-search-input" value="{{ $search }}" class="form-control"
                    placeholder="Cari MAC/nama/ONU ID">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" id="onu-filter-reset">Reset</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex flex-wrap mb-2 mb-md-0">
                <button type="button" data-status-filter=""
                    class="btn btn-sm mr-2 mb-2 {{ $selectedStatus === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Semua Status
                </button>
                <button type="button" data-status-filter="online"
                    class="btn btn-sm mr-2 mb-2 {{ $selectedStatus === 'online' ? 'btn-success' : 'btn-outline-success' }}">
                    ONLINE
                </button>
                <button type="button" data-status-filter="offline"
                    class="btn btn-sm mb-2 {{ $selectedStatus === 'offline' ? 'btn-danger' : 'btn-outline-danger' }}">
                    OFFLINE
                </button>
            </div>
            <div class="small text-muted">
                Total ONU: <span id="summary-active">{{ number_format($activeSummary) }}</span> |
                Online: <span id="summary-online">{{ number_format($onlineSummary) }}</span> |
                Offline: <span id="summary-offline">{{ number_format($offlineSummary) }}</span>
            </div>
        </div>
        <div class="row mt-2" id="onu-port-summary-container">
            @foreach($summaryRows as $summaryRow)
                <div class="col-xl-3 col-lg-4 col-md-6 mb-2">
                    <div class="border rounded px-3 py-2 h-100 {{ $selectedPortId === $summaryRow['port_id'] ? 'border-primary bg-light' : '' }}"
                        data-port-summary="{{ $summaryRow['port_id'] }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>{{ $summaryRow['port_id'] }}</strong>
                            <button type="button" class="btn btn-link btn-sm p-0" data-port-summary-filter="{{ $summaryRow['port_id'] }}">
                                Filter
                            </button>
                        </div>
                        <div class="small text-muted mt-2">Total <span data-summary-total>{{ number_format($summaryRow['total']) }}</span></div>
                        <div class="small text-success">Online <span data-summary-online>{{ number_format($summaryRow['online']) }}</span></div>
                        <div class="small text-danger">Offline <span data-summary-offline>{{ number_format($summaryRow['offline']) }}</span></div>
                        <div class="small text-info">Tx OLT <span data-summary-tx-olt>{{ $summaryRow['tx_olt_dbm'] !== null ? number_format((float) $summaryRow['tx_olt_dbm'], 2).' dBm' : '-' }}</span></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="onu-optics-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>PON</th>
                        <th>ONU ID</th>
                        <th>MAC / ID</th>
                        <th>Nama ONU</th>
                        <th>Pelanggan (CPE)</th>
                        <th>Distance</th>
                        <th>Rx ONU</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        @if($canPollOlt)<th></th><th></th>@endif
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@if($canPollOlt)
<div class="modal fade" id="onu-history-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="onu-history-title">Riwayat Redaman ONU</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 pt-3 pb-2">
                    <small class="text-muted" id="onu-history-meta"></small>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Waktu Poll</th>
                            <th>RX ONU (dBm)</th>
                            <th>Jarak</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="onu-history-tbody">
                        <tr><td colspan="4" class="text-center text-muted">-</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="onu-alarm-modal" tabindex="-1" aria-labelledby="onu-alarm-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="onu-alarm-modal-label">Detail Alarm ONU</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div>
                        <div class="font-weight-bold" id="onu-alarm-subtitle">ONU -</div>
                        <div class="small text-muted" id="onu-alarm-meta">MAC/ID: -</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="onu-alarm-refresh-btn">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
                <div id="onu-alarm-loading" class="text-muted d-none">
                    <i class="fas fa-spinner fa-spin mr-1"></i>Mengambil data alarm dari OLT...
                </div>
                <div id="onu-alarm-error" class="alert alert-danger d-none mb-3"></div>
                <div id="onu-alarm-empty" class="alert alert-secondary d-none mb-0" data-default-message="Belum ada data alarm untuk ONU ini.">Belum ada data alarm untuk ONU ini.</div>
                <ul id="onu-alarm-list" class="list-unstyled small mb-0"></ul>
            </div>
        </div>
    </div>
</div>

@if($canPollOlt)
<div class="modal fade" id="onu-wifi-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-wifi mr-2"></i>Konfigurasi WiFi ONU</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="font-weight-bold mb-1" id="wifi-modal-onu-id">ONU -</div>
                <div class="small text-muted mb-3" id="wifi-modal-onu-name"></div>
                <div id="wifi-modal-loading" class="text-muted mb-3 d-none">
                    <i class="fas fa-spinner fa-spin mr-1"></i><span id="wifi-modal-loading-text">Memuat konfigurasi WiFi...</span>
                </div>
                <div id="wifi-modal-error" class="alert alert-danger d-none mb-3"></div>
                <div id="wifi-modal-success" class="alert alert-success d-none mb-3"></div>
                <div id="wifi-modal-form-area">
                    <div class="form-group">
                        <label class="font-weight-bold">SSID (Nama WiFi)</label>
                        <input type="text" id="wifi-ssid-input" class="form-control" maxlength="32" placeholder="Nama WiFi">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Password WiFi</label>
                        <div class="input-group">
                            <input type="password" id="wifi-password-input" class="form-control" maxlength="63" placeholder="Min. 8 karakter">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-toggle-wifi-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Minimal 8 karakter, maksimal 63 karakter.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn-wifi-save">
                    <i class="fas fa-save mr-1"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function () {
    function init() {
        var tableElement = document.getElementById('onu-optics-table');

        if (!tableElement || $.fn.DataTable.isDataTable('#onu-optics-table')) {
            return;
        }

        var portFilter = document.getElementById('onu-port-filter');
        var searchInput = document.getElementById('onu-search-input');
        var resetButton = document.getElementById('onu-filter-reset');
        var statusButtons = Array.from(document.querySelectorAll('[data-status-filter]'));
        var summaryContainer = document.getElementById('onu-port-summary-container');
        var searchTimer = null;
        var numberFormatter = new Intl.NumberFormat('id-ID');
        var pollingStatusUrl = '{{ route('olt-connections.polling-status', $oltConnection) }}';
        var pollingTriggerUrl = '{{ route('olt-connections.poll', $oltConnection) }}';
        var datatableUrl = '{{ route('olt-connections.datatable', $oltConnection) }}';
        var onuStatusUrl = '{{ route('olt-connections.onu-status', $oltConnection) }}';
        var onuAlarmsUrl = '{{ route('olt-connections.onu-alarms', $oltConnection) }}';
        var onuHistoryUrl = '{{ route('olt-connections.onu-history', $oltConnection) }}';
        var onuWifiConfigUrl = '{{ route('olt-connections.onu-wifi-config', $oltConnection) }}';
        var onuWifiUpdateUrl = '{{ route('olt-connections.onu-wifi-update', $oltConnection) }}';
        var cliEnabled = @json(in_array($oltConnection->cli_protocol ?? 'none', ['telnet', 'ssh']));
        var pollingWatcherTimer = null;
        var autoTriggerTimer = null;
        var alarmModalElement = document.getElementById('onu-alarm-modal');
        var alarmModal = alarmModalElement ? $(alarmModalElement) : null;
        var alarmSubtitle = document.getElementById('onu-alarm-subtitle');
        var alarmMeta = document.getElementById('onu-alarm-meta');
        var alarmList = document.getElementById('onu-alarm-list');
        var alarmLoading = document.getElementById('onu-alarm-loading');
        var alarmError = document.getElementById('onu-alarm-error');
        var alarmEmpty = document.getElementById('onu-alarm-empty');
        var alarmRefreshButton = document.getElementById('onu-alarm-refresh-btn');
        var autoTriggerIntervalMs = @json($autoTriggerIntervalMs);
        var fullTriggerIntervalMs = @json($fullTriggerIntervalMs);
        var autoPollingEnabled = @json($canPollOlt);
        var canFetchOnuAlarm = @json($canPollOlt);
        var lastFullPollTriggeredAt = 0;
        var pollState = {
            isPolling: @json($isPollingInProgress),
            completionHandled: false,
        };

        function formatInteger(value) {
            var number = parseInt(value, 10);

            if (Number.isNaN(number)) {
                return numberFormatter.format(0);
            }

            return numberFormatter.format(number);
        }

        function formatTxOlt(value) {
            var number = Number(value);

            if (!Number.isFinite(number)) {
                return '-';
            }

            return number.toFixed(2) + ' dBm';
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function activeStatus() {
            var activeButton = statusButtons.find(function (button) {
                return button.classList.contains('active');
            });

            return activeButton ? activeButton.getAttribute('data-status-filter') || '' : '';
        }

        function updateStatusButtons(selectedStatus) {
            statusButtons.forEach(function (button) {
                var status = button.getAttribute('data-status-filter') || '';
                var isActive = status === selectedStatus;

                button.classList.remove('btn-primary', 'btn-outline-primary', 'btn-success', 'btn-outline-success', 'btn-danger', 'btn-outline-danger', 'active');

                if (status === 'online') {
                    button.classList.add(isActive ? 'btn-success' : 'btn-outline-success');
                } else if (status === 'offline') {
                    button.classList.add(isActive ? 'btn-danger' : 'btn-outline-danger');
                } else {
                    button.classList.add(isActive ? 'btn-primary' : 'btn-outline-primary');
                }

                if (isActive) {
                    button.classList.add('active');
                }
            });
        }

        function updateSummaryCards(selectedPortId) {
            Array.from(document.querySelectorAll('[data-port-summary]')).forEach(function (card) {
                var portId = card.getAttribute('data-port-summary');
                var isActive = selectedPortId !== '' && portId === selectedPortId;

                card.classList.toggle('border-primary', isActive);
                card.classList.toggle('bg-light', isActive);
            });
        }

        function renderSummaryCards(summaryRows) {
            if (!summaryContainer) {
                return;
            }

            if (!Array.isArray(summaryRows) || summaryRows.length === 0) {
                summaryContainer.innerHTML = '';
                updateSummaryCards(portFilter.value);

                return;
            }

            summaryContainer.innerHTML = summaryRows.map(function (summaryRow) {
                var portId = summaryRow.port_id ? String(summaryRow.port_id) : '-';
                var total = formatInteger(summaryRow.total);
                var online = formatInteger(summaryRow.online);
                var offline = formatInteger(summaryRow.offline);
                var txOlt = formatTxOlt(summaryRow.tx_olt_dbm);
                var cardClass = 'border rounded px-3 py-2 h-100';

                if (portFilter.value !== '' && portFilter.value === portId) {
                    cardClass += ' border-primary bg-light';
                }

                return '<div class="col-xl-3 col-lg-4 col-md-6 mb-2">'
                    + '<div class="' + cardClass + '" data-port-summary="' + escapeHtml(portId) + '">'
                    + '<div class="d-flex justify-content-between align-items-center">'
                    + '<strong>' + escapeHtml(portId) + '</strong>'
                    + '<button type="button" class="btn btn-link btn-sm p-0" data-port-summary-filter="' + escapeHtml(portId) + '">Filter</button>'
                    + '</div>'
                    + '<div class="small text-muted mt-2">Total <span data-summary-total>' + total + '</span></div>'
                    + '<div class="small text-success">Online <span data-summary-online>' + online + '</span></div>'
                    + '<div class="small text-danger">Offline <span data-summary-offline>' + offline + '</span></div>'
                    + '<div class="small text-info">Tx OLT <span data-summary-tx-olt>' + txOlt + '</span></div>'
                    + '</div>'
                    + '</div>';
            }).join('');

            updateSummaryCards(portFilter.value);
        }

        function syncPortFilterOptions(summaryRows) {
            if (!portFilter || !Array.isArray(summaryRows)) {
                return;
            }

            var selectedPortId = portFilter.value;
            var optionRows = summaryRows.map(function (summaryRow) {
                var portId = summaryRow.port_id ? String(summaryRow.port_id) : '';

                if (portId === '') {
                    return '';
                }

                return '<option value="' + escapeHtml(portId) + '">' + escapeHtml(portId) + '</option>';
            }).join('');

            portFilter.innerHTML = '<option value="">Semua Port ID</option>' + optionRows;

            if (selectedPortId !== '') {
                portFilter.value = selectedPortId;
            }
        }

        function setTextContent(id, value) {
            var element = document.getElementById(id);

            if (!element) {
                return;
            }

            element.textContent = value;
        }

        function updatePollingPanel(snapshot) {
            var statusBadge = document.getElementById('polling-status-badge');
            var progressWrapper = document.getElementById('polling-progress-wrapper');
            var progressLabel = document.getElementById('polling-progress-label');
            var progressBar = document.getElementById('polling-progress-bar');
            var messageAlert = document.getElementById('polling-message-alert');
            var pollMessage = typeof snapshot.poll_message === 'string' ? snapshot.poll_message.trim() : '';
            var badgeClass = 'badge-secondary';
            var badgeLabel = 'Belum Pernah Polling';
            var alertClass = 'alert-secondary';
            var progress = parseInt(snapshot.poll_progress_percent, 10);
            var summary = snapshot.summary && typeof snapshot.summary === 'object' ? snapshot.summary : {};

            if (snapshot.is_polling) {
                badgeClass = 'badge-info';
                badgeLabel = 'Sedang Polling';
                alertClass = 'alert-info';
            } else if (snapshot.last_poll_success === true) {
                badgeClass = 'badge-success';
                badgeLabel = 'Sukses';
                alertClass = 'alert-success';
            } else if (snapshot.last_poll_success === false) {
                badgeClass = 'badge-danger';
                badgeLabel = 'Gagal';
                alertClass = 'alert-danger';
            }

            if (statusBadge) {
                statusBadge.classList.remove('badge-info', 'badge-secondary', 'badge-success', 'badge-danger');
                statusBadge.classList.add(badgeClass);
                statusBadge.textContent = badgeLabel;
            }

            setTextContent('polling-last-polled-at', snapshot.last_polled_at || '-');
            setTextContent('total-onu-stored', formatInteger(summary.total_onu_stored));
            setTextContent('summary-active', formatInteger(summary.active));
            setTextContent('summary-online', formatInteger(summary.online));
            setTextContent('summary-offline', formatInteger(summary.offline));

            if (progressWrapper && progressLabel && progressBar) {
                if (snapshot.is_polling && Number.isFinite(progress)) {
                    var normalizedProgress = Math.max(0, Math.min(100, progress));
                    progressWrapper.classList.remove('d-none');
                    progressLabel.textContent = 'Progres: ' + normalizedProgress + '%';
                    progressBar.style.width = normalizedProgress + '%';
                    progressBar.setAttribute('aria-valuenow', String(normalizedProgress));
                } else {
                    progressWrapper.classList.add('d-none');
                    progressLabel.textContent = 'Progres: 0%';
                    progressBar.style.width = '0%';
                    progressBar.setAttribute('aria-valuenow', '0');
                }
            }

            if (messageAlert) {
                messageAlert.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger', 'alert-secondary');

                if (pollMessage === '') {
                    messageAlert.classList.add('d-none');
                    messageAlert.textContent = '';
                } else {
                    messageAlert.classList.add(alertClass);
                    messageAlert.textContent = pollMessage;
                }
            }

            if (Array.isArray(summary.rows)) {
                syncPortFilterOptions(summary.rows);
                renderSummaryCards(summary.rows);
            }
        }

        function syncQueryString() {
            var url = new URL(window.location.href);
            var status = activeStatus();

            if (searchInput.value.trim() !== '') {
                url.searchParams.set('search', searchInput.value.trim());
            } else {
                url.searchParams.delete('search');
            }

            if (portFilter.value !== '') {
                url.searchParams.set('port_id', portFilter.value);
            } else {
                url.searchParams.delete('port_id');
            }

            if (status !== '') {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            window.history.replaceState({}, '', url);
        }

        var columns = [
            { data: 'pon_interface' },
            { data: 'onu_id', render: function (data) {
                return data === '-' ? '-' : '<code>' + data + '</code>';
            }},
            { data: 'serial_number', render: function (data, type, row) {
                if (data === '-') {
                    return '-';
                }

                if (!row || !row.onu_index || row.onu_index === '-' || !canFetchOnuAlarm) {
                    return '<code>' + data + '</code>';
                }

                return '<button type="button" class="btn btn-link btn-sm p-0"'
                    + ' data-onu-alarm="' + escapeHtml(row.onu_index) + '"'
                    + ' data-onu-id="' + escapeHtml(row.onu_id || '-') + '"'
                    + ' data-onu-serial="' + escapeHtml(data) + '"><code>' + escapeHtml(data) + '</code></button>';
            }},
            { data: 'onu_name' },
            { data: 'cpe_customer', orderable: false, searchable: false, render: function (data, type, row) {
                if (!data) return '<span class="text-muted">-</span>';
                var url = '/ppp-users/' + row.cpe_ppp_user_id + '/edit';
                return '<a href="' + url + '" class="small" title="' + escapeHtml(row.cpe_username || '') + '">'
                    + escapeHtml(data) + '</a>';
            }},
            { data: 'distance_m' },
            { data: 'rx_onu_dbm', render: function (data, type, row) {
                if (data === '-') {
                    return '-';
                }

                if (row.rx_onu_alert) {
                    return '<span class="text-danger font-weight-bold">' + data + '</span>';
                }

                return data;
            }},
            { data: 'status_badge', searchable: false },
            { data: 'last_seen_at' },
            @if($canPollOlt)
            { data: 'onu_index', orderable: false, searchable: false, render: function (data, type, row) {
                if (!data || data === '-') return '-';
                return '<button type="button" class="btn btn-xs btn-outline-info" data-onu-history="' + escapeHtml(data) + '" data-onu-id="' + escapeHtml(row.onu_id || '-') + '" data-onu-name="' + escapeHtml(row.onu_name || '-') + '" title="Riwayat Redaman"><i class="fas fa-chart-line"></i></button>';
            }},
            { data: 'onu_index', orderable: false, searchable: false, render: function (data, type, row) {
                if (!data || data === '-' || !cliEnabled) return '';
                return '<button type="button" class="btn btn-xs btn-outline-warning" data-onu-wifi="' + escapeHtml(data) + '" data-onu-id="' + escapeHtml(row.onu_id || '-') + '" data-onu-name="' + escapeHtml(row.onu_name || '-') + '" title="Konfigurasi WiFi ONU"><i class="fas fa-wifi"></i></button>';
            }}
            @endif
        ];

        function showToastMessage(message, type) {
            if (window.showToast) {
                window.showToast(message, type);
                return;
            }

            if (type === 'danger' || type === 'warning' || type === 'success') {
                window.alert(message);
            }
        }

        function setAlarmLoadingState(isLoading) {
            if (!alarmLoading || !alarmRefreshButton) {
                return;
            }

            alarmLoading.classList.toggle('d-none', !isLoading);
            alarmRefreshButton.disabled = isLoading;
        }

        function resetAlarmPanels() {
            if (alarmError) {
                alarmError.classList.add('d-none');
                alarmError.textContent = '';
            }

            if (alarmEmpty) {
                alarmEmpty.classList.add('d-none');
                alarmEmpty.textContent = alarmEmpty.getAttribute('data-default-message') || 'Belum ada data alarm untuk ONU ini.';
            }

            if (alarmList) {
                alarmList.innerHTML = '';
            }
        }

        function loadOnuAlarms(onuIndex) {
            if (!alarmModal || !alarmList || !onuIndex) {
                return Promise.resolve();
            }

            resetAlarmPanels();
            setAlarmLoadingState(true);

            var params = new URLSearchParams();
            params.set('onu_index', onuIndex);

            return fetch(onuAlarmsUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || ('Gagal mengambil data alarm ONU (HTTP ' + response.status + ').'));
                        }

                        return payload;
                    });
                })
                .then(function (payload) {
                    var data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
                    var entries = Array.isArray(data.entries) ? data.entries : [];
                    var notice = typeof data.notice === 'string' ? data.notice : '';

                    if (entries.length === 0) {
                        if (alarmEmpty) {
                            alarmEmpty.textContent = notice !== '' ? notice : (alarmEmpty.getAttribute('data-default-message') || 'Belum ada data alarm untuk ONU ini.');
                            alarmEmpty.classList.remove('d-none');
                        }

                        return;
                    }

                    alarmList.innerHTML = entries.map(function (entry) {
                        return '<li class="border-bottom py-1"><code class="text-dark">' + escapeHtml(entry) + '</code></li>';
                    }).join('');
                })
                .catch(function (error) {
                    if (alarmError) {
                        alarmError.classList.remove('d-none');
                        alarmError.textContent = error.message || 'Gagal membaca data alarm ONU.';
                    }
                })
                .finally(function () {
                    setAlarmLoadingState(false);
                });
        }

        @if($canPollOlt)
        function openOnuHistoryModal(onuIndex, onuId, onuName) {
            var modal = $('#onu-history-modal');
            if (!modal.length) return;
            document.getElementById('onu-history-title').textContent = 'Riwayat Redaman — ' + (onuId !== '-' ? onuId : onuIndex);
            document.getElementById('onu-history-meta').textContent = onuName !== '-' ? onuName : '';
            var tbody = document.getElementById('onu-history-tbody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memuat...</td></tr>';
            modal.modal('show');
            loadOnuHistory(onuIndex);
        }

        function loadOnuHistory(onuIndex) {
            var params = new URLSearchParams();
            params.set('onu_index', onuIndex);
            fetch(onuHistoryUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    var tbody = document.getElementById('onu-history-tbody');
                    var histories = json.data && json.data.histories ? json.data.histories : [];
                    if (histories.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat. Data akan tersedia setelah polling berikutnya.</td></tr>';
                        return;
                    }
                    var rows = '';
                    var prev = null;
                    histories.forEach(function (h) {
                        var rx = h.rx_onu_dbm !== null ? parseFloat(h.rx_onu_dbm).toFixed(2) : '-';
                        var delta = '';
                        if (prev !== null && h.rx_onu_dbm !== null && prev.rx_onu_dbm !== null) {
                            var diff = parseFloat(h.rx_onu_dbm) - parseFloat(prev.rx_onu_dbm);
                            if (Math.abs(diff) >= 0.5) {
                                delta = diff > 0
                                    ? ' <small class="text-success">▲' + diff.toFixed(1) + '</small>'
                                    : ' <small class="text-danger">▼' + Math.abs(diff).toFixed(1) + '</small>';
                            }
                        }
                        var rxClass = h.rx_onu_dbm !== null && parseFloat(h.rx_onu_dbm) < -27 ? 'text-danger font-weight-bold' : '';
                        var dist = h.distance_m !== null ? numberFormatter.format(h.distance_m) + ' m' : '-';
                        var statusBadge = h.status === '1' || h.status === 'online'
                            ? '<span class="badge badge-success">Online</span>'
                            : '<span class="badge badge-danger">Offline</span>';
                        rows += '<tr>'
                            + '<td class="text-monospace small">' + escapeHtml(h.polled_at || '-') + '</td>'
                            + '<td class="' + rxClass + '">' + rx + delta + '</td>'
                            + '<td>' + dist + '</td>'
                            + '<td>' + statusBadge + '</td>'
                            + '</tr>';
                        prev = h;
                    });
                    tbody.innerHTML = rows;
                })
                .catch(function () {
                    var tbody = document.getElementById('onu-history-tbody');
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Gagal memuat riwayat.</td></tr>';
                });
        }
        @endif

        function openOnuAlarmModal(onuIndex, onuId, serialNumber) {
            if (!alarmModal || !onuIndex) {
                return;
            }

            var normalizedOnuId = onuId && onuId !== '-' ? onuId : '-';
            var normalizedSerial = serialNumber && serialNumber !== '-' ? serialNumber : '-';

            if (alarmSubtitle) {
                alarmSubtitle.textContent = 'ONU ' + normalizedOnuId;
            }

            if (alarmMeta) {
                alarmMeta.textContent = 'MAC/ID: ' + normalizedSerial + ' | Index: ' + onuIndex;
            }

            if (alarmRefreshButton) {
                alarmRefreshButton.setAttribute('data-onu-index', onuIndex);
            }

            alarmModal.modal('show');
            loadOnuAlarms(onuIndex);
        }

        function fetchOnuStatusByIndex(onuIndex) {
            var params = new URLSearchParams();
            params.set('onu_index', onuIndex);

            return fetch(onuStatusUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.message || ('Gagal cek status ONU (HTTP ' + response.status + ').'));
                    }

                    return payload;
                });
            }).then(function (payload) {
                if (!payload || !payload.data || typeof payload.data !== 'object') {
                    throw new Error('Data ONU tidak ditemukan saat verifikasi status online.');
                }

                return payload.data;
            });
        }

        var table = $('#onu-optics-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            search: {
                search: searchInput.value || ''
            },
            ajax: {
                url: datatableUrl,
                data: function (data) {
                    data.port_id = portFilter.value;
                    data.status = activeStatus();
                }
            },
            columns: columns,
            pageLength: 50,
            searchDelay: 350,
            dom: "<'row align-items-center px-3 pt-2'<'col-sm-6'l><'col-sm-6 d-flex justify-content-end'p>>rt<'row align-items-center px-3 py-2'<'col-sm-6'i><'col-sm-6 d-flex justify-content-end'p>>",
            language: {
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(disaring dari _MAX_ total data)',
                zeroRecords: 'Tidak ada data yang cocok.',
                emptyTable: 'Belum ada data ONU. Jalankan polling untuk mengambil data redaman dari OLT.',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Selanjutnya',
                    previous: 'Sebelumnya'
                },
                processing: 'Memuat...'
            },
            order: [[0, 'asc'], [1, 'asc']]
        });

        function reloadTable(resetPaging) {
            syncQueryString();
            updateSummaryCards(portFilter.value);
            table.ajax.reload(null, resetPaging);
        }

        function fetchPollingStatus() {
            return fetch(pollingStatusUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Polling status unavailable.');
                    }

                    return response.json();
                })
                .then(function (snapshot) {
                    updatePollingPanel(snapshot);

                    var wasPolling = pollState.isPolling;
                    pollState.isPolling = Boolean(snapshot.is_polling);

                    if (!wasPolling && pollState.isPolling) {
                        pollState.completionHandled = false;
                    }

                    if (wasPolling && !pollState.isPolling && !pollState.completionHandled) {
                        pollState.completionHandled = true;
                        table.ajax.reload(null, false);

                        if (window.showToast) {
                            if (snapshot.last_poll_success === true) {
                                window.showToast('Polling SNMP selesai. Tabel ONU diperbarui otomatis.', 'success');
                            } else if (snapshot.last_poll_success === false) {
                                window.showToast(snapshot.poll_message || 'Polling SNMP gagal.', 'danger');
                            }
                        }

                        stopPollingWatcher();
                    }
                })
                .catch(function () {
                });
        }

        function startPollingWatcher() {
            if (pollingWatcherTimer !== null) {
                return;
            }

            pollingWatcherTimer = window.setInterval(fetchPollingStatus, 2500);
        }

        function stopPollingWatcher() {
            if (pollingWatcherTimer === null) {
                return;
            }

            window.clearInterval(pollingWatcherTimer);
            pollingWatcherTimer = null;
        }

        function csrfToken() {
            var element = document.querySelector('meta[name="csrf-token"]');

            return element ? (element.getAttribute('content') || '') : '';
        }

        function triggerPollingInBackground(mode) {
            var normalizedMode = mode === 'quick' ? 'quick' : 'full';

            if (!autoPollingEnabled || pollState.isPolling) {
                return;
            }

            fetch(pollingTriggerUrl + '?mode=' + encodeURIComponent(normalizedMode), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Trigger polling gagal.');
                    }

                    return response.json();
                })
                .then(function () {
                    pollState.isPolling = true;
                    pollState.completionHandled = false;
                    startPollingWatcher();
                    fetchPollingStatus();
                })
                .catch(function () {
                });
        }

        function startAutoTrigger() {
            if (!autoPollingEnabled || autoTriggerTimer !== null) {
                return;
            }

            autoTriggerTimer = window.setInterval(function () {
                if (document.hidden) {
                    return;
                }

                var now = Date.now();
                var mode = 'quick';

                if (now - lastFullPollTriggeredAt >= fullTriggerIntervalMs) {
                    mode = 'full';
                    lastFullPollTriggeredAt = now;
                }

                triggerPollingInBackground(mode);
            }, autoTriggerIntervalMs);
        }

        function stopAutoTrigger() {
            if (autoTriggerTimer === null) {
                return;
            }

            window.clearInterval(autoTriggerTimer);
            autoTriggerTimer = null;
        }

        portFilter.addEventListener('change', function () {
            reloadTable(true);
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                table.search(searchInput.value.trim()).draw();
                syncQueryString();
            }, 300);
        });

        statusButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                updateStatusButtons(button.getAttribute('data-status-filter') || '');
                reloadTable(true);
            });
        });

        if (summaryContainer) {
            summaryContainer.addEventListener('click', function (event) {
                var button = event.target.closest('[data-port-summary-filter]');

                if (!button) {
                    return;
                }

                portFilter.value = button.getAttribute('data-port-summary-filter') || '';
                reloadTable(true);
            });
        }

        resetButton.addEventListener('click', function () {
            searchInput.value = '';
            portFilter.value = '';
            updateStatusButtons('');
            table.search('');
            reloadTable(true);
        });

        tableElement.addEventListener('click', function (event) {
            var alarmButton = event.target.closest('[data-onu-alarm]');

            if (!alarmButton) {
                return;
            }

            var onuIndex = alarmButton.getAttribute('data-onu-alarm') || '';
            var onuId = alarmButton.getAttribute('data-onu-id') || '-';
            var serialNumber = alarmButton.getAttribute('data-onu-serial') || '-';

            if (onuIndex === '') {
                return;
            }

            openOnuAlarmModal(onuIndex, onuId, serialNumber);
        });

        @if($canPollOlt)
        tableElement.addEventListener('click', function (event) {
            var historyButton = event.target.closest('[data-onu-history]');
            if (!historyButton) return;
            var onuIndex = historyButton.getAttribute('data-onu-history') || '';
            var onuId = historyButton.getAttribute('data-onu-id') || '-';
            var onuName = historyButton.getAttribute('data-onu-name') || '-';
            if (onuIndex === '') return;
            openOnuHistoryModal(onuIndex, onuId, onuName);
        });

        // WiFi modal logic
        var wifiModalElement = document.getElementById('onu-wifi-modal');
        var wifiModal = wifiModalElement ? $(wifiModalElement) : null;
        var wifiCurrentOnuIndex = null;

        function setWifiLoading(isLoading, text) {
            var loadingEl = document.getElementById('wifi-modal-loading');
            var loadingText = document.getElementById('wifi-modal-loading-text');
            var formArea = document.getElementById('wifi-modal-form-area');
            var saveBtn = document.getElementById('btn-wifi-save');
            if (loadingEl) loadingEl.classList.toggle('d-none', !isLoading);
            if (loadingText && text) loadingText.textContent = text;
            if (formArea) formArea.classList.toggle('d-none', isLoading);
            if (saveBtn) saveBtn.disabled = isLoading;
        }

        function setWifiError(message) {
            var errorEl = document.getElementById('wifi-modal-error');
            var successEl = document.getElementById('wifi-modal-success');
            if (errorEl) { errorEl.textContent = message; errorEl.classList.toggle('d-none', !message); }
            if (successEl) { successEl.classList.add('d-none'); }
        }

        function setWifiSuccess(message) {
            var errorEl = document.getElementById('wifi-modal-error');
            var successEl = document.getElementById('wifi-modal-success');
            if (successEl) { successEl.textContent = message; successEl.classList.toggle('d-none', !message); }
            if (errorEl) { errorEl.classList.add('d-none'); }
        }

        function openWifiModal(onuIndex, onuId, onuName) {
            if (!wifiModal || !onuIndex) return;
            wifiCurrentOnuIndex = onuIndex;
            var idEl = document.getElementById('wifi-modal-onu-id');
            var nameEl = document.getElementById('wifi-modal-onu-name');
            var ssidInput = document.getElementById('wifi-ssid-input');
            var passInput = document.getElementById('wifi-password-input');
            if (idEl) idEl.textContent = 'ONU ' + onuId;
            if (nameEl) nameEl.textContent = onuName !== '-' ? onuName : '';
            if (ssidInput) ssidInput.value = '';
            if (passInput) passInput.value = '';
            setWifiError('');
            setWifiSuccess('');
            wifiModal.modal('show');

            setWifiLoading(true, 'Memuat konfigurasi WiFi dari OLT...');
            var params = new URLSearchParams();
            params.set('onu_index', onuIndex);
            fetch(onuWifiConfigUrl + '?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (res) {
                setWifiLoading(false);
                if (!res.ok) {
                    setWifiError(res.body.message || 'Gagal memuat konfigurasi WiFi ONU.');
                    return;
                }
                var data = res.body.data || {};
                if (ssidInput && data.ssid) ssidInput.value = data.ssid;
                if (passInput && data.password) passInput.value = data.password;
            })
            .catch(function () {
                setWifiLoading(false);
                setWifiError('Gagal menghubungi OLT via CLI. Pastikan konfigurasi Telnet/SSH benar.');
            });
        }

        tableElement.addEventListener('click', function (event) {
            var wifiButton = event.target.closest('[data-onu-wifi]');
            if (!wifiButton) return;
            var onuIndex = wifiButton.getAttribute('data-onu-wifi') || '';
            var onuId = wifiButton.getAttribute('data-onu-id') || '-';
            var onuName = wifiButton.getAttribute('data-onu-name') || '-';
            if (onuIndex === '') return;
            openWifiModal(onuIndex, onuId, onuName);
        });

        var btnWifiSave = document.getElementById('btn-wifi-save');
        if (btnWifiSave) {
            btnWifiSave.addEventListener('click', function () {
                var ssid = (document.getElementById('wifi-ssid-input') || {}).value || '';
                var password = (document.getElementById('wifi-password-input') || {}).value || '';
                if (!ssid.trim()) { setWifiError('SSID tidak boleh kosong.'); return; }
                if (password.length < 8) { setWifiError('Password WiFi minimal 8 karakter.'); return; }
                if (!wifiCurrentOnuIndex) return;

                setWifiLoading(true, 'Menyimpan konfigurasi WiFi ke OLT...');
                setWifiError('');

                fetch(onuWifiUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ onu_index: wifiCurrentOnuIndex, ssid: ssid, password: password }),
                })
                .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                .then(function (res) {
                    setWifiLoading(false);
                    if (!res.ok) {
                        setWifiError(res.body.message || 'Gagal menyimpan konfigurasi WiFi ONU.');
                    } else {
                        setWifiSuccess(res.body.message || 'Konfigurasi WiFi ONU berhasil diperbarui.');
                        if (window.AppAjax && window.AppAjax.showToast) {
                            window.AppAjax.showToast(res.body.message || 'Konfigurasi WiFi ONU berhasil diperbarui.', 'success');
                        }
                    }
                })
                .catch(function () {
                    setWifiLoading(false);
                    setWifiError('Gagal menyimpan konfigurasi WiFi ONU.');
                });
            });
        }

        var btnToggleWifiPwd = document.getElementById('btn-toggle-wifi-password');
        if (btnToggleWifiPwd) {
            btnToggleWifiPwd.addEventListener('click', function () {
                var pwdInput = document.getElementById('wifi-password-input');
                if (!pwdInput) return;
                var isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                this.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }
        @endif

        if (alarmRefreshButton) {
            alarmRefreshButton.addEventListener('click', function () {
                var onuIndex = alarmRefreshButton.getAttribute('data-onu-index') || '';

                if (onuIndex === '') {
                    return;
                }

                loadOnuAlarms(onuIndex);
            });
        }


        updateStatusButtons('{{ $selectedStatus }}');
        updateSummaryCards(portFilter.value);
        syncQueryString();

        var currentUrl = new URL(window.location.href);
        var shouldAutoPoll = currentUrl.searchParams.get('auto_poll') === '1';
        startAutoTrigger();

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopAutoTrigger();
                return;
            }

            startAutoTrigger();
            fetchPollingStatus();
        });

        if (alarmModal) {
            alarmModal.on('hidden.bs.modal', function () {
                if (alarmRefreshButton) {
                    alarmRefreshButton.removeAttribute('data-onu-index');
                }
                resetAlarmPanels();
                setAlarmLoadingState(false);
            });
        }

        window.addEventListener('beforeunload', function () {
            stopAutoTrigger();
            stopPollingWatcher();
        });

        if (shouldAutoPoll) {
            currentUrl.searchParams.delete('auto_poll');
            window.history.replaceState({}, '', currentUrl.toString());
            triggerPollingInBackground('quick');
        }

        if (pollState.isPolling) {
            fetchPollingStatus();
            startPollingWatcher();
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();
</script>
@endpush
