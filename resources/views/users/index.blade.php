@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Manajemen Pengguna</h4>
            <small class="text-muted">Level: Administrator, IT Support, NOC, Keuangan, Teknisi, Customer Services</small>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">Tambah Pengguna</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="user-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Nama</th>
                        <th>Nickname</th>
                        <th>Email</th>
                        <th>HP / WA</th>
                        <th>Role</th>
                        @if(auth()->user()->isSuperAdmin())
                        <th>Tenant / Induk</th>
                        @endif
                        <th>Terakhir Login</th>
                        <th class="text-right" style="width:110px;">Aksi</th>
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
        if (!document.getElementById('user-table')) return;
        if ($.fn.DataTable.isDataTable('#user-table')) return;

        $('#user-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("users.datatable") }}' },
            columns: [
                { data: 'name' },
                { data: 'nickname', orderable: false },
                { data: 'email' },
                { data: 'phone', orderable: false },
                { data: 'role', orderable: false },
                @if(auth()->user()->isSuperAdmin())
                { data: 'tenant', orderable: false, render: function(d) { return d || '-'; } },
                @endif
                { data: 'last_login_at', orderable: false },
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<div class="text-right">'
                        + '<a href="' + row.edit_url + '" class="btn btn-sm btn-warning text-white mr-1"><i class="fas fa-pen"></i></a>'
                        + '<button class="btn btn-sm btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus pengguna ini?"><i class="fas fa-trash"></i></button>'
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
