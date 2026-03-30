@extends('layouts.admin')

@section('title', 'Session Hotspot')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Session Hotspot Aktif</h3>
            <div class="d-flex align-items-center" style="gap:.5rem;">
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('sessions.hotspot') }}" class="btn btn-outline-success active">Aktif</a>
                    <a href="{{ route('sessions.hotspot-inactive') }}" class="btn btn-outline-danger">Tidak Aktif</a>
                </div>
                <button type="button" id="btn-refresh-all" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt" id="refresh-icon"></i> Refresh
                </button>
            </div>
        </div>

        <div class="card-body">
            {{-- Stats --}}
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="info-box mb-0">
                        <span class="info-box-icon bg-success"><i class="fas fa-broadcast-tower"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Hotspot Online</span>
                            <span class="info-box-number" id="total-count">{{ $total }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="info-box mb-0">
                        <span class="info-box-icon bg-info"><i class="fas fa-server"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Router</span>
                            <span class="info-box-number">{{ $routers->count() }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="info-box mb-0">
                        <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Auto-Refresh</span>
                            <span class="info-box-number" id="refresh-countdown">60s</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alert area --}}
            <div id="alert-area"></div>

            {{-- Filter & per-router refresh --}}
            <div class="row mb-3 align-items-center">
                <div class="col-md-4 col-sm-6">
                    <select id="filter-router" class="form-control form-control-sm">
                        <option value="">-- Semua Router --</option>
                        @foreach ($routers as $router)
                            <option value="{{ $router->id }}"
                                    data-refresh-url="{{ route('sessions.refresh-router', $router->id) }}">
                                {{ $router->name }} ({{ $router->host }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" id="btn-refresh-router" class="btn btn-sm btn-outline-primary" style="display:none">
                        <i class="fas fa-sync-alt"></i> Sync Router Ini
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dt-hotspot" class="table table-hover table-sm align-middle mb-0 w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>Username</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Uptime</th>
                            <th>Upload</th>
                            <th>Download</th>
                            <th>Server</th>
                            <th>Router</th>
                            <th>Diperbarui</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var dtTable = null;
        var countdownTimer = null;
        var countdown = 60;
        var dtUrl         = '{{ route("sessions.hotspot.datatable") }}';
        var refreshAllUrl = '{{ route("sessions.refresh-all") }}';
        var csrfToken     = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function showAlert(type, msg) {
            var area = document.getElementById('alert-area');
            area.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show py-2">' +
                msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
            if (type === 'success') setTimeout(function () {
                var a = area.querySelector('.alert'); if (a) a.remove();
            }, 4000);
        }

        function updateTotal(n) {
            var el = document.getElementById('total-count');
            if (el && n !== undefined) el.textContent = n;
        }

        function startCountdown() {
            if (countdownTimer) clearInterval(countdownTimer);
            countdown = 60;
            countdownTimer = setInterval(function () {
                countdown--;
                var el = document.getElementById('refresh-countdown');
                if (el) el.textContent = countdown + 's';
                if (countdown <= 0) {
                    countdown = 60;
                    if (dtTable) dtTable.ajax.reload(function (json) {
                        updateTotal(json.recordsTotal);
                    }, false);
                }
            }, 1000);
        }

        function init() {
            if (!document.getElementById('dt-hotspot')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }
            if (countdownTimer) clearInterval(countdownTimer);

            dtTable = $('#dt-hotspot').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: dtUrl,
                    data: function (d) { d.router_id = $('#filter-router').val(); },
                    dataSrc: function (json) {
                        updateTotal(json.recordsTotal);
                        return json.data;
                    },
                },
                columns: [
                    { data: 'username',    render: function (v) { return '<strong>' + v + '</strong>'; } },
                    { data: 'ipv4',        render: function (v) { return '<code>' + v + '</code>'; } },
                    { data: 'caller_id',   render: function (v) { return v !== '-' ? '<code class="text-muted">' + v + '</code>' : '-'; } },
                    { data: 'uptime',      render: function (v) { return v !== '-' ? '<span class="badge badge-success">' + v + '</span>' : '-'; } },
                    { data: 'bytes_in' },
                    { data: 'bytes_out' },
                    { data: 'server_name' },
                    { data: 'router',      render: function (v) { return '<span class="badge badge-info">' + v + '</span>'; } },
                    { data: 'updated_at',  render: function (v) { return '<small class="text-muted">' + v + '</small>'; } },
                ],
                language: { url: false, emptyTable: 'Belum ada sesi Hotspot aktif.', processing: 'Memuat...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_-_END_ dari _TOTAL_', paginate: { next: 'Berikutnya', previous: 'Sebelumnya' } },
                pageLength: 25,
            });

            $('#filter-router').off('change.hotspot').on('change.hotspot', function () {
                var btn = document.getElementById('btn-refresh-router');
                btn.style.display = this.value ? 'inline-block' : 'none';
                if (dtTable) dtTable.ajax.reload(function (json) { updateTotal(json.recordsTotal); }, false);
            });

            startCountdown();

            // Auto sync saat halaman dibuka agar data terbaru tanpa klik manual.
            syncAllSessions({
                withLoading: false,
                showSuccess: false,
                showError: false
            });
        }

        function syncAllSessions(options) {
            var opts = options || {};
            var withLoading = opts.withLoading === true;
            var showSuccess = opts.showSuccess === true;
            var showError = opts.showError !== false;
            var btn = document.getElementById('btn-refresh-all');
            var icon = document.getElementById('refresh-icon');

            if (withLoading && btn && icon) {
                btn.disabled = true;
                icon.classList.add('fa-spin');
            }

            return fetch(refreshAllUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ service: 'hotspot' })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (dtTable) dtTable.ajax.reload(function (json) { updateTotal(json.recordsTotal); }, false);
                    countdown = 60;
                    if (showSuccess) {
                        var msg = 'Hotspot: <strong>' + data.hotspot_online + '</strong> sesi aktif';
                        if (data.errors && data.errors.length) msg += ' &mdash; <span class="text-warning">' + data.errors.join(', ') + '</span>';
                        showAlert('success', msg);
                    }
                })
                .catch(function () {
                    if (showError) {
                        showAlert('danger', 'Gagal menghubungi server.');
                    }
                })
                .finally(function () {
                    if (withLoading && btn && icon) {
                        btn.disabled = false;
                        icon.classList.remove('fa-spin');
                    }
                });
        }

        // Refresh semua router (manual)
        document.getElementById('btn-refresh-all').addEventListener('click', function () {
            syncAllSessions({
                withLoading: true,
                showSuccess: true,
                showError: true
            });
        });

        // Refresh per-router
        document.getElementById('btn-refresh-router').addEventListener('click', function () {
            var btn = this;
            var sel = document.getElementById('filter-router');
            var url = sel.options[sel.selectedIndex]?.dataset.refreshUrl;
            if (!url) return;
            btn.disabled = true;
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json' }, body: JSON.stringify({ service: 'hotspot' }) })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (dtTable) dtTable.ajax.reload(function (json) { updateTotal(json.recordsTotal); }, false);
                    countdown = 60;
                    showAlert(data.success ? 'success' : 'warning', data.message);
                })
                .catch(function () { showAlert('danger', 'Gagal menghubungi router.'); })
                .finally(function () { btn.disabled = false; });
        });

        document.addEventListener('DOMContentLoaded', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
