@extends('layouts.admin')

@section('title', 'Log Login')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Log Login</h4>
        <div class="d-flex gap-2">
            <select id="filter-event" class="form-control form-control-sm" style="width:150px;">
                <option value="">Semua Event</option>
                <option value="login">Login</option>
                <option value="logout">Logout</option>
                <option value="failed">Gagal</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="login-log-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Event</th>
                        <th>Email</th>
                        <th>Nama</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    function eventBadge(event) {
        const map = { login: 'badge-success', logout: 'badge-secondary', failed: 'badge-danger' };
        const label = { login: 'Login', logout: 'Logout', failed: 'Gagal' };
        return `<span class="badge ${map[event] || 'badge-light'}">${label[event] || event}</span>`;
    }

    function init() {
        if (!document.getElementById('login-log-table')) return;
        if ($.fn.DataTable.isDataTable('#login-log-table')) return;

        var table = $('#login-log-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.login.datatable") }}',
                data: function (d) { d.event = $('#filter-event').val(); }
            },
            columns: [
                { data: 'created_at' },
                { data: 'event', render: (d) => eventBadge(d), orderable: false },
                { data: 'email' },
                { data: 'name' },
                { data: 'ip_address' },
                { data: 'user_agent', render: (d) => `<span title="${d}" style="max-width:200px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${d}</span>` },
            ],
            pageLength: 25, order: [[0, 'desc']], stateSave: false,
        });

        $('#filter-event').on('change', function () { table.ajax.reload(); });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
