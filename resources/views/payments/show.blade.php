@extends('layouts.admin')

@section('title', 'Detail Pembayaran')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <h3 class="card-title mb-0">
                    @if($payment->status === 'paid')
                        <i class="fas fa-check-circle mr-2"></i>Pembayaran Berhasil
                    @elseif($payment->status === 'expired')
                        <i class="fas fa-times-circle mr-2"></i>Pembayaran Kedaluwarsa
                    @else
                        <i class="fas fa-clock mr-2"></i>Menunggu Pembayaran
                    @endif
                </h3>
            </div>
            <div class="card-body text-center">

                @if($payment->qr_string)
                    <div class="mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($payment->qr_string) }}"
                             alt="QR Code" class="img-fluid" style="max-width: 250px;">
                    </div>
                    <p class="text-muted small">Scan QR Code dengan aplikasi e-wallet / mobile banking</p>
                @elseif($payment->qr_url && $payment->qr_url !== 'qr')
                    <div class="mb-3">
                        <img src="{{ $payment->qr_url }}" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                    </div>
                    <p class="text-muted small">Scan QR Code dengan aplikasi pembayaran Anda</p>
                @endif

                @if($payment->pay_code)
                    <div class="mb-3">
                        <label class="text-muted small">Kode Pembayaran / Virtual Account</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg text-center font-weight-bold"
                                   value="{{ $payment->pay_code }}" id="payCode" readonly>
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

                @php
                    $instructions = $payment->payment_instructions;
                    if (is_string($instructions)) {
                        $instructions = json_decode($instructions, true);
                    }
                    $instructionList = is_array($instructions) ? ($instructions['instructions'] ?? $instructions) : [];
                @endphp
                @if(count($instructionList) > 0)
                <hr>
                <div class="text-left">
                    <h6>Instruksi Pembayaran:</h6>
                    <ol class="pl-3">
                        @foreach($instructionList as $instruction)
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
                    @if($payment->status === 'pending')
                    <button class="btn btn-success" onclick="checkStatus()">
                        <i class="fas fa-sync-alt"></i> Cek Status
                    </button>
                    @endif
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
                    @if($payment->invoice)
                    <tr>
                        <td class="text-muted">No. Invoice</td>
                        <td class="text-right">{{ $payment->invoice->invoice_number }}</td>
                    </tr>
                    @endif
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
    window.AppAjax.showToast('Kode berhasil disalin: ' + copyText.value, 'success');
}

function checkStatus() {
    fetch('{{ route("payments.check-status", $payment) }}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                window.AppAjax.showToast('Pembayaran berhasil!', 'success');
                setTimeout(() => { window.location.href = '{{ route("invoices.index") }}'; }, 1500);
            } else {
                window.AppAjax.showToast('Status: ' + data.status, 'warning');
            }
        });
}

@if($payment->status === 'pending')
setInterval(checkStatus, 30000);
@endif
</script>
@endpush
@endsection
