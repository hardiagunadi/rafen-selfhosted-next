@extends('layouts.admin')

@section('title', 'Konfirmasi Pembayaran Manual')

@section('content_header')
    <h1>Konfirmasi Pembayaran Manual</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock mr-2"></i>Bukti Transfer Menunggu Konfirmasi</h3>
        <div class="card-tools">
            <span class="badge badge-warning">{{ $payments->count() }} menunggu</span>
        </div>
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <p class="mb-0">Tidak ada pembayaran yang menunggu konfirmasi.</p>
            </div>
        @else
        <table class="table table-bordered table-hover mb-0">
            <thead>
                <tr>
                    <th>Invoice / Payment</th>
                    <th>Pelanggan</th>
                    <th>Tagihan</th>
                    <th>Transfer</th>
                    <th>Tgl Upload</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                <tr>
                    <td>
                        @if($p->invoice_url)
                            <a href="{{ $p->invoice_url }}" class="font-weight-bold text-primary">{{ $p->invoice_number }}</a>
                        @else
                            <span class="font-weight-bold">{{ $p->invoice_number }}</span>
                        @endif
                        <br><small class="text-muted">{{ $p->payment_number }}</small>
                    </td>
                    <td>
                        <div>{{ $p->customer_name }}</div>
                        <small class="text-muted">{{ $p->customer_id }}</small>
                    </td>
                    <td>Rp {{ $p->amount }}</td>
                    <td>
                        <div>Rp {{ $p->amount_transferred }}</div>
                        <small class="text-muted">{{ $p->transfer_date }}</small>
                    </td>
                    <td>{{ $p->uploaded_at }}</td>
                    <td>
                        <button class="btn btn-sm btn-primary btn-view"
                            data-id="{{ $p->id }}"
                            data-invoice="{{ $p->invoice_number }}"
                            data-payment="{{ $p->payment_number }}"
                            data-customer="{{ $p->customer_name }} ({{ $p->customer_id }})"
                            data-amount="Rp {{ $p->amount }}"
                            data-transferred="Rp {{ $p->amount_transferred }}"
                            data-date="{{ $p->transfer_date }}"
                            data-notes="{{ $p->catatan }}"
                            data-proof="{{ $p->proof_url }}"
                            data-confirm="{{ $p->confirm_url }}"
                            data-reject="{{ $p->reject_url }}">
                            <i class="fas fa-search mr-1"></i>Lihat & Konfirmasi
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Modal Lihat & Konfirmasi --}}
<div class="modal fade" id="modal-confirm" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle text-success mr-2"></i>Konfirmasi Pembayaran</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Invoice</th><td id="c-invoice">-</td></tr>
                            <tr><th class="text-muted">Payment #</th><td id="c-payment">-</td></tr>
                            <tr><th class="text-muted">Pelanggan</th><td id="c-customer">-</td></tr>
                            <tr><th class="text-muted">Tagihan</th><td id="c-amount">-</td></tr>
                            <tr><th class="text-muted">Jml Transfer</th><td id="c-transferred">-</td></tr>
                            <tr><th class="text-muted">Tgl Transfer</th><td id="c-date">-</td></tr>
                            <tr><th class="text-muted">Catatan</th><td id="c-notes" class="text-muted small">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-center">
                        <div id="proof-wrapper">
                            <img id="proof-img" src="" alt="Bukti Transfer" class="img-fluid rounded border"
                                style="max-height:260px; cursor:zoom-in; display:none;"
                                title="Klik untuk perbesar">
                            <div id="proof-empty" class="text-muted mt-3" style="display:none">
                                <i class="fas fa-image fa-3x mb-2"></i><br>Tidak ada bukti gambar
                            </div>
                        </div>
                        <div class="mt-2" id="proof-actions" style="display:none">
                            <a id="proof-link" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-external-link-alt mr-1"></i>Buka Original
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-danger" id="btn-open-reject">
                    <i class="fas fa-times mr-1"></i>Tolak
                </button>
                <div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success ml-2" id="btn-confirm-pay">
                        <i class="fas fa-check mr-1"></i>Konfirmasi Bayar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Tolak --}}
<div class="modal fade" id="modal-reject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Tolak Pembayaran</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Masukkan alasan penolakan.</p>
                <div class="form-group mb-0">
                    <label>Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="rejection-reason" class="form-control" rows="3" maxlength="500" placeholder="mis. Nominal tidak sesuai, bukti tidak jelas..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-back-to-confirm">Kembali</button>
                <button type="button" class="btn btn-danger" id="btn-do-reject">
                    <i class="fas fa-times mr-1"></i>Tolak Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var confirmUrl = null;
    var rejectUrl  = null;
    var activeRow  = null;

    function removeRow() {
        if (activeRow) {
            activeRow.remove();
            activeRow = null;
        }
        // Update badge counter
        var tbody = document.querySelector('table tbody');
        var badge = document.querySelector('.badge-warning');
        if (badge) {
            var count = tbody ? tbody.querySelectorAll('tr').length : 0;
            badge.textContent = count + ' menunggu';
        }
        // Tampilkan pesan kosong jika tidak ada baris
        if (tbody && tbody.querySelectorAll('tr').length === 0) {
            var card = document.querySelector('.card-body');
            card.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 text-success"></i><p class="mb-0">Tidak ada pembayaran yang menunggu konfirmasi.</p></div>';
        }
    }

    // Buka modal konfirmasi
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-view');
        if (!btn) return;

        activeRow  = btn.closest('tr');
        confirmUrl = btn.dataset.confirm;
        rejectUrl  = btn.dataset.reject;

        document.getElementById('c-invoice').textContent     = btn.dataset.invoice;
        document.getElementById('c-payment').textContent     = btn.dataset.payment;
        document.getElementById('c-customer').textContent    = btn.dataset.customer;
        document.getElementById('c-amount').textContent      = btn.dataset.amount;
        document.getElementById('c-transferred').textContent = btn.dataset.transferred;
        document.getElementById('c-date').textContent        = btn.dataset.date;
        document.getElementById('c-notes').textContent       = btn.dataset.notes || '-';

        var img   = document.getElementById('proof-img');
        var link  = document.getElementById('proof-link');
        var empty = document.getElementById('proof-empty');
        var proof = btn.dataset.proof;

        if (proof) {
            img.src  = proof;
            link.href = proof;
            img.style.display   = '';
            empty.style.display = 'none';
            document.getElementById('proof-actions').style.display = '';
        } else {
            img.src = '';
            img.style.display   = 'none';
            empty.style.display = '';
            document.getElementById('proof-actions').style.display = 'none';
        }

        document.getElementById('rejection-reason').value = '';
        $('#modal-confirm').modal('show');
    });

    // Lightbox
    document.getElementById('proof-img').addEventListener('click', function () {
        if (!this.src || this.style.display === 'none') return;
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
        var imgEl = document.createElement('img');
        imgEl.src = this.src;
        imgEl.style.cssText = 'max-width:95vw;max-height:95vh;border-radius:4px;box-shadow:0 4px 32px rgba(0,0,0,.6);';
        overlay.appendChild(imgEl);
        overlay.addEventListener('click', function () { document.body.removeChild(overlay); });
        document.body.appendChild(overlay);
    });

    // Konfirmasi bayar
    document.getElementById('btn-confirm-pay').addEventListener('click', function () {
        if (!confirmUrl) return;
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';

        fetch(confirmUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Konfirmasi Bayar';
            $('#modal-confirm').modal('hide');
            if (res.ok) {
                window.AppAjax.showToast(res.data.message || 'Pembayaran dikonfirmasi.', 'success');
                removeRow();
            } else {
                window.AppAjax.showToast(res.data.message || 'Gagal mengkonfirmasi.', 'danger');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Konfirmasi Bayar';
            window.AppAjax.showToast('Terjadi kesalahan. Coba lagi.', 'danger');
        });
    });

    // Buka modal tolak
    document.getElementById('btn-open-reject').addEventListener('click', function () {
        $('#modal-confirm').modal('hide');
        $('#modal-reject').modal('show');
    });

    // Kembali ke modal konfirmasi
    document.getElementById('btn-back-to-confirm').addEventListener('click', function () {
        $('#modal-reject').modal('hide');
        $('#modal-confirm').modal('show');
    });

    // Tolak pembayaran
    document.getElementById('btn-do-reject').addEventListener('click', function () {
        var reason = document.getElementById('rejection-reason').value.trim();
        if (!reason) { window.AppAjax.showToast('Masukkan alasan penolakan.', 'warning'); return; }
        if (!rejectUrl) return;

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';

        var body = new FormData();
        body.append('_token', '{{ csrf_token() }}');
        body.append('rejection_reason', reason);

        fetch(rejectUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Tolak Pembayaran';
            $('#modal-reject').modal('hide');
            if (res.ok) {
                window.AppAjax.showToast(res.data.message || 'Pembayaran ditolak.', 'success');
                removeRow();
            } else {
                window.AppAjax.showToast(res.data.message || 'Gagal menolak.', 'danger');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Tolak Pembayaran';
            window.AppAjax.showToast('Terjadi kesalahan. Coba lagi.', 'danger');
        });
    });
}());
</script>
@endpush

