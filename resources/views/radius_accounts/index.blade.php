@extends('layouts.admin')

@section('title', 'Manajemen PPPoE & Hotspot')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Akun RADIUS</h3>
        <a href="{{ route('radius-accounts.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Akun
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="radius-account-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Username</th>
                        <th>Layanan</th>
                        <th>IP PPPoE</th>
                        <th>Rate Limit</th>
                        <th>Mikrotik</th>
                        <th style="width:90px;">Status</th>
                        <th class="text-right" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    function init() {
        if (!document.getElementById('radius-account-table')) return;
        if ($.fn.DataTable.isDataTable('#radius-account-table')) return;

        $('#radius-account-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("radius-accounts.datatable") }}' },
            columns: [
                { data: 'username' },
                { data: 'service', orderable: false },
                { data: 'ip_address', orderable: false },
                { data: 'rate_limit', orderable: false },
                { data: 'router', orderable: false },
                { data: 'is_active', orderable: false, render: function(d) {
                    return d ? '<span class="badge badge-success">Aktif</span>'
                             : '<span class="badge badge-secondary">Non-aktif</span>';
                }},
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<div class="text-right">'
                        + '<a href="' + row.edit_url + '" class="btn btn-sm btn-warning text-white mr-1"><i class="fas fa-pen"></i></a>'
                        + '<button class="btn btn-sm btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus akun ini?"><i class="fas fa-trash"></i></button>'
                        + '</div>';
                }},
            ],
            pageLength: 20, stateSave: false,
        });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
