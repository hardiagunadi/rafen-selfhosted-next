@extends('layouts.admin')

@section('title', 'Profil PPP')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Profil PPP</h4>
        <div class="btn-group">
            <a href="{{ route('ppp-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Profil</a>
            <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn"><i class="fas fa-trash mr-1"></i>Hapus</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="ppp-profile-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama</th>
                        <th>Owner</th>
                        <th>Harga Modal</th>
                        <th>Harga Promo</th>
                        <th>PPN</th>
                        <th>Group</th>
                        <th>Bandwidth</th>
                        <th>Masa Aktif</th>
                        <th class="text-right" style="width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var dtTable;

    function init() {
        if (!document.getElementById('ppp-profile-table')) return;
        if ($.fn.DataTable.isDataTable('#ppp-profile-table')) return;

        dtTable = $('#ppp-profile-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("ppp-profiles.datatable") }}' },
            columns: [
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<input type="checkbox" class="row-check" value="' + row.id + '">';
                }},
                { data: 'name' },
                { data: 'owner_name', orderable: false },
                { data: 'harga_modal', render: function(d) { return 'Rp ' + d; }, orderable: false },
                { data: 'harga_promo', render: function(d) { return 'Rp ' + d; }, orderable: false },
                { data: 'ppn', orderable: false },
                { data: 'group_name', orderable: false },
                { data: 'bandwidth', orderable: false },
                { data: 'masa_aktif', orderable: false },
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<div class="text-right">'
                        + '<a href="' + row.edit_url + '" class="btn btn-sm btn-warning text-white mr-1"><i class="fas fa-pen"></i></a>'
                        + '<button class="btn btn-sm btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus profil ini?"><i class="fas fa-trash"></i></button>'
                        + '</div>';
                }},
            ],
            pageLength: 20, stateSave: false,
        });

        document.getElementById('select-all').addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    }

    document.getElementById('bulk-delete-btn').addEventListener('click', function () {
        var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
        if (!ids.length) { alert('Pilih minimal satu profil.'); return; }
        if (!confirm('Hapus ' + ids.length + ' profil terpilih?')) return;

        var params = new URLSearchParams({ _method: 'DELETE' });
        ids.forEach(id => params.append('ids[]', id));

        fetch('{{ route("ppp-profiles.bulk-destroy") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: params,
        }).then(r => r.json()).then(function (data) {
            AppAjax.showToast(data.status || 'Profil dihapus.', 'success');
            if (dtTable) dtTable.ajax.reload(null, false);
        }).catch(function () {
            AppAjax.showToast('Gagal menghapus.', 'danger');
        });
    });
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
