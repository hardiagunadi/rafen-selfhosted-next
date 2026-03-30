@extends('layouts.admin')

@section('title', 'Log Aktivitas')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Log Aktivitas User</h4>
        <div class="d-flex flex-wrap" style="gap:.5rem;">
            @if(auth()->user()->isSuperAdmin() && $tenants && $tenants->isNotEmpty())
            <select id="filter-tenant" class="form-control form-control-sm" style="width:180px;">
                <option value="">Semua Tenant</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            @endif
            <select id="filter-subject" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Objek</option>
                <option value="PppUser">Pelanggan</option>
                <option value="Invoice">Invoice</option>
                <option value="PppProfile">Paket</option>
            </select>
            <select id="filter-action" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Aksi</option>
                <option value="created">Dibuat</option>
                <option value="updated">Diubah</option>
                <option value="deleted">Dihapus</option>
                <option value="paid">Dibayar</option>
                <option value="renewed">Diperpanjang</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="activity-log-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:145px;">Waktu</th>
                        @if(auth()->user()->isSuperAdmin())
                        <th>Tenant</th>
                        @endif
                        <th>Pengguna</th>
                        <th style="width:115px;">Aksi</th>
                        <th>Objek</th>
                        <th style="width:130px;">IP Address</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var isSuperAdmin = {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }};

    var actionBadge = {
        created: '<span class="badge badge-success">Dibuat</span>',
        updated: '<span class="badge badge-info">Diubah</span>',
        deleted: '<span class="badge badge-danger">Dihapus</span>',
        paid:    '<span class="badge" style="background:#6f42c1;color:#fff;">Dibayar</span>',
        renewed: '<span class="badge badge-warning">Diperpanjang</span>',
    };

    var subjectLabel = {
        PppUser:    'Pelanggan',
        Invoice:    'Invoice',
        PppProfile: 'Paket',
    };

    function renderAction(d) {
        return actionBadge[d] || '<span class="badge badge-secondary">' + d + '</span>';
    }

    function renderSubject(d, type, row) {
        var tLabel = subjectLabel[row.subject_type] || row.subject_type;
        return '<span class="text-muted small">' + tLabel + '</span> ' + $.fn.dataTable.render.text().display(row.subject_label || '-');
    }

    function renderUser(d, type, row) {
        var name  = $.fn.dataTable.render.text().display(d || '-');
        var email = $.fn.dataTable.render.text().display(row.user_email || '');
        return '<div class="font-weight-bold">' + name + '</div>'
             + (email ? '<div class="text-muted small">' + email + '</div>' : '');
    }

    function renderTenant(d, type, row) {
        var name  = $.fn.dataTable.render.text().display(d || '-');
        var email = $.fn.dataTable.render.text().display(row.owner_email || '');
        return '<div class="font-weight-bold">' + name + '</div>'
             + (email ? '<div class="text-muted small">' + email + '</div>' : '');
    }

    function init() {
        if (!document.getElementById('activity-log-table')) return;
        if ($.fn.DataTable.isDataTable('#activity-log-table')) return;

        var columns = [
            { data: 'created_at', orderable: false },
            { data: 'user_name', render: renderUser, orderable: false },
            { data: 'action', render: renderAction, orderable: false },
            { data: 'subject_label', render: renderSubject, orderable: false },
            { data: 'ip_address', orderable: false },
        ];

        if (isSuperAdmin) {
            columns.splice(1, 0, {
                data: 'owner_name',
                render: renderTenant,
                orderable: false
            });
        }

        var table = $('#activity-log-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.activity.data") }}',
                data: function (d) {
                    d.action       = $('#filter-action').val();
                    d.subject_type = $('#filter-subject').val();
                    if (isSuperAdmin) {
                        d.owner_id = $('#filter-tenant').val() || '';
                    }
                }
            },
            columns: columns,
            pageLength: 25, stateSave: false, searching: true,
        });

        $('#filter-action, #filter-subject').on('change', function () { table.ajax.reload(); });
        if (isSuperAdmin) {
            $('#filter-tenant').on('change', function () { table.ajax.reload(); });
        }
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
