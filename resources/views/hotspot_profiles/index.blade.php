@extends('layouts.admin')

@section('title', 'Profil Hotspot')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Profil Hotspot</h4>
            <div class="btn-group">
                <a href="{{ route('hotspot-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Profil</a>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">Hapus</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table id="hotspot-profiles-table" class="table table-striped table-hover mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama</th>
                        <th>Harga</th>
                        <th>Bandwidth</th>
                        <th>Tipe Profil</th>
                        <th>Profil Group</th>
                        <th>Shared Users</th>
                        <th>Pengguna</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
    (function () {
        var table = null;

        function initDataTable() {
            if (!document.getElementById('hotspot-profiles-table')) return;
            if (table) { table.destroy(); table = null; }

            table = $('#hotspot-profiles-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('hotspot-profiles.datatable') }}',
                columns: [
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function (id) {
                            return '<input type="checkbox" class="row-check" value="' + id + '">';
                        }
                    },
                    { data: 'name' },
                    {
                        data: 'harga_jual',
                        orderable: false,
                        searchable: false,
                        render: function (v, type, row) {
                            var jual = parseFloat(row.harga_jual).toLocaleString('id-ID', {minimumFractionDigits:0});
                            var lines = '<div>Rp ' + jual + '</div>';
                            if (parseFloat(row.harga_promo) > 0) {
                                var promo = parseFloat(row.harga_promo).toLocaleString('id-ID', {minimumFractionDigits:0});
                                lines += '<div class="small text-muted">Promo: Rp ' + promo + '</div>';
                            }
                            if (parseFloat(row.ppn) > 0) {
                                lines += '<div class="small text-muted">PPN: ' + parseFloat(row.ppn) + '%</div>';
                            }
                            return lines;
                        }
                    },
                    { data: 'bandwidth_name', searchable: false },
                    { data: 'tipe_profil', orderable: false, searchable: false },
                    { data: 'profile_group_name', searchable: false },
                    {
                        data: 'shared_users',
                        orderable: false,
                        searchable: false,
                        render: function (v, type, row) {
                            return '<div>' + v + ' user</div><div class="small text-muted">' + row.prioritas_label + '</div>';
                        }
                    },
                    {
                        data: 'hotspot_users_count',
                        orderable: false,
                        searchable: false,
                        render: function (v, type, row) {
                            return '<div class="small"><i class="fas fa-user mr-1 text-primary"></i>' + row.hotspot_users_count + ' user</div>'
                                + '<div class="small"><i class="fas fa-ticket-alt mr-1 text-success"></i>' + row.vouchers_count + ' voucher</div>';
                        }
                    },
                    { data: 'aksi', orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    processing: 'Memuat data...',
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ profil',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(disaring dari _MAX_ total)',
                    zeroRecords: 'Tidak ada profil hotspot.',
                    paginate: { first: '«', previous: '‹', next: '›', last: '»' },
                },
                order: [[1, 'asc']],
                pageLength: 25,
                drawCallback: function () {
                    bindDeleteButtons();
                },
            });
        }

        function bindDeleteButtons() {
            document.querySelectorAll('[data-ajax-delete]').forEach(function (btn) {
                if (btn._bound) return;
                btn._bound = true;
                btn.addEventListener('click', function () {
                    if (!confirm(btn.dataset.confirm || 'Hapus?')) return;
                    fetch(btn.dataset.ajaxDelete, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'X-HTTP-Method-Override': 'DELETE',
                        },
                        body: new URLSearchParams({ _method: 'DELETE' }),
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        AppAjax.showToast(data.status || 'Dihapus.', 'success');
                        if (table) table.ajax.reload(null, false);
                    }).catch(function () {
                        AppAjax.showToast('Gagal menghapus.', 'danger');
                    });
                });
            });
        }

        // select-all
        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'select-all') {
                document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = e.target.checked; });
            }
        });

        // bulk delete
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'bulk-delete-btn') {
                var checked = Array.from(document.querySelectorAll('.row-check:checked')).map(function (cb) { return cb.value; });
                if (!checked.length) { alert('Pilih minimal satu profil untuk dihapus.'); return; }
                if (!confirm('Hapus profil terpilih?')) return;
                var params = new URLSearchParams({ _method: 'DELETE' });
                checked.forEach(function (id) { params.append('ids[]', id); });
                fetch('{{ route('hotspot-profiles.bulk-destroy') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: params,
                }).then(function (r) { return r.json(); }).then(function (data) {
                    AppAjax.showToast(data.status || 'Profil dihapus.', 'success');
                    document.getElementById('select-all').checked = false;
                    if (table) table.ajax.reload(null, false);
                }).catch(function () {
                    AppAjax.showToast('Gagal menghapus.', 'danger');
                });
            }
        });

        document.addEventListener('DOMContentLoaded', initDataTable);
        if (document.readyState !== 'loading') initDataTable();
    })();
    </script>
@endsection
