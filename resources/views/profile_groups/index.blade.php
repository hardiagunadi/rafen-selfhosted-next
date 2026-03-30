@extends('layouts.admin')

@section('title', 'Profil Group')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Profil Group</h4>
            <small class="text-muted">Kelola group Hotspot/PPPoE</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('profile-groups.create') }}" class="btn btn-primary btn-sm">Tambah Group</a>
            <button type="button" class="btn btn-success btn-sm" id="bulk-export-btn" data-toggle="modal" data-target="#bulk-export-modal">Ekspor Group Ke Router</button>
            <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn"><i class="fas fa-trash mr-1"></i>Hapus</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <div class="form-row">
                <div class="form-group col-md-3 mb-0">
                    <label for="filter-group-type" class="mb-1">Filter Tipe Group</label>
                    <select id="filter-group-type" class="form-control form-control-sm">
                        <option value="">Semua Tipe</option>
                        <option value="pppoe">PPPoE</option>
                        <option value="hotspot">Hotspot</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table id="profile-group-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama Group</th>
                        <th>Owner</th>
                        <th>Router (NAS)</th>
                        <th>Tipe</th>
                        <th>Modul IP Pool</th>
                        <th>Pool Info</th>
                        <th class="text-right" style="width:130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="bulk-export-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="bulk-export-form" action="{{ route('profile-groups.export-bulk') }}" method="POST">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Ekspor Profil Group ke Router</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">Pilih router (NAS) yang akan menerima export.</p>
                <div class="form-group mb-2">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="select-all-routers">
                        <label class="custom-control-label" for="select-all-routers">Pilih semua router</label>
                    </div>
                </div>
                <div class="form-group">
                    @forelse($mikrotikConnections as $connection)
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="custom-control-input router-checkbox" id="router-{{ $connection->id }}" name="mikrotik_connection_ids[]" value="{{ $connection->id }}">
                            <label class="custom-control-label" for="router-{{ $connection->id }}">{{ $connection->name }}</label>
                        </div>
                    @empty
                        <div class="text-muted">Belum ada router aktif.</div>
                    @endforelse
                </div>
                <div id="bulk-export-group-ids"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Ekspor</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var dtTable;

    function init() {
        var table = document.getElementById('profile-group-table');
        if (!table) return;

        if ($.fn.DataTable.isDataTable('#profile-group-table')) {
            dtTable = $('#profile-group-table').DataTable();
        } else {
            dtTable = $('#profile-group-table').DataTable({
                processing: true, serverSide: true,
                ajax: {
                    url: '{{ route("profile-groups.datatable") }}',
                    data: function (d) {
                        d.filter_type = document.getElementById('filter-group-type').value || '';
                    }
                },
                columns: [
                    { data: null, orderable: false, render: function(d, t, row) {
                        return '<input type="checkbox" class="row-check" value="' + row.id + '">';
                    }},
                    { data: 'name' },
                    { data: 'owner', orderable: false },
                    { data: 'router', orderable: false },
                    { data: 'type', orderable: false },
                    { data: 'ip_pool_mode', orderable: false },
                    { data: 'pool_info', orderable: false },
                    { data: null, orderable: false, render: function(d, t, row) {
                        return '<div class="text-right">'
                            + '<a href="' + row.edit_url + '" class="btn btn-sm btn-warning text-white mr-1"><i class="fas fa-pen"></i></a>'
                            + '<button class="btn btn-sm btn-success mr-1" onclick="exportSingle(\'' + row.export_url + '\')" title="Export ke Router"><i class="fas fa-upload"></i></button>'
                            + '<button class="btn btn-sm btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus group ini?"><i class="fas fa-trash"></i></button>'
                            + '</div>';
                    }},
                ],
                pageLength: 20, stateSave: false,
            });
        }

        var filterType = document.getElementById('filter-group-type');
        if (filterType) {
            filterType.onchange = function () {
                if (dtTable) {
                    dtTable.ajax.reload();
                }
            };
        }

        var selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.onchange = function () {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
            };
        }
    }

    window.exportSingle = function (url) {
        if (!confirm('Export profil group ini ke router?')) return;
        var form = document.createElement('form');
        form.method = 'POST'; form.action = url;
        var csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token';
        csrf.value = document.querySelector('meta[name=csrf-token]').content;
        form.appendChild(csrf);
        document.body.appendChild(form);
        form.submit();
    };

    document.getElementById('bulk-delete-btn').addEventListener('click', function () {
        var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
        if (!ids.length) { alert('Pilih minimal satu group.'); return; }
        if (!confirm('Hapus ' + ids.length + ' group terpilih?')) return;
        var params = new URLSearchParams({ _method: 'DELETE' });
        ids.forEach(id => params.append('ids[]', id));
        fetch('{{ route("profile-groups.bulk-destroy") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: params,
        }).then(r => r.json()).then(function (data) {
            AppAjax.showToast(data.status || 'Group dihapus.', 'success');
            if (dtTable) dtTable.ajax.reload(null, false);
        }).catch(function () { AppAjax.showToast('Gagal menghapus.', 'danger'); });
    });

    document.getElementById('bulk-export-btn').addEventListener('click', function (event) {
        var selected = Array.from(document.querySelectorAll('.row-check:checked'));
        if (!selected.length) { event.preventDefault(); event.stopPropagation(); alert('Pilih minimal satu group untuk export.'); return; }
        var container = document.getElementById('bulk-export-group-ids');
        container.innerHTML = '';
        selected.forEach(cb => {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'profile_group_ids[]'; input.value = cb.value;
            container.appendChild(input);
        });
    });

    var selectAllRouters = document.getElementById('select-all-routers');
    if (selectAllRouters) selectAllRouters.addEventListener('change', function () {
        document.querySelectorAll('.router-checkbox').forEach(cb => cb.checked = this.checked);
    });

    var bulkExportForm = document.getElementById('bulk-export-form');
    if (bulkExportForm) bulkExportForm.addEventListener('submit', function (event) {
        if (!Array.from(document.querySelectorAll('.router-checkbox')).some(cb => cb.checked)) {
            event.preventDefault(); alert('Pilih minimal satu router (NAS) untuk export.');
        }
    });
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
