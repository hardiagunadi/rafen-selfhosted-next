@extends('layouts.admin')

@section('title', 'Detail Pembayaran')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <h3 class="card-title mb-0">Menunggu Pembayaran</h3>
            </div>
            <div class="card-body text-center">
                @if($payment->qr_url)
                    <div class="mb-3">
                        <img src="{{ $payment->qr_url }}" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                    </div>
                    <p class="text-muted">Scan QR Code dengan aplikasi pembayaran Anda</p>
                @endif

                @if($payment->pay_code)
                    <div class="mb-3">
                        <label class="text-muted small">Kode Pembayaran / Virtual Account</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg text-center font-weight-bold" value="{{ $payment->pay_code }}" id="payCode" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-primary" onclick="copyPayCode()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <hr>

                <div class="row text-left">
                    <div class="col-6">
                        <p class="mb-1 text-muted small">Total Pembayaran</p>
                        <h4 class="text-primary">Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</h4>
                    </div>
                    <div class="col-6 text-right">
                        <p class="mb-1 text-muted small">Batas Waktu</p>
                        <h5 class="text-danger">
                            {{ $payment->expired_at ? $payment->expired_at->format('d M Y H:i') : '-' }}
                        </h5>
                    </div>
                </div>

                @if($payment->payment_instructions)
                <hr>
                <div class="text-left">
                    <h6>Instruksi Pembayaran:</h6>
                    <ol class="pl-3">
                        @foreach($payment->payment_instructions as $instruction)
                            @if(is_array($instruction))
                                @foreach($instruction['steps'] ?? [] as $step)
                                    <li class="mb-1">{{ $step }}</li>
                                @endforeach
                            @else
                                <li class="mb-1">{{ $instruction }}</li>
                            @endif
                        @endforeach
                    </ol>
                </div>
                @endif

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('invoices.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button class="btn btn-success" onclick="checkStatus()">
                        <i class="fas fa-sync-alt"></i> Cek Status
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6>Detail Transaksi</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted">No. Pembayaran</td>
                        <td class="text-right">{{ $payment->payment_number }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">No. Invoice</td>
                        <td class="text-right">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Metode</td>
                        <td class="text-right">{{ $payment->payment_channel }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Subtotal</td>
                        <td class="text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Biaya Admin</td>
                        <td class="text-right">Rp {{ number_format($payment->fee, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Total</strong></td>
                        <td class="text-right"><strong>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyPayCode() {
    var copyText = document.getElementById("payCode");
    copyText.select();
    document.execCommand("copy");
    alert("Kode berhasil disalin: " + copyText.value);
}

function checkStatus() {
    fetch('{{ route("payments.check-status", $payment) }}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                alert('Pembayaran berhasil!');
                window.location.href = '{{ route("invoices.index") }}';
            } else {
                alert('Status: ' + data.status);
            }
        });
}

// Auto check status every 30 seconds
setInterval(checkStatus, 30000);
</script>
@endpush
@endsection
