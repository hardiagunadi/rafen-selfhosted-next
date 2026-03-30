@extends('layouts.admin')

@section('title', 'Impor User')

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Impor User dari CSV</h4>
            </div>
            <div class="card-body">
                <form id="import-form" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Tipe User</label>
                        <select name="type" id="import-type" class="form-control">
                            <option value="ppp">PPP Users</option>
                            <option value="hotspot">Hotspot Users</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>File CSV</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="csv-file" name="file" accept=".csv,.txt" required>
                            <label class="custom-file-label" for="csv-file">Pilih file CSV...</label>
                        </div>
                        <small class="text-muted">Maksimal 5 MB. Format: UTF-8 tanpa BOM.</small>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-preview">
                        <i class="fas fa-search mr-1"></i>Preview & Cek Data
                    </button>
                </form>

                <div id="import-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>

        {{-- Panel konflik --}}
        <div id="conflict-panel" class="card mt-3" style="display:none;">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Data Konflik — Sudah Ada di Database</h5>
                <div>
                    <button class="btn btn-sm btn-outline-dark mr-1" id="btn-check-all">Pilih Semua</button>
                    <button class="btn btn-sm btn-outline-dark" id="btn-uncheck-all">Batal Semua</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width:40px;">Update</th>
                                <th>Username</th>
                                <th>Field</th>
                                <th>Data Lama</th>
                                <th>Data Baru (CSV)</th>
                            </tr>
                        </thead>
                        <tbody id="conflict-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted" id="conflict-count-label"></small>
                <button class="btn btn-warning" id="btn-confirm">
                    <i class="fas fa-check mr-1"></i>Konfirmasi Import
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Template CSV</h5></div>
            <div class="card-body">
                <p class="text-muted small">Kolom <strong>username</strong> dan <strong>customer_name</strong> wajib diisi.</p>

                <div id="template-ppp">
                    <strong>PPP Users (Format Rafen):</strong>
                    <pre class="bg-light p-2 small mt-1">customer_id,customer_name,nik,nomor_hp,email,alamat,username,ppp_password,status_akun,status_bayar,jatuh_tempo,tipe_service,catatan</pre>
                    <a href="{{ route('tools.import.template', 'ppp') }}" class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="fas fa-download mr-1"></i>Download Template PPP
                    </a>
                    <hr>
                    <strong>PPP Users (Format MixRadius):</strong>
                    <p class="text-muted small mb-0">Upload langsung CSV export MixRadius. Kolom yang digunakan: <code>Login, Password, FullName, CustomerId, Email, IdCard, Phone, Address, ExpiredAction, Plan, PaymentStatus, AuthStatus, Expired, Note</code></p>
                </div>

                <div id="template-hotspot" style="display:none;">
                    <strong>Hotspot Users:</strong>
                    <pre class="bg-light p-2 small mt-1">customer_id,customer_name,nik,nomor_hp,email,alamat,username,hotspot_password,status_akun,status_bayar,jatuh_tempo,catatan</pre>
                    <a href="{{ route('tools.import.template', 'hotspot') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download mr-1"></i>Download Template Hotspot
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('import-type').addEventListener('change', function () {
        document.getElementById('template-ppp').style.display = this.value === 'ppp' ? '' : 'none';
        document.getElementById('template-hotspot').style.display = this.value === 'hotspot' ? '' : 'none';
    });

    document.getElementById('csv-file').addEventListener('change', function () {
        this.nextElementSibling.textContent = this.files[0] ? this.files[0].name : 'Pilih file CSV...';
    });

    var previewData = null;

    var fieldLabels = {
        customer_name: 'Nama Pelanggan', ppp_password: 'Password PPP',
        hotspot_password: 'Password Hotspot', customer_id: 'Customer ID',
        nik: 'NIK', nomor_hp: 'No. HP', email: 'Email', alamat: 'Alamat',
        status_akun: 'Status Akun', status_bayar: 'Status Bayar',
        jatuh_tempo: 'Jatuh Tempo', catatan: 'Catatan',
    };

    // ── Step 1: Preview ───────────────────────────────────────────────────────
    document.getElementById('import-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btn-preview');
        var result = document.getElementById('import-result');
        document.getElementById('conflict-panel').style.display = 'none';
        document.getElementById('conflict-tbody').innerHTML = '';
        previewData = null;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memuat...';
        result.style.display = 'none';

        var fd = new FormData(this);
        fetch('{{ route("tools.import.preview") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd,
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search mr-1"></i>Preview & Cek Data';
            result.style.display = '';

            if (!res.ok || res.data.error) {
                result.innerHTML = '<div class="alert alert-danger">' + (res.data.error || res.data.message || 'Terjadi kesalahan.') + '</div>';
                return;
            }

            previewData = res.data;
            renderPreviewSummary(res.data);
            if (res.data.conflicts && res.data.conflicts.length > 0) {
                renderConflicts(res.data.conflicts);
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search mr-1"></i>Preview & Cek Data';
            result.style.display = '';
            result.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan koneksi.</div>';
        });
    });

    function renderPreviewSummary(data) {
        var newCount       = (data.new || []).length;
        var conflictCount  = (data.conflicts || []).length;
        var identicalCount = data.identical || 0;
        var errCount       = (data.parse_errors || []).length;

        var html = '<div class="alert alert-info mb-2"><strong>Hasil Analisis CSV:</strong><ul class="mb-0 mt-1">';
        html += '<li><span class="badge badge-success">' + newCount + '</span> user baru (akan langsung diimport)</li>';
        html += '<li><span class="badge badge-warning">' + conflictCount + '</span> user sudah ada dengan data berbeda</li>';
        html += '<li><span class="badge badge-secondary">' + identicalCount + '</span> user sudah ada, data sama (dilewati)</li>';
        if (errCount > 0) html += '<li><span class="badge badge-danger">' + errCount + '</span> baris gagal diparse</li>';
        html += '</ul></div>';

        if (errCount > 0) {
            html += '<div class="alert alert-warning"><strong>Baris gagal:</strong><ul class="mb-0 mt-1">';
            data.parse_errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
            html += '</ul></div>';
        }

        if (newCount === 0 && conflictCount === 0) {
            html += '<div class="alert alert-secondary">Tidak ada data baru untuk diimport.</div>';
        } else if (conflictCount === 0) {
            // Tidak ada konflik — tombol langsung confirm
            html += '<button class="btn btn-success" id="btn-confirm-direct"><i class="fas fa-upload mr-1"></i>Import ' + newCount + ' User Baru</button>';
        } else {
            html += '<div class="alert alert-warning mt-2 mb-0">Terdapat ' + conflictCount + ' konflik. Pilih baris yang ingin diperbarui di bawah, lalu klik <strong>Konfirmasi Import</strong>.</div>';
        }

        document.getElementById('import-result').innerHTML = html;

        var directBtn = document.getElementById('btn-confirm-direct');
        if (directBtn) {
            directBtn.addEventListener('click', function () { doConfirm([]); });
        }
    }

    function renderConflicts(conflicts) {
        var tbody = document.getElementById('conflict-tbody');
        tbody.innerHTML = '';

        conflicts.forEach(function (c, idx) {
            var fields  = Object.keys(c.diff);
            var rowspan = fields.length;

            fields.forEach(function (field, fi) {
                var tr = document.createElement('tr');

                if (fi === 0) {
                    var tdCheck = document.createElement('td');
                    tdCheck.className = 'text-center align-middle';
                    tdCheck.rowSpan = rowspan;
                    var cb = document.createElement('input');
                    cb.type = 'checkbox'; cb.className = 'conflict-cb'; cb.dataset.idx = idx;
                    tdCheck.appendChild(cb);
                    tr.appendChild(tdCheck);

                    var tdUser = document.createElement('td');
                    tdUser.rowSpan = rowspan;
                    tdUser.className = 'align-middle font-weight-bold small';
                    tdUser.textContent = c.username;
                    tr.appendChild(tdUser);
                }

                var tdField = document.createElement('td');
                tdField.className = 'small'; tdField.textContent = fieldLabels[field] || field;
                tr.appendChild(tdField);

                var tdOld = document.createElement('td');
                tdOld.className = 'small text-muted'; tdOld.textContent = c.diff[field].existing || '-';
                tr.appendChild(tdOld);

                var tdNew = document.createElement('td');
                tdNew.className = 'small text-success font-weight-bold'; tdNew.textContent = c.diff[field].incoming || '-';
                tr.appendChild(tdNew);

                tbody.appendChild(tr);
            });
        });

        document.getElementById('conflict-count-label').textContent =
            conflicts.length + ' user konflik. Centang yang ingin diperbarui.';
        document.getElementById('conflict-panel').style.display = '';
    }

    document.getElementById('btn-check-all').addEventListener('click', function () {
        document.querySelectorAll('.conflict-cb').forEach(function (cb) { cb.checked = true; });
    });
    document.getElementById('btn-uncheck-all').addEventListener('click', function () {
        document.querySelectorAll('.conflict-cb').forEach(function (cb) { cb.checked = false; });
    });

    // ── Step 2: Confirm ───────────────────────────────────────────────────────
    document.getElementById('btn-confirm').addEventListener('click', function () {
        var selectedUpdates = [];
        document.querySelectorAll('.conflict-cb:checked').forEach(function (cb) {
            selectedUpdates.push(previewData.conflicts[parseInt(cb.dataset.idx)]._data);
        });
        doConfirm(selectedUpdates);
    });

    function doConfirm(selectedUpdates) {
        var confirmBtn = document.getElementById('btn-confirm');
        var directBtn  = document.getElementById('btn-confirm-direct');
        var activeBtn  = confirmBtn || directBtn;
        if (activeBtn) { activeBtn.disabled = true; activeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengimport...'; }

        var token = document.querySelector('input[name="_token"]').value;

        fetch('{{ route("tools.import.confirm") }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                _token:  token,
                type:    previewData.type,
                new:     previewData.new || [],
                updates: selectedUpdates,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('conflict-panel').style.display = 'none';
            var html = '<div class="alert alert-success"><strong>Import selesai!</strong><ul class="mb-0 mt-1">';
            html += '<li>' + data.inserted + ' user baru berhasil diimport.</li>';
            if (data.updated > 0) html += '<li>' + data.updated + ' user berhasil diperbarui.</li>';
            html += '</ul></div>';
            if (data.errors && data.errors.length > 0) {
                html += '<div class="alert alert-warning"><strong>' + data.errors.length + ' error:</strong><ul class="mb-0 mt-1">';
                data.errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
                html += '</ul></div>';
            }
            document.getElementById('import-result').innerHTML = html;
            previewData = null;
        })
        .catch(function () {
            document.getElementById('import-result').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan saat mengimport.</div>';
        });
    }
})();
</script>
@endsection
