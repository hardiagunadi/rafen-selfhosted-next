@extends('layouts.admin')

@section('title', 'Pembayaran Langganan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pilih Metode Pembayaran</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Detail Langganan</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Paket</td>
                                <td><strong>{{ $subscription->plan->name }}</strong></td>
                            </tr>
                            <tr>
                                <td>Durasi</td>
                                <td>{{ $subscription->start_date->diffInDays($subscription->end_date) }} hari</td>
                            </tr>
                            <tr>
                                <td>Mulai</td>
                                <td>{{ $subscription->start_date->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td>Berakhir</td>
                                <td>{{ $subscription->end_date->format('d M Y') }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Pembayaran</h6>
                                <h2 class="text-primary mb-0">
                                    Rp {{ number_format($subscription->amount_paid, 0, ',', '.') }}
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <form action="{{ route('subscription.process-payment', $subscription) }}" method="POST">
                    @csrf
                    <input type="hidden" name="gateway_code" id="selected_gateway_code" value="">
                    <h5 class="mb-3">Pilih Metode Pembayaran</h5>

                    @if(count($channels) > 0)
                        <div class="row">
                            @foreach($channels as $channel)
                            @php $cardId = $channel['gateway_code'] . '_' . $channel['code']; @endphp
                            <div class="col-md-4 col-6 mb-3">
                                <div class="card h-100 payment-channel-card" style="cursor: pointer;"
                                    onclick="selectChannel('{{ $channel['code'] }}', '{{ $channel['gateway_code'] }}', '{{ $cardId }}')">
                                    <div class="card-body text-center p-3">
                                        <input type="radio" name="payment_channel" value="{{ $channel['code'] }}" id="channel_{{ $cardId }}" class="d-none">
                                        @if(isset($channel['icon_url']))
                                        <img src="{{ $channel['icon_url'] }}" alt="{{ $channel['name'] }}" class="mb-2" style="height: 40px; max-width: 100%;">
                                        @else
                                        <i class="fas fa-credit-card fa-2x mb-2 text-primary"></i>
                                        @endif
                                        <p class="mb-0 small">{{ $channel['name'] }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="payBtn" disabled>
                                <i class="fas fa-lock"></i> Bayar Sekarang
                            </button>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Tidak ada metode pembayaran tersedia. Silakan hubungi administrator.
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.payment-channel-card {
    transition: all 0.2s;
    border: 2px solid transparent;
}
.payment-channel-card:hover {
    border-color: #007bff;
}
.payment-channel-card.selected {
    border-color: #007bff;
    background-color: #f0f7ff;
}
</style>

@push('scripts')
<script>
function selectChannel(code, gatewayCode, cardId) {
    document.querySelectorAll('.payment-channel-card').forEach(card => card.classList.remove('selected'));
    document.getElementById('channel_' + cardId).checked = true;
    document.getElementById('channel_' + cardId).closest('.payment-channel-card').classList.add('selected');
    document.getElementById('selected_gateway_code').value = gatewayCode;
    document.getElementById('payBtn').disabled = false;
}
</script>
@endpush
@endsection
