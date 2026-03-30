@extends('layouts.admin')

@section('title', 'Profil Bandwidth')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Profil Bandwidth</h4>
        <div class="btn-group">
            <a href="{{ route('bandwidth-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Bandwidth</a>
            <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn"><i class="fas fa-trash mr-1"></i>Hapus</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="bandwidth-profile-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama Bandwidth</th>
                        <th>Upload Min | Max (Mbps)</th>
                        <th>Download Min | Max (Mbps)</th>
                        <th>Owner</th>
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
    var dtTable;

    function init() {
        if (!document.getElementById('bandwidth-profile-table')) return;
        if ($.fn.DataTable.isDataTable('#bandwidth-profile-table')) return;

        dtTable = $('#bandwidth-profile-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("bandwidth-profiles.datatable") }}' },
            columns: [
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<input type="checkbox" class="row-check" value="' + row.id + '">';
                }},
                { data: 'name' },
                { data: 'upload', orderable: false },
                { data: 'download', orderable: false },
                { data: 'owner', orderable: false },
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
        fetch('{{ route("bandwidth-profiles.bulk-destroy") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: params,
        }).then(r => r.json()).then(function (data) {
            AppAjax.showToast(data.status || 'Profil dihapus.', 'success');
            if (dtTable) dtTable.ajax.reload(null, false);
        }).catch(function () { AppAjax.showToast('Gagal menghapus.', 'danger'); });
    });
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
