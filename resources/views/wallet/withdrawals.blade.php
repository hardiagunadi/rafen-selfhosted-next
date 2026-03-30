@extends('layouts.admin')

@section('title', 'Riwayat Penarikan')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-history mr-2 text-primary"></i>Riwayat Penarikan</h4>
            <small class="text-muted">Daftar permintaan penarikan saldo wallet Anda</small>
        </div>
        <div class="col-auto">
            <a href="{{ route('wallet.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Wallet
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0" id="tableWithdrawals">
                <thead class="thead-light">
                    <tr>
                        <th>No. Permintaan</th>
                        <th class="text-right">Jumlah</th>
                        <th>Status</th>
                        <th>Info Bank</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Diproses</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#tableWithdrawals').DataTable({
    processing: true,
    serverSide: true,
    ajax: '{{ route("wallet.withdrawals.datatable") }}',
    order: [[4, 'desc']],
    columns: [
        { data: 'request_number' },
        { data: 'amount', className: 'text-right' },
        {
            data: 'status',
            render: function(val) {
                var map = {
                    pending:  '<span class="badge badge-warning">Menunggu</span>',
                    approved: '<span class="badge badge-info">Disetujui</span>',
                    rejected: '<span class="badge badge-danger">Ditolak</span>',
                    settled:  '<span class="badge badge-success">Selesai</span>',
                };
                return map[val] || val;
            }
        },
        { data: 'bank_info' },
        { data: 'created_at' },
        { data: 'processed_at' },
    ],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' },
});
</script>
@endpush
