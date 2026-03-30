@extends('layouts.admin')

@section('title', 'Rekonsiliasi Nota Teknisi')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Rekonsiliasi Nota Teknisi</h4>
        <div class="d-flex align-items-center" style="gap:.5rem;">
            <select id="filter-status" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="submitted">Disubmit</option>
                <option value="verified">Terverifikasi</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="setoran-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Teknisi</th>
                        <th class="text-center">Jml Nota</th>
                        <th class="text-right">Total Tagihan</th>
                        <th class="text-right">Total Tunai Setor</th>
                        <th class="text-center">Status</th>
                        <th>Diverifikasi Oleh</th>
                        <th class="text-right" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="3" class="text-right">Total</td>
                        <td class="text-right" id="setoran-total-tagihan">Rp 0</td>
                        <td class="text-right text-success" id="setoran-total-cash">Rp 0</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var ROLE = '{{ auth()->user()->role }}';
    var currentSummary = {
        total_tagihan_formatted: '0',
        total_cash_formatted: '0',
    };

    function statusBadge(status) {
        var map = {
            draft:     '<span class="badge badge-secondary">Draft</span>',
            submitted: '<span class="badge badge-warning">Disubmit</span>',
            verified:  '<span class="badge badge-success">Terverifikasi</span>',
        };
        return map[status] || status;
    }

    function init() {
        if (!document.getElementById('setoran-table')) return;
        if ($.fn.DataTable.isDataTable('#setoran-table')) return;

        function renderFooterTotals() {
            var totalTagihanElement = document.getElementById('setoran-total-tagihan');
            var totalCashElement = document.getElementById('setoran-total-cash');
            if (totalTagihanElement) {
                totalTagihanElement.textContent = 'Rp ' + (currentSummary.total_tagihan_formatted || '0');
            }
            if (totalCashElement) {
                totalCashElement.textContent = 'Rp ' + (currentSummary.total_cash_formatted || '0');
            }
        }

        var table = $('#setoran-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("teknisi-setoran.datatable") }}',
                data: function (d) {
                    d.status = $('#filter-status').val();
                },
                dataSrc: function (json) {
                    currentSummary = json && json.summary ? json.summary : {
                        total_tagihan_formatted: '0',
                        total_cash_formatted: '0',
                    };
                    return json.data || [];
                },
            },
            columns: [
                { data: 'period_date' },
                { data: 'teknisi_name' },
                { data: 'total_invoices', className: 'text-center' },
                { data: 'total_tagihan', render: function(d) { return 'Rp ' + d; }, className: 'text-right' },
                { data: 'total_cash',    render: function(d) { return 'Rp ' + d; }, className: 'text-right' },
                { data: 'status', render: statusBadge, className: 'text-center', orderable: false },
                { data: 'verified_by' },
                { data: null, orderable: false, className: 'text-right', render: function(d, t, row) {
                    return '<a href="' + row.show_url + '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>';
                }},
            ],
            pageLength: 20, stateSave: false, order: [[0, 'desc']],
            drawCallback: function () {
                renderFooterTotals();
            },
        });

        $('#filter-status').on('change', function () { table.ajax.reload(); });
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
