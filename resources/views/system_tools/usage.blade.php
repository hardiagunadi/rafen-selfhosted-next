@extends('layouts.admin')

@section('title', 'Cek Pemakaian')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Cek Pemakaian (RADIUS Accounting)</h4>
        <div class="d-flex gap-2">
            <select id="type-filter" class="form-control form-control-sm" style="width:140px;">
                <option value="ppp">PPP Users</option>
                <option value="hotspot">Hotspot Users</option>
            </select>
            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Cari username / nama..." style="width:220px;">
            <button class="btn btn-primary btn-sm" id="btn-cari"><i class="fas fa-search mr-1"></i>Cari</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="usage-table">
                <thead class="thead-light">
                    <tr>
                        <th>Username</th>
                        <th>Nama Pelanggan</th>
                        <th>IP Address</th>
                        <th>Upload</th>
                        <th>Download</th>
                        <th>Durasi Sesi</th>
                        <th>Terakhir Aktif</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="usage-tbody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Klik tombol Cari untuk memuat data.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var loading = false;

    function load() {
        if (loading) return;
        loading = true;
        var tbody = document.getElementById('usage-tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat data...</td></tr>';

        fetch('{{ route("tools.usage.data") }}?type=' + encodeURIComponent(document.getElementById('type-filter').value)
            + '&search=' + encodeURIComponent(document.getElementById('search-input').value), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            loading = false;
            if (!res.data || res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.map(function (r) {
                var statusBadge = r.online
                    ? '<span class="badge badge-success">ONLINE</span>'
                    : '<span class="badge badge-secondary">OFFLINE</span>';
                return '<tr>'
                    + '<td><code>' + (r.username || '-') + '</code></td>'
                    + '<td>' + r.customer_name + '</td>'
                    + '<td><code>' + r.ip_address + '</code></td>'
                    + '<td class="text-info font-weight-bold">' + r.upload + '</td>'
                    + '<td class="text-success font-weight-bold">' + r.download + '</td>'
                    + '<td>' + r.session_time + '</td>'
                    + '<td class="text-muted small">' + (r.last_seen || '-') + '</td>'
                    + '<td>' + statusBadge + '</td>'
                    + '</tr>';
            }).join('');
        })
        .catch(function () {
            loading = false;
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
        });
    }

    document.getElementById('btn-cari').addEventListener('click', load);
    document.getElementById('search-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') load();
    });
})();
</script>
@endsection
