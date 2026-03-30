<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Langganan — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .portal-card { max-width: 640px; margin: 2rem auto; }
        .brand-header { background: #343a40; color: #fff; border-radius: .5rem .5rem 0 0; padding: 1.25rem 1.5rem; }
        .brand-header .business-name { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .section-divider { border-top: 2px dashed #dee2e6; margin: 1.5rem 0; }

        /* Payment channel cards */
        .channel-label {
            transition: all .2s ease;
            border: 2px solid #e9ecef !important;
            border-radius: .5rem !important;
            padding: .75rem .5rem !important;
            background: #fff;
            min-height: 90px;
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .channel-label:hover {
            border-color: #4a90d9 !important;
            box-shadow: 0 3px 10px rgba(0,123,255,.15);
            transform: translateY(-1px);
        }
        .channel-label.selected {
            border-color: #007bff !important;
            background: #eaf3ff !important;
            box-shadow: 0 0 0 3px rgba(0,123,255,.2);
        }
        .channel-label.selected .channel-name {
            color: #0056b3;
            font-weight: 600;
        }
        .channel-label img {
            height: 36px;
            object-fit: contain;
        }
        .channel-name {
            font-size: .78rem;
            color: #495057;
            text-align: center;
            line-height: 1.2;
        }
        .channel-col { padding: .3rem; }
    </style>
</head>
<body>

<div class="portal-card">

    {{-- Brand Header --}}
    <div class="brand-header d-flex align-items-center">
        <i class="fas fa-server fa-2x mr-3 text-info"></i>
        <div>
            <p class="business-name">{{ config('app.name') }}</p>
            <p style="font-size:.85rem;opacity:.75;margin:0;">Tagihan Langganan</p>
        </div>
    </div>

    <div class="card shadow-sm" style="border-radius: 0 0 .5rem .5rem; border-top: none;">
        <div class="card-body">

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
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Subscription Info --}}
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="font-weight-bold mb-1">{{ $subscription->plan->name ?? '-' }}</h5>
                    <span class="text-muted small">{{ $subscription->user->name }}</span>
                </div>
                <span class="badge badge-warning" style="font-size:1rem;padding:.5rem 1rem;color:#212529;">
                    <i class="fas fa-clock mr-1"></i>MENUNGGU BAYAR
                </span>
            </div>

            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width:40%">Paket</td>
                    <td class="font-weight-bold">{{ $subscription->plan->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Mulai</td>
                    <td>{{ $subscription->start_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Berakhir</td>
                    <td>{{ $subscription->end_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Total</td>
                    <td class="font-weight-bold text-success" style="font-size:1.1rem">
                        Rp {{ number_format($subscription->amount_paid, 0, ',', '.') }}
                    </td>
                </tr>
            </table>

            <div class="section-divider"></div>
            <h6 class="font-weight-bold mb-3"><i class="fas fa-credit-card mr-2 text-primary"></i>Pilih Metode Pembayaran</h6>

            @if(count($channels) > 0)
                <form action="{{ route('subscription.payment.public.process', $token) }}" method="POST">
                    @csrf
                    <input type="hidden" name="gateway_code" id="selected_gateway_code" value="">
                    <div class="row no-gutters" style="margin: 0 -.3rem;">
                        @foreach($channels as $channel)
                        @php $cardId = ($channel['gateway_code'] ?? 'gw') . '_' . $channel['code']; @endphp
                        <div class="col-4 col-md-3 channel-col">
                            <label class="channel-label" style="cursor:pointer;" id="label_{{ $cardId }}">
                                <input type="radio" name="payment_channel" value="{{ $channel['code'] }}"
                                    id="channel_{{ $cardId }}" class="d-none channel-radio" required
                                    data-gateway="{{ $channel['gateway_code'] ?? '' }}">
                                @if(!empty($channel['icon_url']))
                                    <img src="{{ $channel['icon_url'] }}" alt="{{ $channel['name'] }}">
                                @else
                                    <i class="fas fa-credit-card fa-2x text-primary"></i>
                                @endif
                                <span class="channel-name">{{ $channel['name'] }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg mt-4" id="payBtn" disabled
                        style="font-size:1rem;padding:.75rem;border-radius:.5rem;letter-spacing:.03em;">
                        <i class="fas fa-lock mr-2"></i>Bayar Sekarang
                    </button>
                </form>
            @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Tidak ada metode pembayaran tersedia. Silakan hubungi administrator.
                </div>
            @endif

            <div class="section-divider"></div>
            <div class="text-center text-muted small">
                <strong>{{ config('app.name') }}</strong>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.channel-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.channel-label').forEach(function (lbl) {
            lbl.classList.remove('selected');
        });
        if (this.checked) {
            this.closest('.channel-label').classList.add('selected');
            document.getElementById('selected_gateway_code').value = this.dataset.gateway;
            document.getElementById('payBtn').disabled = false;
        }
    });
});
</script>
</body>
</html>
