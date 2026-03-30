@extends('layouts.admin')

@section('title', 'Invoice #' . $invoice->invoice_number)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Invoice</h3>
                <div class="card-tools">
                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-sm btn-default mr-1">
                        <i class="fas fa-print"></i> Cetak
                    </a>
                    @if($invoice->isUnpaid())
                        <span class="badge badge-warning">Belum Dibayar</span>
                    @else
                        <span class="badge badge-success">Lunas</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">No. Invoice</td>
                                <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Pelanggan</td>
                                <td>{{ $invoice->customer_name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">ID Pelanggan</td>
                                <td>{{ $invoice->customer_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipe Service</td>
                                <td>{{ strtoupper($invoice->tipe_service) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Paket</td>
                                <td>{{ $invoice->paket_langganan ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Tanggal</td>
                                <td>{{ $invoice->created_at->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jatuh Tempo</td>
                                <td>
                                    {{ $invoice->due_date ? $invoice->due_date->format('d M Y') : '-' }}
                                    @if($invoice->isOverdue())
                                        <span class="badge badge-danger">Terlambat</span>
                                    @endif
                                </td>
                            </tr>
                            @if($invoice->isPaid())
                            <tr>
                                <td class="text-muted">Dibayar Pada</td>
                                <td>{{ $invoice->paid_at?->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Metode Pembayaran</td>
                                <td>{{ $invoice->payment_channel ?? $invoice->payment_method ?? '-' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <hr>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Deskripsi</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                {{ $invoice->paket_langganan ?? 'Layanan Internet' }}
                                @if($invoice->promo_applied)
                                    <span class="badge badge-warning ml-1">Promo</span>
                                @endif
                                @if($invoice->prorata_applied)
                                    <span class="badge badge-info ml-1">Prorata</span>
                                @endif
                                @if($invoice->prorata_applied && $invoice->harga_asli > 0 && $invoice->harga_asli != $invoice->harga_dasar)
                                    <br><small class="text-muted">Harga normal: Rp {{ number_format($invoice->harga_asli, 0, ',', '.') }} → dihitung prorata sisa hari</small>
                                @endif
                            </td>
                            <td class="text-right">Rp {{ number_format($invoice->harga_dasar, 0, ',', '.') }}</td>
                        </tr>
                        @if($invoice->ppn_amount > 0)
                        <tr>
                            <td>PPN ({{ $invoice->ppn_percent }}%)</td>
                            <td class="text-right">Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td class="text-right">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        @if($invoice->isUnpaid())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pembayaran</h3>
            </div>
            <div class="card-body">
                @if(isset($pendingPayment) && $pendingPayment)
                    {{-- Ada bukti transfer menunggu konfirmasi --}}
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-clock mr-2"></i>
                        <strong>Menunggu Konfirmasi Admin</strong><br>
                        <small class="text-muted">Bukti transfer sudah dikirim pelanggan pada {{ $pendingPayment->created_at->format('d/m/Y H:i') }}.</small>
                    </div>

                    {{-- Tampilkan detail & bukti transfer --}}
                    @php
                        // Prioritas: kolom dedicated, fallback ke parsing notes (data lama)
                        $proofPath = $pendingPayment->payment_proof;
                        $amountTransferred = $pendingPayment->amount_transferred;
                        $transferDate = $pendingPayment->transfer_date;
                        $catatanBukti = $pendingPayment->notes;

                        if (!$proofPath && $pendingPayment->notes) {
                            foreach (explode("\n", $pendingPayment->notes) as $line) {
                                if (str_starts_with($line, 'Bukti transfer: ')) $proofPath = trim(substr($line, 16));
                                elseif (str_starts_with($line, 'Jumlah: ')) $amountTransferred = trim(substr($line, 8));
                                elseif (str_starts_with($line, 'Tanggal: ')) $transferDate = trim(substr($line, 9));
                                elseif (str_starts_with($line, 'Catatan: ')) $catatanBukti = trim(substr($line, 9));
                            }
                        }
                        $proofUrl = $proofPath ? asset('storage/' . $proofPath) : null;
                    @endphp

                    <table class="table table-sm table-borderless mb-2">
                        @if($amountTransferred)
                        <tr>
                            <td class="text-muted small" width="40%">Jml Transfer</td>
                            <td class="small font-weight-bold">Rp {{ number_format((float)$amountTransferred, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($transferDate)
                        <tr>
                            <td class="text-muted small">Tgl Transfer</td>
                            <td class="small">{{ $transferDate }}</td>
                        </tr>
                        @endif
                        @if($catatanBukti)
                        <tr>
                            <td class="text-muted small">Catatan</td>
                            <td class="small">{{ $catatanBukti }}</td>
                        </tr>
                        @endif
                    </table>

                    @if($proofUrl)
                        <div class="text-center mb-2">
                            <a href="{{ $proofUrl }}" target="_blank">
                                <img src="{{ $proofUrl }}" alt="Bukti Transfer"
                                    class="img-fluid rounded border" style="max-height:180px; cursor:zoom-in;"
                                    title="Klik untuk perbesar">
                            </a>
                            <div class="mt-1">
                                <a href="{{ $proofUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-external-link-alt mr-1"></i>Buka Gambar
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted small mb-2 py-2 border rounded bg-light">
                            <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                            Bukti transfer tidak tersedia atau gagal diupload.
                        </div>
                    @endif

                    @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() || auth()->user()->role === 'keuangan')
                    <a href="{{ route('payments.pending') }}" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-check-double mr-1"></i> Konfirmasi Bukti Transfer
                    </a>
                    @endif
                    @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-block btn-sm" onclick="return confirm('Konfirmasi bayar langsung tanpa verifikasi bukti?')">
                            <i class="fas fa-check"></i> Konfirmasi Langsung
                        </button>
                    </form>
                    @endif
                @else
                    @if($settings && $settings->hasPaymentGateway())
                        <a href="{{ route('payments.create-for-invoice', $invoice) }}" class="btn btn-primary btn-block mb-2">
                            <i class="fas fa-credit-card"></i> Bayar Online (QRIS/VA)
                        </a>
                    @endif

                    @if($settings && $settings->enable_manual_payment && $bankAccounts->count() > 0)
                        <a href="{{ route('payments.manual-form', $invoice) }}" class="btn btn-outline-success btn-block mb-2">
                            <i class="fas fa-university"></i> Upload Bukti Transfer Bank
                        </a>
                    @endif

                    @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Konfirmasi pembayaran manual?')">
                            <i class="fas fa-check"></i> Konfirmasi Bayar (Admin)
                        </button>
                    </form>
                    @endif
                @endif

                @if((auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || auth()->user()->role === 'keuangan') && $settings && $settings->hasWaConfigured())
                <hr class="my-2">
                <button type="button" class="btn btn-outline-info btn-block btn-sm btn-send-wa">
                    <i class="fab fa-whatsapp"></i> Kirim Tagihan ke WA
                </button>
                @endif
            </div>
        </div>

        @if($settings && $settings->enable_manual_payment && $bankAccounts->count() > 0)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-university mr-1"></i> Rekening Transfer</h3>
            </div>
            <div class="card-body p-0">
                @foreach($bankAccounts as $bank)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <strong>{{ $bank->bank_name }}</strong>
                    @if($bank->is_primary)
                        <span class="badge badge-primary">Utama</span>
                    @endif
                    <br>
                    <span class="font-weight-bold">{{ $bank->account_number }}</span><br>
                    <small class="text-muted">a.n. {{ $bank->account_name }}</small>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @else
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Invoice Lunas</h3>
            </div>
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p>Invoice ini sudah dibayar.</p>
                @if($invoice->payment_reference)
                <p class="text-muted small">Ref: {{ $invoice->payment_reference }}</p>
                @endif
                @if((auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || auth()->user()->role === 'keuangan') && $settings && $settings->hasWaConfigured())
                <button type="button" class="btn btn-outline-success btn-sm mt-1 btn-send-wa">
                    <i class="fab fa-whatsapp"></i> Kirim Notifikasi Lunas ke WA
                </button>
                @endif
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Invoice
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-send-wa').forEach(function(btn) {
    var originalHtml = btn.innerHTML;
    btn.addEventListener('click', function() {
        var self = this;
        self.disabled = true;
        self.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

        fetch('{{ route('invoices.send-wa', $invoice) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status) {
                window.AppAjax.showToast(data.status, 'success');
            } else {
                window.AppAjax.showToast(data.error || 'Gagal mengirim WA.', 'danger');
            }
        })
        .catch(function() {
            window.AppAjax.showToast('Terjadi kesalahan saat mengirim.', 'danger');
        })
        .finally(function() {
            self.disabled = false;
            self.innerHTML = originalHtml;
        });
    });
});
</script>
@endpush
