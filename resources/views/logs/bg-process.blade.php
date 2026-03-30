@extends('layouts.admin')

@section('title', 'Log BG Process')

@section('content')
<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $stats['pending'] }}</h3><p>Job Pending</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $stats['failed'] }}</h3><p>Job Gagal</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $stats['batches'] }}</h3><p>Batch</p></div>
            <div class="icon"><i class="fas fa-layer-group"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Background Process</h4>
        <div class="btn-group btn-group-sm" id="type-tabs">
            <button class="btn btn-danger active" data-type="failed">Job Gagal</button>
            <button class="btn btn-outline-warning" data-type="pending">Job Pending</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="bg-log-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr id="bg-thead">
                        <th>ID</th>
                        <th>Queue</th>
                        <th>Job</th>
                        <th>Exception</th>
                        <th>Gagal Pada</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var currentType = 'failed';
    var table;

    var columns = {
        failed: [
            { data: 'id' },
            { data: 'queue' },
            { data: 'payload_name' },
            { data: 'exception', render: (d) => `<span class="text-danger small">${d}</span>` },
            { data: 'failed_at' },
        ],
        pending: [
            { data: 'id' },
            { data: 'queue' },
            { data: 'payload_name' },
            { data: 'attempts' },
            { data: 'available_at' },
        ],
    };

    var headers = {
        failed: ['ID', 'Queue', 'Job', 'Exception', 'Gagal Pada'],
        pending: ['ID', 'Queue', 'Job', 'Attempts', 'Tersedia'],
    };

    function buildTable(type) {
        if ($.fn.DataTable.isDataTable('#bg-log-table')) {
            $('#bg-log-table').DataTable().destroy();
            $('#bg-log-table thead tr').html(headers[type].map(h => `<th>${h}</th>`).join(''));
        }

        table = $('#bg-log-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("logs.bg-process.datatable") }}', data: (d) => { d.type = type; } },
            columns: columns[type],
            pageLength: 20, order: [[0, 'desc']], stateSave: false,
        });
    }

    function init() {
        if (!document.getElementById('bg-log-table')) return;

        buildTable(currentType);

        document.querySelectorAll('#type-tabs .btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#type-tabs .btn').forEach(b => {
                    b.className = b.dataset.type === 'failed' ? 'btn btn-outline-danger' : 'btn btn-outline-warning';
                });
                this.className = this.dataset.type === 'failed' ? 'btn btn-danger active' : 'btn btn-warning active';
                currentType = this.dataset.type;
                buildTable(currentType);
            });
        });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
