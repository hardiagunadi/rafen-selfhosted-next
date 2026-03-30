@extends('layouts.admin')

@section('title', 'Log Auth RADIUS')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Log Auth RADIUS</h4>
        <select id="filter-reply" class="form-control form-control-sm" style="width:180px;">
            <option value="">Semua Reply</option>
            <option value="Access-Accept">Access-Accept</option>
            <option value="Access-Reject">Access-Reject</option>
        </select>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="radius-auth-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Username</th>
                        <th>Reply</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    function replyBadge(reply) {
        if (reply === 'Access-Accept') return '<span class="badge badge-success">Accept</span>';
        if (reply === 'Access-Reject') return '<span class="badge badge-danger">Reject</span>';
        return `<span class="badge badge-secondary">${reply}</span>`;
    }

    function init() {
        if (!document.getElementById('radius-auth-table')) return;
        if ($.fn.DataTable.isDataTable('#radius-auth-table')) return;

        var table = $('#radius-auth-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.radius-auth.datatable") }}',
                data: function (d) { d.reply = $('#filter-reply').val(); }
            },
            columns: [
                { data: 'authdate' },
                { data: 'username' },
                { data: 'reply', render: (d) => replyBadge(d), orderable: false },
            ],
            pageLength: 25, order: [[0, 'desc']], stateSave: false,
        });

        $('#filter-reply').on('change', function () { table.ajax.reload(); });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
