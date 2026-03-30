@extends('layouts.admin')

@section('title', 'Log GenieACS')

@section('content')
<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $stats['faults'] }}</h3><p>Fault / Error</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-warning">
            <div class="inner"><h3 id="stat-tasks-box">{{ $stats['tasks'] }}</h3><p>Task Pending</p></div>
            <div class="icon"><i class="fas fa-tasks"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $stats['devices'] }}</h3><p>Total Perangkat</p></div>
            <div class="icon"><i class="fas fa-router"></i></div>
        </div>
    </div>
</div>

{{-- Status Panel --}}
<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <h5 class="card-title mb-0"><i class="fas fa-heartbeat mr-1"></i>Status Koneksi GenieACS</h5>
        <div class="card-tools">
            <span id="nbi-badge" class="badge badge-secondary"><i class="fas fa-spinner fa-spin mr-1"></i>Memeriksa...</span>
        </div>
    </div>
    <div class="card-body py-2 px-3" id="status-stats" style="display:none">
        <div class="row text-center">
            <div class="col-4"><strong id="stat-total" class="h5">-</strong><br><small class="text-muted">Total Perangkat</small></div>
            <div class="col-4"><strong id="stat-online" class="h5 text-success">-</strong><br><small class="text-muted">Online</small></div>
            <div class="col-4"><strong id="stat-pending" class="h5 text-warning">-</strong><br><small class="text-muted">Task Pending</small></div>
        </div>
    </div>
</div>

{{-- Main Card --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="mb-0"><i class="fas fa-router text-primary mr-1"></i> Log GenieACS / TR-069</h4>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <div class="btn-group btn-group-sm" id="genieacs-tabs">
                <button class="btn btn-danger active" data-tab="faults"><i class="fas fa-exclamation-triangle mr-1"></i>Faults</button>
                <button class="btn btn-outline-warning" data-tab="tasks"><i class="fas fa-tasks mr-1"></i>Tasks Pending</button>
                <button class="btn btn-outline-info" data-tab="devices"><i class="fas fa-router mr-1"></i>Devices</button>
            </div>
            <button class="btn btn-sm btn-outline-danger" id="btn-delete-all-tasks"><i class="fas fa-trash mr-1"></i>Hapus Semua Task</button>
            <button class="btn btn-sm btn-outline-primary" id="btn-restart-genieacs"><i class="fas fa-redo mr-1"></i>Restart GenieACS</button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-refresh"><i class="fas fa-sync-alt mr-1"></i><span id="refresh-label">Auto Refresh</span></button>
        </div>
    </div>
    <div class="card-body p-0">
        {{-- Search bar --}}
        <div class="px-3 pt-3 pb-2">
            <input type="text" id="tbl-search" class="form-control form-control-sm w-25" placeholder="Cari...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm table-striped mb-0">
                <thead class="thead-dark" id="tbl-head"><tr></tr></thead>
                <tbody id="tbl-body">
                    <tr><td colspan="10" class="text-center py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 d-flex justify-content-between align-items-center" id="tbl-footer">
            <small class="text-muted" id="tbl-info"></small>
            <div id="tbl-pagination" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    var csrfToken   = '{{ csrf_token() }}';
    var currentTab  = 'faults';
    var allRows     = [];
    var pageSize    = 25;
    var currentPage = 1;
    var autoTimer   = null;
    var autoEnabled = false;

    var schema = {
        faults: {
            headers: ['Perangkat','Pelanggan','Kode Error','Pesan','Retries','Waktu'],
            render: function (r) {
                return '<tr>'
                    + '<td><code class="small">'+esc(r.device_id)+'</code></td>'
                    + '<td>'+esc(r.customer_name)+'</td>'
                    + '<td><span class="badge badge-danger">'+esc(r.code)+'</span></td>'
                    + '<td><span class="text-danger small">'+esc(r.message)+'</span></td>'
                    + '<td>'+esc(r.retries)+'</td>'
                    + '<td>'+esc(r.timestamp)+'</td>'
                    + '</tr>';
            },
        },
        tasks: {
            headers: ['Perangkat','Pelanggan','Tipe Task','Detail','Waktu','Aksi'],
            render: function (r) {
                return '<tr>'
                    + '<td><code class="small">'+esc(r.device_id)+'</code></td>'
                    + '<td>'+esc(r.customer_name)+'</td>'
                    + '<td><span class="badge badge-warning text-dark">'+esc(r.task_name)+'</span></td>'
                    + '<td><span class="small text-muted">'+esc(r.detail)+'</span></td>'
                    + '<td>'+esc(r.timestamp)+'</td>'
                    + '<td class="text-nowrap">'
                    +   '<button class="btn btn-xs btn-outline-primary btn-cr mr-1" data-id="'+esc(r.device_id)+'" title="Force Connection Request"><i class="fas fa-plug"></i> CR</button>'
                    +   '<button class="btn btn-xs btn-outline-danger btn-del-tasks" data-id="'+esc(r.device_id)+'" title="Hapus task perangkat ini"><i class="fas fa-times"></i></button>'
                    + '</td>'
                    + '</tr>';
            },
        },
        devices: {
            headers: ['Perangkat','Pelanggan','Serial','Model','Status','Inform Terakhir','Aksi'],
            render: function (r) {
                var model = esc((r.manufacturer !== '-' ? r.manufacturer+' ' : '') + r.model);
                var badge = r.status === 'online'
                    ? '<span class="badge badge-success">Online</span>'
                    : '<span class="badge badge-secondary">Offline</span>';
                return '<tr>'
                    + '<td><code class="small">'+esc(r.device_id)+'</code></td>'
                    + '<td>'+esc(r.customer_name)+'</td>'
                    + '<td>'+esc(r.serial_number)+'</td>'
                    + '<td>'+model+'</td>'
                    + '<td>'+badge+'</td>'
                    + '<td>'+esc(r.last_inform)+'</td>'
                    + '<td class="text-nowrap">'
                    +   '<button class="btn btn-xs btn-outline-primary btn-cr mr-1" data-id="'+esc(r.device_id)+'" title="Force Connection Request"><i class="fas fa-plug"></i> CR</button>'
                    +   '<button class="btn btn-xs btn-outline-danger btn-del-device" data-id="'+esc(r.device_id)+'" data-serial="'+esc(r.serial_number)+'" title="Hapus device"><i class="fas fa-trash"></i></button>'
                    + '</td>'
                    + '</tr>';
            },
        },
    };

    function esc(s) {
        return String(s ?? '-').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Render tabel dari allRows
    function renderTable() {
        var q    = $('#tbl-search').val().toLowerCase().trim();
        var rows = q
            ? allRows.filter(function(r){ return JSON.stringify(r).toLowerCase().includes(q); })
            : allRows;

        var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
        if (currentPage > totalPages) currentPage = 1;
        var start = (currentPage - 1) * pageSize;
        var slice = rows.slice(start, start + pageSize);

        var s = schema[currentTab];
        if (slice.length === 0) {
            $('#tbl-body').html('<tr><td colspan="'+s.headers.length+'" class="text-center text-muted py-3">Tidak ada data.</td></tr>');
        } else {
            $('#tbl-body').html(slice.map(s.render).join(''));
        }

        // Info
        var from = rows.length ? start + 1 : 0;
        var to   = Math.min(start + pageSize, rows.length);
        $('#tbl-info').text('Menampilkan ' + from + ' - ' + to + ' dari ' + rows.length + ' data'
            + (q ? ' (difilter dari ' + allRows.length + ' total)' : ''));

        // Pagination
        var pages = '';
        var maxBtn = 5;
        var half   = Math.floor(maxBtn / 2);
        var pStart = Math.max(1, currentPage - half);
        var pEnd   = Math.min(totalPages, pStart + maxBtn - 1);
        if (pEnd - pStart < maxBtn - 1) pStart = Math.max(1, pEnd - maxBtn + 1);

        pages += '<button class="btn btn-sm btn-outline-secondary '+(currentPage===1?'disabled':'')+'" data-page="'+(currentPage-1)+'"><i class="fas fa-chevron-left"></i></button>';
        for (var p = pStart; p <= pEnd; p++) {
            pages += '<button class="btn btn-sm '+(p===currentPage?'btn-primary':'btn-outline-secondary')+' mx-1" data-page="'+p+'">'+p+'</button>';
        }
        pages += '<button class="btn btn-sm btn-outline-secondary '+(currentPage===totalPages?'disabled':'')+'" data-page="'+(currentPage+1)+'"><i class="fas fa-chevron-right"></i></button>';
        $('#tbl-pagination').html(pages);
    }

    // ── Load data dari server
    function loadData() {
        $('#tbl-body').html('<tr><td colspan="20" class="text-center py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</td></tr>');
        $.getJSON('{{ route("logs.genieacs.data") }}', { tab: currentTab }, function (json) {
            if (json.error) { window.AppAjax.showToast(json.error, 'danger'); allRows = []; }
            else { allRows = json.data || []; }
            currentPage = 1;
            renderTable();
        }).fail(function () {
            window.AppAjax.showToast('Gagal memuat data.', 'danger');
            allRows = [];
            renderTable();
        });
    }

    // ── Set tab aktif
    function setTab(tab) {
        currentTab = tab;
        $('#tbl-search').val('');
        currentPage = 1;

        $('#genieacs-tabs .btn').each(function () {
            var t = $(this).data('tab'), on = (t === tab);
            $(this).removeClass('active btn-danger btn-warning btn-info btn-outline-danger btn-outline-warning btn-outline-info');
            $(this).addClass(on
                ? 'active '+(t==='faults'?'btn-danger': t==='tasks'?'btn-warning':'btn-info')
                : (t==='faults'?'btn-outline-danger': t==='tasks'?'btn-outline-warning':'btn-outline-info'));
        });

        // Update header
        var headers = schema[tab].headers;
        $('#tbl-head tr').html(headers.map(function(h){ return '<th>'+h+'</th>'; }).join(''));

        loadData();
    }

    // ── Status panel
    function loadStatus() {
        $.getJSON('{{ route("logs.genieacs.status") }}', function (data) {
            var badge = $('#nbi-badge');
            badge.html(data.online
                ? '<i class="fas fa-circle text-success mr-1"></i>NBI Online'
                : '<i class="fas fa-circle text-danger mr-1"></i>NBI Offline');
            badge.attr('class', 'badge badge-'+(data.online?'success':'danger'));
            if (data.online && data.total_devices !== undefined) {
                $('#stat-total').text(data.total_devices);
                $('#stat-online').text(data.online_devices);
                $('#stat-pending').text(data.pending_tasks);
                $('#stat-tasks-box').text(data.pending_tasks);
                $('#status-stats').show();
            }
        }).fail(function () {
            $('#nbi-badge').html('<i class="fas fa-circle text-danger mr-1"></i>Error').attr('class','badge badge-danger');
        });
    }

    // ── Helper AJAX action
    function ajaxAction(url, method, payload, onSuccess) {
        $.ajax({
            url: url, method: method,
            headers: { 'X-CSRF-TOKEN': csrfToken },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (d) {
                window.AppAjax.showToast(d.message, d.status === 'ok' ? 'success' : 'danger');
                if (d.status === 'ok' && onSuccess) onSuccess();
            },
            error: function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                window.AppAjax.showToast(msg, 'danger');
            },
        });
    }

    // ── Events
    $(document).on('click', '#genieacs-tabs .btn', function () { setTab($(this).data('tab')); });

    $('#tbl-search').on('input', function () { currentPage = 1; renderTable(); });

    $(document).on('click', '#tbl-pagination button:not(.disabled)', function () {
        currentPage = parseInt($(this).data('page'));
        renderTable();
    });

    $('#btn-refresh').on('click', function () {
        autoEnabled = !autoEnabled;
        if (autoEnabled) {
            $('#refresh-label').text('Stop Refresh');
            $(this).removeClass('btn-outline-secondary').addClass('btn-warning');
            autoTimer = setInterval(function () { loadData(); loadStatus(); }, 30000);
        } else {
            $('#refresh-label').text('Auto Refresh');
            $(this).removeClass('btn-warning').addClass('btn-outline-secondary');
            clearInterval(autoTimer);
        }
    });

    $('#btn-delete-all-tasks').on('click', function () {
        if (!confirm('Hapus SEMUA task pending dari GenieACS?')) return;
        ajaxAction('{{ route("logs.genieacs.delete-task") }}', 'DELETE', {}, function () {
            loadData(); loadStatus();
        });
    });

    var restartDone = false;
    $('#btn-restart-genieacs').on('click', function () {
        if (!confirm('Restart semua service GenieACS (cwmp, nbi, fs)?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Merestart...');
        restartDone = false;

        var safeTid = setTimeout(function () {
            if (restartDone) return; restartDone = true;
            window.AppAjax.showToast('GenieACS berhasil direstart.', 'success');
            $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i>Restart GenieACS');
            setTimeout(loadStatus, 2000);
        }, 15000);

        $.ajax({
            url: '{{ route("genieacs.restart") }}', method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken }, dataType: 'json',
            success: function (d) {
                if (restartDone) return; restartDone = true;
                clearTimeout(safeTid);
                window.AppAjax.showToast(d.message || 'GenieACS berhasil direstart.', 'success');
                $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i>Restart GenieACS');
                setTimeout(loadStatus, 2000);
            },
            error: function (xhr) {
                if (restartDone) return; restartDone = true;
                clearTimeout(safeTid);
                window.AppAjax.showToast(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Restart gagal.', 'danger');
                $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i>Restart GenieACS');
            },
        });
    });

    $(document).on('click', '.btn-cr', function () {
        var id = $(this).data('id');
        ajaxAction('{{ route("logs.genieacs.connection-request") }}', 'POST', { device_id: id }, null);
    });

    $(document).on('click', '.btn-del-tasks', function () {
        if (!confirm('Hapus semua task untuk perangkat ini?')) return;
        var id = $(this).data('id');
        ajaxAction('{{ route("logs.genieacs.delete-task") }}', 'DELETE', { device_id: id }, function () {
            loadData(); loadStatus();
        });
    });

    $(document).on('click', '.btn-del-device', function () {
        var id = $(this).data('id'), serial = $(this).data('serial');
        var label = serial && serial !== '-' ? serial : id;
        if (!confirm('Hapus device "'+label+'" dari GenieACS?\n\nIni akan menghapus:\n• Device dari MongoDB GenieACS\n• Semua task pending device ini\n• Link ke pelanggan (cpe_devices)')) return;
        ajaxAction('{{ route("logs.genieacs.delete-device") }}', 'DELETE', { device_id: id }, function () {
            loadData(); loadStatus();
        });
    });

    // ── Init
    loadStatus();
    setTab('faults');
});
</script>
@endpush
