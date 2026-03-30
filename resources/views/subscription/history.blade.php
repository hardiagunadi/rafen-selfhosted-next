@extends('layouts.admin')

@section('title', 'Riwayat Pembayaran')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Riwayat Pembayaran Langganan</h3>
    </div>
    <div class="card-body p-0">
        <table id="dt-history" class="table table-hover text-nowrap mb-0 w-100">
            <thead>
                <tr>
                    <th>No. Pembayaran</th>
                    <th>Paket</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var dtTable = null;
    var dtUrl = '{{ route("subscription.history-datatable") }}';

    function init() {
        if (!document.getElementById('dt-history')) return;
        if (dtTable) { dtTable.destroy(); dtTable = null; }

        dtTable = $('#dt-history').DataTable({
            processing: true,
            serverSide: true,
            ajax: dtUrl,
            columns: [
                { data: 'payment_number' },
                { data: 'plan' },
                { data: 'payment_channel' },
                { data: 'total_amount' },
                { data: 'status', orderable: false },
                { data: 'created_at' },
            ],
            language: { url: false, emptyTable: 'Belum ada riwayat pembayaran.', processing: 'Memuat...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_-_END_ dari _TOTAL_', paginate: { next: 'Berikutnya', previous: 'Sebelumnya' } },
            pageLength: 20,
            order: [],
        });
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
