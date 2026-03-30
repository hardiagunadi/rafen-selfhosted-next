@extends('layouts.admin')

@section('title', 'Data ODP')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Data ODP</h4>
            <small class="text-muted">Master Optical Distribution Point per tenant</small>
        </div>
        <a href="{{ route('odps.create') }}" class="btn btn-primary btn-sm">Tambah ODP</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="odps-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Kode ODP</th>
                        <th>Nama</th>
                        <th>Area</th>
                        <th>Koordinat</th>
                        <th>Port (Pakai/Kapasitas/Sisa)</th>
                        <th>Status</th>
                        <th>Owner</th>
                        <th class="text-right" style="width:110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function statusBadge(status) {
        if (status === 'ACTIVE') return '<span class="badge badge-success">ACTIVE</span>';
        if (status === 'MAINTENANCE') return '<span class="badge badge-warning">MAINTENANCE</span>';
        return '<span class="badge badge-secondary">INACTIVE</span>';
    }

    function init() {
        if (!document.getElementById('odps-table')) return;
        if ($.fn.DataTable.isDataTable('#odps-table')) return;

        $('#odps-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: { url: '{{ route('odps.datatable') }}' },
            columns: [
                { data: 'code' },
                { data: 'name' },
                { data: 'area' },
                { data: 'coordinates', orderable: false },
                { data: null, orderable: false, render: function (d, t, row) {
                    return row.used_ports + ' / ' + row.capacity_ports + ' / ' + row.remaining_ports;
                }},
                { data: 'status', orderable: false, render: function (d) { return statusBadge(d); } },
                { data: 'owner', orderable: false },
                { data: null, orderable: false, searchable: false, className: 'text-right', render: function (d, t, row) {
                    if (!row.can_edit && !row.can_delete) {
                        return '<span class="text-muted">-</span>';
                    }

                    var actions = '<div class="btn-group btn-group-sm">';
                    if (row.can_edit) {
                        actions += '<a href="' + row.edit_url + '" class="btn btn-warning text-white" title="Edit"><i class="fas fa-pen"></i></a>';
                    }
                    if (row.can_delete) {
                        actions += '<button class="btn btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus data ODP ini?" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }

                    actions += '</div>';
                    return actions;
                }},
            ],
            pageLength: 20,
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(disaring dari _MAX_ total data)',
                zeroRecords: 'Tidak ada data yang cocok.',
                emptyTable: 'Belum ada data ODP.',
                paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                processing: 'Memuat...',
            },
            order: [[0, 'asc']],
        });
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endpush
