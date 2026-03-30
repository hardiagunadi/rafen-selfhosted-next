@extends('layouts.admin')

@section('title', 'Permintaan Penarikan Saldo')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave mr-2 text-primary"></i>Permintaan Penarikan Saldo</h4>
            <small class="text-muted">Kelola permintaan penarikan saldo wallet tenant</small>
        </div>
        <div class="col-auto">
            <a href="{{ route('super-admin.wallets.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-wallet mr-1"></i> Lihat Saldo Tenant
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif

    {{-- Filter Status --}}
    <div class="mb-3">
        <div class="btn-group" id="statusFilter">
            <button type="button" class="btn btn-outline-secondary btn-filter active" data-status="">Semua</button>
            <button type="button" class="btn btn-outline-warning btn-filter" data-status="pending">Menunggu</button>
            <button type="button" class="btn btn-outline-info btn-filter" data-status="approved">Disetujui</button>
            <button type="button" class="btn btn-outline-success btn-filter" data-status="settled">Selesai</button>
            <button type="button" class="btn btn-outline-danger btn-filter" data-status="rejected">Ditolak</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0" id="tableWithdrawals">
                <thead class="thead-light">
                    <tr>
                        <th>No. Permintaan</th>
                        <th>Tenant</th>
                        <th class="text-right">Jumlah</th>
                        <th>Status</th>
                        <th>Info Bank</th>
                        <th>Tanggal</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Reject --}}
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-times-circle mr-1"></i> Tolak Penarikan</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Permintaan: <strong id="rejectRequestNo"></strong></p>
                <div class="form-group">
                    <label>Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="rejectionReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmReject">
                    <i class="fas fa-times-circle mr-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Settle --}}
<div class="modal fade" id="modalSettle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success"><i class="fas fa-check-circle mr-1"></i> Tandai Selesai (Settle)</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 px-3 small">
                    <i class="fas fa-info-circle mr-1"></i>
                    Pastikan transfer ke rekening tenant sudah dilakukan sebelum menandai selesai. Saldo wallet tenant akan didebit otomatis.
                </div>
                <p>Permintaan: <strong id="settleRequestNo"></strong></p>
                <div class="form-group">
                    <label>Bukti Transfer (opsional)</label>
                    <input type="file" id="transferProof" class="form-control-file" accept="image/*">
                    <small class="text-muted">Format: JPG, PNG, max 5MB</small>
                </div>
                <div class="form-group">
                    <label>Catatan Admin (opsional)</label>
                    <textarea id="adminNotes" class="form-control" rows="2" placeholder="cth: Transfer via BCA 10:30 12/03/2026"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btnConfirmSettle">
                    <i class="fas fa-check-circle mr-1"></i> Konfirmasi Selesai
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var currentWithdrawalId = null;
var statusFilter = '';

var table = $('#tableWithdrawals').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '{{ route("super-admin.withdrawals.datatable") }}',
        data: function(d) { d.status = statusFilter; }
    },
    order: [],
    columns: [
        { data: 'request_number' },
        { data: 'tenant' },
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
        { data: 'bank_info', render: $.fn.dataTable.render.text() },
        { data: 'created_at' },
        {
            data: 'actions',
            orderable: false,
            className: 'text-center',
            render: function(id, type, row) {
                var btns = '';
                if (row.status === 'pending') {
                    btns += '<button class="btn btn-sm btn-success btn-approve mr-1" data-id="' + id + '" data-no="' + row.request_number + '" title="Setujui"><i class="fas fa-check"></i></button>';
                    btns += '<button class="btn btn-sm btn-danger btn-reject" data-id="' + id + '" data-no="' + row.request_number + '" title="Tolak"><i class="fas fa-times"></i></button>';
                } else if (row.status === 'approved') {
                    btns += '<button class="btn btn-sm btn-primary btn-settle" data-id="' + id + '" data-no="' + row.request_number + '" title="Tandai Selesai"><i class="fas fa-check-double"></i> Settle</button>';
                }
                return btns || '-';
            }
        },
    ],
    rowCallback: function(row, data) {
        if (data.status === 'pending') $(row).addClass('table-warning');
    },
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' },
});

// Filter buttons
$('.btn-filter').on('click', function() {
    $('.btn-filter').removeClass('active');
    $(this).addClass('active');
    statusFilter = $(this).data('status');
    table.ajax.reload();
});

// Approve
$(document).on('click', '.btn-approve', function() {
    var id = $(this).data('id');
    var no = $(this).data('no');
    if (!confirm('Setujui permintaan penarikan ' + no + '?')) return;

    $.ajax({
        url: '{{ route("super-admin.withdrawals.approve", ":id") }}'.replace(':id', id),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function(res) {
            window.AppAjax.showToast(res.message, 'success');
            table.ajax.reload();
        },
        error: function(xhr) {
            window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal menyetujui.', 'danger');
        }
    });
});

// Reject Modal
$(document).on('click', '.btn-reject', function() {
    currentWithdrawalId = $(this).data('id');
    $('#rejectRequestNo').text($(this).data('no'));
    $('#rejectionReason').val('');
    $('#modalReject').modal('show');
});

$('#btnConfirmReject').on('click', function() {
    var reason = $('#rejectionReason').val().trim();
    if (!reason) {
        window.AppAjax.showToast('Alasan penolakan wajib diisi.', 'warning');
        return;
    }

    $.ajax({
        url: '{{ route("super-admin.withdrawals.reject", ":id") }}'.replace(':id', currentWithdrawalId),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', rejection_reason: reason },
        success: function(res) {
            $('#modalReject').modal('hide');
            window.AppAjax.showToast(res.message, 'success');
            table.ajax.reload();
        },
        error: function(xhr) {
            window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal menolak.', 'danger');
        }
    });
});

// Settle Modal
$(document).on('click', '.btn-settle', function() {
    currentWithdrawalId = $(this).data('id');
    $('#settleRequestNo').text($(this).data('no'));
    $('#transferProof').val('');
    $('#adminNotes').val('');
    $('#modalSettle').modal('show');
});

$('#btnConfirmSettle').on('click', function() {
    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('admin_notes', $('#adminNotes').val());
    if ($('#transferProof')[0].files.length) {
        formData.append('transfer_proof', $('#transferProof')[0].files[0]);
    }

    $.ajax({
        url: '{{ route("super-admin.withdrawals.settle", ":id") }}'.replace(':id', currentWithdrawalId),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#modalSettle').modal('hide');
            window.AppAjax.showToast(res.message, 'success');
            table.ajax.reload();
        },
        error: function(xhr) {
            window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal settle.', 'danger');
        }
    });
});
</script>
@endpush
