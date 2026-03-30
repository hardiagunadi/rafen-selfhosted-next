@extends('layouts.admin')

@section('title', 'Log WA Blast')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Log WA Blast</h4>
        <div class="d-flex align-items-center" style="gap:.5rem;">
            <select id="filter-event" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Event</option>
                <option value="blast">Blast</option>
                <option value="registration">Registrasi</option>
                <option value="invoice_created">Invoice Terbit</option>
                <option value="invoice_paid">Invoice Lunas</option>
            </select>
            <select id="filter-status" class="form-control form-control-sm" style="width:140px;">
                <option value="">Semua Status</option>
                <option value="sent">Terkirim</option>
                <option value="skip">Skip</option>
                <option value="failed">Gagal</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="wa-blast-log-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Event</th>
                        <th>Dikirim Oleh</th>
                        <th>Nomor</th>
                        <th>Pelanggan</th>
                        <th>Invoice</th>
                        <th style="width:80px;">Status</th>
                        <th>Alasan / Ref</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var eventLabel = {
        blast: 'Blast',
        registration: 'Registrasi',
        invoice_created: 'Invoice Terbit',
        invoice_paid: 'Invoice Lunas',
    };

    function statusBadge(status) {
        if (status === 'sent')   return '<span class="badge badge-success">Terkirim</span>';
        if (status === 'skip')   return '<span class="badge badge-warning">Skip</span>';
        if (status === 'failed') return '<span class="badge badge-danger">Gagal</span>';
        return '<span class="badge badge-secondary">' + status + '</span>';
    }

    function init() {
        if (!document.getElementById('wa-blast-log-table')) return;
        if ($.fn.DataTable.isDataTable('#wa-blast-log-table')) return;

        var table = $('#wa-blast-log-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.wa-blast.datatable") }}',
                data: function (d) {
                    d.status = $('#filter-status').val();
                    d.event  = $('#filter-event').val();
                },
            },
            columns: [
                { data: 'created_at', width: '145px' },
                { data: 'event', render: function(d) { return eventLabel[d] || d; } },
                { data: 'sent_by', render: function(d) {
                    return '<span class="text-info small">' + $.fn.dataTable.render.text().display(d) + '</span>';
                }},
                { data: 'phone' },
                { data: 'customer_name', render: function(d, t, row) {
                    return d + (row.username && row.username !== '-' ? '<br><small class="text-muted">' + row.username + '</small>' : '');
                }},
                { data: 'invoice_number' },
                { data: 'status', render: statusBadge, orderable: false },
                { data: 'reason', render: function(d, t, row) {
                    if (row.status === 'sent') {
                        return row.ref_id && row.ref_id !== '-'
                            ? '<span class="text-muted small">ref: ' + row.ref_id + '</span>'
                            : '<span class="text-muted small">fire-and-forget</span>';
                    }
                    return '<span class="text-danger small">' + $.fn.dataTable.render.text().display(d) + '</span>';
                }, orderable: false },
            ],
            pageLength: 25, stateSave: false,
            order: [[0, 'desc']],
        });

        $('#filter-status, #filter-event').on('change', function () { table.ajax.reload(); });
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
