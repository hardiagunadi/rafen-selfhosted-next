@extends('layouts.admin')

@section('title', 'Hotspot Tidak Aktif')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Session Hotspot Tidak Aktif</h3>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('sessions.hotspot') }}" class="btn btn-outline-success">Aktif</a>
                <a href="{{ route('sessions.hotspot-inactive') }}" class="btn btn-outline-danger active">Tidak Aktif</a>
            </div>
        </div>

        <div class="card-body">
            {{-- Stats --}}
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="info-box mb-0">
                        <span class="info-box-icon bg-danger"><i class="fas fa-broadcast-tower"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Hotspot Tidak Aktif</span>
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
                        <span class="info-box-icon bg-secondary"><i class="fas fa-info-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Keterangan</span>
                            <span class="info-box-number" style="font-size:0.85rem">Sesi yang sudah disconnect</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter --}}
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6">
                    <select id="filter-router" class="form-control form-control-sm">
                        <option value="">-- Semua Router --</option>
                        @foreach ($routers as $router)
                            <option value="{{ $router->id }}">{{ $router->name }} ({{ $router->host }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dt-hotspot-inactive" class="table table-hover table-sm align-middle mb-0 w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>Username</th>
                            <th>IP Terakhir</th>
                            <th>MAC Address</th>
                            <th>Server</th>
                            <th>Router</th>
                            <th>Terakhir Online</th>
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
        var dtUrl = '{{ route("sessions.hotspot-inactive.datatable") }}';
        var refreshAllUrl = '{{ route("sessions.refresh-all") }}';
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function init() {
            if (!document.getElementById('dt-hotspot-inactive')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }

            dtTable = $('#dt-hotspot-inactive').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: dtUrl,
                    data: function (d) { d.router_id = $('#filter-router').val(); },
                    dataSrc: function (json) {
                        var el = document.getElementById('total-count');
                        if (el) el.textContent = json.recordsTotal;
                        return json.data;
                    },
                },
                columns: [
                    { data: 'username',    render: function (v) { return '<strong>' + v + '</strong>'; } },
                    { data: 'ipv4',        render: function (v) { return v !== '-' ? '<code class="text-muted">' + v + '</code>' : '-'; } },
                    { data: 'caller_id',   render: function (v) { return v !== '-' ? '<code class="text-muted">' + v + '</code>' : '-'; } },
                    { data: 'server_name' },
                    { data: 'router',      render: function (v) { return '<span class="badge badge-secondary">' + v + '</span>'; } },
                    { data: 'updated_at',  render: function (v) { return '<small class="text-muted">' + v + '</small>'; } },
                ],
                language: { url: false, emptyTable: 'Tidak ada data Hotspot tidak aktif.', processing: 'Memuat...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_-_END_ dari _TOTAL_', paginate: { next: 'Berikutnya', previous: 'Sebelumnya' } },
                pageLength: 25,
                order: [[5, 'desc']],
            });

            $('#filter-router').off('change.hotspot-inactive').on('change.hotspot-inactive', function () {
                if (dtTable) dtTable.ajax.reload(null, false);
            });

            // Auto sync saat halaman dibuka agar data status terbaru.
            syncOnOpen();
        }

        function syncOnOpen() {
            fetch(refreshAllUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ service: 'hotspot' })
            })
                .then(function () {
                    if (dtTable) {
                        dtTable.ajax.reload(null, false);
                    }
                })
                .catch(function () {
                    // Silent fail: user tetap bisa manual buka ulang/filter tabel.
                });
        }

        document.addEventListener('DOMContentLoaded', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
