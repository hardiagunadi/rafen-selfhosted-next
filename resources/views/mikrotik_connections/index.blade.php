@extends('layouts.admin')

@section('title', 'Router NAS')

@section('content')
<div class="mk-page">

    {{-- Header --}}
    <div class="mk-page-header">
        <div class="mk-page-header-left">
            <div class="mk-page-icon">
                <i class="fas fa-network-wired"></i>
            </div>
            <div>
                <div class="mk-page-title">Router <span class="mk-page-title-dim">[NAS]</span></div>
                <div class="mk-page-sub">Manajemen koneksi MikroTik &amp; Network Access Server</div>
            </div>
        </div>
        @if(auth()->user()->role !== 'teknisi')
        <a href="{{ route('mikrotik-connections.create') }}" class="mk-btn-add">
            <i class="fas fa-plus mr-1"></i> Tambah Router
        </a>
        @endif
    </div>

    {{-- Info bar --}}
    <div class="mk-info-bar">
        <div class="mk-info-item">
            <i class="fas fa-info-circle mk-info-icon"></i>
            Sistem mengecek ping setiap <strong>5 menit</strong>, tabel refresh otomatis setiap <strong>1 menit</strong>.
        </div>
        <div class="mk-info-badges">
            <span class="mk-status-chip mk-status-connected"><i class="fas fa-circle mr-1" style="font-size:.55rem;vertical-align:middle;"></i>Terhubung</span>
            <span class="mk-status-chip mk-status-unstable"><i class="fas fa-circle mr-1" style="font-size:.55rem;vertical-align:middle;"></i>Tidak Stabil</span>
            <span class="mk-status-chip mk-status-down"><i class="fas fa-circle mr-1" style="font-size:.55rem;vertical-align:middle;"></i>Tidak Terhubung</span>
        </div>
    </div>

    {{-- Table card --}}
    <div class="mk-card">
        <div class="mk-table-responsive">
            <table id="router-table" class="mk-dt-table">
                <thead>
                    <tr>
                        <th style="width:52px;">API</th>
                        <th style="width:140px;">Status</th>
                        <th>Detail</th>
                        <th>Nama Router</th>
                        <th>IP Address</th>
                        <th>User Aktif</th>
                        <th>Cek Terakhir</th>
                        <th style="width:80px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>

<style>
/* ── Page layout ─────────────────────────────────────────────────── */
.mk-page { display: flex; flex-direction: column; gap: 1rem; }

/* ── Page header ─────────────────────────────────────────────────── */
.mk-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
}
.mk-page-header-left {
    display: flex;
    align-items: center;
    gap: .85rem;
}
.mk-page-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: linear-gradient(140deg,#0369a1,#0ea5e9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(14,165,233,.3);
}
.mk-page-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--app-text, #0f172a);
    line-height: 1.2;
}
.mk-page-title-dim { color: var(--app-text-soft, #5b6b83); font-weight: 500; }
.mk-page-sub {
    font-size: .8rem;
    color: var(--app-text-soft, #5b6b83);
    margin-top: .15rem;
}
.mk-btn-add {
    display: inline-flex;
    align-items: center;
    padding: .45rem 1.1rem;
    border-radius: 10px;
    background: linear-gradient(140deg,#0369a1,#0ea5e9);
    color: #fff;
    font-size: .84rem;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 3px 12px rgba(14,165,233,.25);
    transition: opacity 150ms, transform 150ms;
    white-space: nowrap;
}
.mk-btn-add:hover { opacity: .9; transform: translateY(-1px); color: #fff; text-decoration: none; }

/* ── Info bar ────────────────────────────────────────────────────── */
.mk-info-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .65rem;
    padding: .75rem 1rem;
    background: #f0f7ff;
    border: 1px solid #c7dff7;
    border-radius: 12px;
    font-size: .82rem;
    color: #1e4d78;
}
.mk-info-icon { margin-right: .4rem; opacity: .7; }
.mk-info-badges { display: flex; gap: .4rem; flex-wrap: wrap; }
.mk-status-chip {
    display: inline-flex;
    align-items: center;
    padding: .2rem .65rem;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 600;
}
.mk-status-connected { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #065f46; }
.mk-status-unstable  { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); color: #92400e; }
.mk-status-down      { background: rgba(239,68,68,.1);   border: 1px solid rgba(239,68,68,.25); color: #991b1b; }

/* ── Card ────────────────────────────────────────────────────────── */
.mk-card {
    background: var(--app-surface, #fff);
    border: 1px solid var(--app-border, #d7e1ee);
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(15,23,42,.06);
    overflow: hidden;
}
.mk-table-responsive { overflow-x: auto; }

/* ── DataTable ───────────────────────────────────────────────────── */
.mk-dt-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.mk-dt-table thead tr {
    background: #f8fbff;
    border-bottom: 2px solid var(--app-border, #d7e1ee);
}
.mk-dt-table thead th {
    padding: .7rem 1rem;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--app-text-soft, #5b6b83);
    white-space: nowrap;
}
.mk-dt-table tbody tr {
    border-bottom: 1px solid #edf2f9;
    transition: background 120ms;
}
.mk-dt-table tbody tr:last-child { border-bottom: none; }
.mk-dt-table tbody tr:hover { background: #f4f8ff; }
.mk-dt-table tbody td {
    padding: .65rem 1rem;
    color: var(--app-text, #0f172a);
    vertical-align: middle;
}

/* ── Status badges ───────────────────────────────────────────────── */
.mk-dt-table .badge-success {
    background: rgba(16,185,129,.14);
    border: 1px solid rgba(16,185,129,.3);
    color: #065f46;
    border-radius: 20px;
    padding: .22rem .65rem;
    font-size: .72rem;
    font-weight: 600;
}
.mk-dt-table .badge-warning {
    background: rgba(245,158,11,.14);
    border: 1px solid rgba(245,158,11,.3);
    color: #92400e;
    border-radius: 20px;
    padding: .22rem .65rem;
    font-size: .72rem;
    font-weight: 600;
}
.mk-dt-table .badge-danger {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.25);
    color: #991b1b;
    border-radius: 20px;
    padding: .22rem .65rem;
    font-size: .72rem;
    font-weight: 600;
}
.mk-dt-table .badge-secondary {
    background: rgba(100,116,139,.12);
    border: 1px solid rgba(100,116,139,.25);
    color: #334155;
    border-radius: 20px;
    padding: .22rem .65rem;
    font-size: .72rem;
    font-weight: 600;
}

/* ── Action buttons ──────────────────────────────────────────────── */
.mk-dt-table .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: .8rem;
    transition: opacity 130ms, transform 130ms;
    margin: 0 2px;
    text-decoration: none;
}
.mk-dt-table .btn-icon:hover { opacity: .82; transform: scale(1.08); }
.mk-dt-table .btn-icon-api    { background: linear-gradient(140deg,#0369a1,#0ea5e9); color:#fff; }
.mk-dt-table .btn-icon-edit   { background: linear-gradient(140deg,#b45309,#f59e0b); color:#fff; }
.mk-dt-table .btn-icon-delete { background: linear-gradient(140deg,#be123c,#f43f5e); color:#fff; }

/* ── DataTables override ─────────────────────────────────────────── */
#router-table_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid var(--app-border, #d7e1ee);
    padding: .3rem .7rem;
    font-size: .82rem;
}
#router-table_wrapper .dataTables_length select {
    border-radius: 8px;
    border: 1px solid var(--app-border, #d7e1ee);
    padding: .3rem .5rem;
    font-size: .82rem;
}
#router-table_wrapper .dataTables_info,
#router-table_wrapper .dataTables_filter,
#router-table_wrapper .dataTables_length { padding: .75rem 1rem; font-size: .8rem; }
#router-table_wrapper .dataTables_paginate { padding: .75rem 1rem; }
#router-table_wrapper .paginate_button {
    border-radius: 7px !important;
    font-size: .8rem !important;
}
#router-table_wrapper .paginate_button.current {
    background: linear-gradient(140deg,#0369a1,#0ea5e9) !important;
    border-color: transparent !important;
    color: #fff !important;
}

/* ── User active cell ────────────────────────────────────────────── */
.mk-active-users {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-weight: 600;
    color: #0369a1;
}
</style>

<script>
(function () {
    var dtTable;
    var refreshTimer;

    function renderStatus(d, t, row) {
        return '<span class="badge ' + row.ping_class + '">' + row.ping_status + '</span>';
    }

    function renderActiveUsers(d) {
        return '<span class="mk-active-users"><i class="fas fa-users" style="font-size:.78rem;opacity:.6;"></i>' + d + '</span>';
    }

    function renderAksi(d, t, row) {
        if (!row.can_edit) {
            return '<div style="text-align:center"><span style="font-size:.75rem;color:#94a3b8;">Read Only</span></div>';
        }
        return '<div style="text-align:center;">'
            + '<a href="' + row.edit_url + '" class="btn-icon btn-icon-edit" title="Edit"><i class="fas fa-pen"></i></a>'
            + '<button class="btn-icon btn-icon-delete" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus koneksi ini?" title="Hapus"><i class="fas fa-trash"></i></button>'
            + '</div>';
    }

    function renderApi(d, t, row) {
        return '<div style="text-align:center;">'
            + '<a href="' + row.api_url + '" class="btn-icon btn-icon-api" title="Buka API Dashboard"><i class="fas fa-plug"></i></a>'
            + '</div>';
    }

    function init() {
        if (!document.getElementById('router-table')) return;
        if ($.fn.DataTable.isDataTable('#router-table')) return;

        dtTable = $('#router-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("mikrotik-connections.datatable") }}' },
            columns: [
                { data: null, orderable: false, render: renderApi },
                { data: 'ping_status', render: renderStatus, orderable: false },
                { data: 'ping_message', orderable: false },
                { data: 'name' },
                { data: 'host', orderable: false },
                { data: 'radius_count', render: renderActiveUsers, orderable: false },
                { data: 'last_ping_at', orderable: false },
                { data: null, render: renderAksi, orderable: false },
            ],
            pageLength: 20, stateSave: false,
            language: {
                search: '',
                searchPlaceholder: 'Cari router...',
                lengthMenu: 'Tampilkan _MENU_',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ router',
                infoEmpty: 'Tidak ada data',
                paginate: { previous: '&lsaquo;', next: '&rsaquo;' },
                processing: '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...',
                emptyTable: 'Belum ada router yang ditambahkan.',
                zeroRecords: 'Router tidak ditemukan.',
            },
        });

        refreshTimer = setInterval(function () {
            if (dtTable) dtTable.ajax.reload(null, false);
        }, 60000);
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
