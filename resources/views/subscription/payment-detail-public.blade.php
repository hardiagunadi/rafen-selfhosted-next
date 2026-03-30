<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembayaran — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .portal-card { max-width: 540px; margin: 2rem auto; }
        .brand-header { background: #343a40; color: #fff; border-radius: .5rem .5rem 0 0; padding: 1.25rem 1.5rem; }
        .brand-header .business-name { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .section-divider { border-top: 2px dashed #dee2e6; margin: 1.5rem 0; }
    </style>
</head>
<body>

<div class="portal-card">
    <div class="brand-header d-flex align-items-center">
        <i class="fas fa-server fa-2x mr-3 text-info"></i>
        <div>
            <p class="business-name">{{ config('app.name') }}</p>
            <p style="font-size:.85rem;opacity:.75;margin:0;">Menunggu Pembayaran</p>
        </div>
    </div>

    <div class="card shadow-sm" style="border-radius: 0 0 .5rem .5rem; border-top: none;">
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
                <p class="text-muted">Scan QR Code dengan aplikasi pembayaran Anda</p>
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
                    <h6 class="text-danger" id="expiry">
                        {{ $payment->expired_at ? $payment->expired_at->format('d M Y H:i') : '-' }}
                    </h6>
                </div>
            </div>

            @php
                $instructions = is_string($payment->payment_instructions)
                    ? json_decode($payment->payment_instructions, true)
                    : $payment->payment_instructions;
            @endphp
            @if(is_array($instructions) && isset($instructions['instructions']))
            <hr>
            <div class="text-left">
                <h6>Instruksi Pembayaran:</h6>
                <ol class="pl-3">
                    @foreach($instructions['instructions'] as $instruction)
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

            <button class="btn btn-success btn-block" onclick="checkStatus()">
                <i class="fas fa-sync-alt mr-2"></i>Cek Status Pembayaran
            </button>

        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <h6>Detail Transaksi</h6>
            <table class="table table-sm">
                <tr>
                    <td class="text-muted">Paket</td>
                    <td class="text-right">{{ $subscription->plan->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">No. Pembayaran</td>
                    <td class="text-right">{{ $payment->payment_number }}</td>
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

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyPayCode() {
    var copyText = document.getElementById("payCode");
    copyText.select();
    document.execCommand("copy");
    alert("Kode berhasil disalin: " + copyText.value);
}

function checkStatus() {
    fetch('{{ route("subscription.payment.public.check-status", ["token" => $token, "payment" => $payment->id]) }}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                alert('Pembayaran berhasil! Halaman akan di-refresh.');
                window.location.href = '{{ route("subscription.payment.public", $token) }}';
            } else {
                alert('Status: ' + data.status + '. Silakan tunggu konfirmasi.');
            }
        })
        .catch(function () {
            alert('Gagal memeriksa status. Coba lagi nanti.');
        });
}

// Auto check status every 30 seconds
setInterval(checkStatus, 30000);
</script>
</body>
</html>
