@extends('layouts.admin')

@section('title', 'Manajemen Voucher')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Voucher
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" style="min-width: 200px;">
                        <a class="dropdown-item" href="{{ route('vouchers.create') }}">Generate Voucher Baru</a>
                        <a class="dropdown-item" href="{{ route('vouchers.index') }}">List Semua Voucher</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Unused Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">Voucher Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-ticket-alt fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Unused</div>
                            <div class="h5 mb-0">{{ number_format($stats['unused']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-check-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Used</div>
                            <div class="h5 mb-0">{{ number_format($stats['used']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-secondary"><i class="fas fa-times-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Expired</div>
                            <div class="h5 mb-0">{{ number_format($stats['expired']) }}</div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="table-responsive">
                <table id="vouchers-table" class="table table-striped table-hover mb-0" style="width:100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                            <th>Kode</th>
                            <th>Batch</th>
                            <th>Profil Hotspot</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <form id="bulk-delete-form" action="{{ route('vouchers.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <script>
    (function () {
        var dtTable = null;

        function init() {
            if (!document.getElementById('vouchers-table')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }

            dtTable = $('#vouchers-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("vouchers.datatable") }}',
                    type: 'GET',
                    data: function (d) { },
                },
                columns: [
                    { data: 'checkbox', orderable: false, searchable: false, width: '40px' },
                    { data: 'code',     orderable: true },
                    { data: 'batch',    orderable: true },
                    { data: 'profil',   orderable: false },
                    { data: 'status',   orderable: false },
                    { data: 'expired',  orderable: true },
                    { data: 'aksi',     orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada voucher yang cocok.', emptyTable: 'Belum ada voucher.',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                    processing: 'Memuat...',
                },
                pageLength: 20,
                lengthMenu: [[20, 50, 100, 200], [20, 50, 100, 200]],
                order: [[1, 'asc']],
                stateSave: false,
                drawCallback: function () { },
            });


            $('#select-all').off('change.voucher').on('change.voucher', function () {
                $('#vouchers-table tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
            });

            $('.bulk-delete-action').off('click.voucher').on('click.voucher', function (e) {
                e.preventDefault();
                var ids = $('#vouchers-table tbody input[name="ids[]"]:checked').map(function () { return this.value; }).get();
                if (!ids.length) { alert('Pilih voucher unused terlebih dahulu.'); return; }
                if (!confirm('Hapus ' + ids.length + ' voucher terpilih?')) return;
                var form = document.getElementById('bulk-delete-form');
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });
        }
        document.addEventListener('DOMContentLoaded', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
