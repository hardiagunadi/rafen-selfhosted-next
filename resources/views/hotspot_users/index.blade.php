@extends('layouts.admin')

@section('title', 'User Hotspot')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="managementDropdown" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Hotspot
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="managementDropdown" style="min-width: 220px;">
                        <a class="dropdown-item" href="{{ route('hotspot-users.create') }}">Tambah User Hotspot</a>
                        <a class="dropdown-item" href="{{ route('hotspot-users.index') }}">List User Hotspot</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">User Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-users fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Registrasi Bulan Ini</div>
                            <div class="h5 mb-0">{{ $stats['registrasi_bulan_ini'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-user-check fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Total User</div>
                            <div class="h5 mb-0">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-warning"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Pelanggan Isolir</div>
                            <div class="h5 mb-0">{{ $stats['pelanggan_isolir'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-danger"><i class="fas fa-ban fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Akun Disable</div>
                            <div class="h5 mb-0">{{ $stats['akun_disable'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="hotspot-users-table" class="table table-striped table-hover mb-0" style="width:100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                            <th>ID Pelanggan</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Profil Hotspot</th>
                            <th>Status Akun</th>
                            <th>Jatuh Tempo</th>
                            <th>Teknisi</th>
                            <th>Owner</th>
                            <th>Perpanjang</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <form id="bulk-delete-form" action="{{ route('hotspot-users.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <script>
    (function () {
        var initialSearchQuery = @json((string) request('search', ''));

        function init() {
            if (!document.getElementById('hotspot-users-table')) return;
            if ($.fn.DataTable.isDataTable('#hotspot-users-table')) {
                $('#hotspot-users-table').DataTable().destroy();
            }
            var table = $('#hotspot-users-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '{{ route('hotspot-users.datatable') }}',
                    type: 'GET',
                    data: function (d) {
                        d.filter_on_process = $('#filter-on-process').is(':checked') ? '1' : '';
                    }
                },
                search: {
                    search: initialSearchQuery
                },
                columns: [
                    { data: 'checkbox',    orderable: false, searchable: false, width: '40px' },
                    { data: 'customer_id', orderable: true },
                    { data: 'nama',        orderable: false },
                    { data: 'username',    orderable: true },
                    { data: 'profil',      orderable: false },
                    { data: 'status',      orderable: false },
                    { data: 'jatuh_tempo', orderable: false },
                    { data: 'teknisi',     orderable: false },
                    { data: 'owner',       orderable: false },
                    { data: 'perpanjang',  orderable: false, searchable: false },
                    { data: 'aksi',        orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada data yang cocok.', emptyTable: 'Belum ada user hotspot.',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                    processing: 'Memuat...',
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[1, 'asc']],
                initComplete: function () {
                    var filters = '<div class="d-flex align-items-center mr-2" style="gap:.75rem;">'
                        + '<div class="custom-control custom-switch mb-0">'
                        +   '<input type="checkbox" class="custom-control-input" id="filter-on-process">'
                        +   '<label class="custom-control-label text-info font-weight-bold" for="filter-on-process">On Process</label>'
                        + '</div>'
                        + '</div>';
                    $('#hotspot-users-table_filter').css('display','flex').css('align-items','center').prepend(filters);
                    $('#filter-on-process').on('change', function () {
                        table.ajax.reload();
                    });
                },
            });

            $('#select-all').on('change', function () {
                $('#hotspot-users-table tbody input[type="checkbox"]').prop('checked', this.checked);
            });

            $('.bulk-delete-action').on('click', function (e) {
                e.preventDefault();
                var ids = $('#hotspot-users-table tbody input[name="ids[]"]:checked').map(function () { return this.value; }).get();
                if (!ids.length) { alert('Pilih user terlebih dahulu.'); return; }
                if (!confirm('Hapus ' + ids.length + ' user terpilih?')) return;
                var form = document.getElementById('bulk-delete-form');
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });

            $(document).on('click', '.toggle-status-btn', function (e) {
                e.preventDefault();
                var $el = $(this);
                var url = $el.data('toggle-url');
                $.post(url, { _token: '{{ csrf_token() }}' }, function (res) {
                    var isEnable = res.status === 'enable';
                    $el.removeClass('badge-success badge-danger')
                       .addClass(isEnable ? 'badge-success' : 'badge-danger')
                       .attr('title', 'Klik untuk ' + (isEnable ? 'disable' : 'enable'));
                });
            });
        }

        document.addEventListener('DOMContentLoaded', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
