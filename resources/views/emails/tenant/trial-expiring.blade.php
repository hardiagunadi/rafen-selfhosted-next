@extends('emails.layout')

@section('content')
@if($daysLeft <= 0)
    <span class="badge-danger" style="font-size:14px;">⚠ Trial Anda Berakhir Hari Ini</span>
@elseif($daysLeft === 1)
    <span class="badge-warning" style="font-size:14px;">⚠ Trial Anda Berakhir Besok</span>
@else
    <span class="badge-warning" style="font-size:14px;">🔔 Trial Anda Berakhir dalam {{ $daysLeft }} Hari</span>
@endif

<h2 style="margin-top:14px;">Halo, {{ $tenant->name }}</h2>

@if($daysLeft <= 0)
<p>Masa trial akun Anda di <strong>{{ config('app.name') }}</strong> telah berakhir. Untuk terus menggunakan layanan, segera pilih paket berlangganan.</p>
<p style="color:#842029;">Jika tidak melakukan pembayaran, akun dan semua data Anda akan <strong>dihapus otomatis dalam 7 hari.</strong></p>
@else
<p>Masa trial akun Anda di <strong>{{ config('app.name') }}</strong> akan berakhir dalam <strong>{{ $daysLeft }} hari</strong>
@if($tenant->subscription_expires_at)
({{ \Carbon\Carbon::parse($tenant->subscription_expires_at)->format('d/m/Y') }})
@endif
.</p>
<p>Pastikan Anda sudah memilih paket berlangganan agar layanan tidak terganggu.</p>
@endif

<p style="text-align:center;">
    <a href="{{ $renewUrl }}" class="btn">Pilih Paket Sekarang</a>
</p>

<hr>

@if($plans->isNotEmpty())
<h2>Paket Tersedia</h2>

@foreach($plans as $plan)
<div class="plan-card">
    <h4>{{ $plan->name }}</h4>
    <div class="price">{{ $plan->formatted_price }}<span style="font-size:13px;font-weight:400;color:#666;"> / {{ $plan->duration_days }} hari</span></div>
    @if($plan->description)
    <div class="desc">{{ $plan->description }}</div>
    @endif
    @if($plan->features)
    <div class="desc" style="margin-top:6px;">
        @foreach($plan->features as $f)
        ✓ {{ $f }}&nbsp;&nbsp;
        @endforeach
    </div>
    @endif
    <div style="font-size:12px;color:#888;margin-top:4px;">
        MikroTik: {{ $plan->max_mikrotik == -1 ? 'Unlimited' : $plan->max_mikrotik }} &nbsp;|&nbsp;
        PPP Users: {{ $plan->max_ppp_users == -1 ? 'Unlimited' : $plan->max_ppp_users }} &nbsp;|&nbsp;
        VPN Peers: {{ $plan->max_vpn_peers == -1 ? 'Unlimited' : $plan->max_vpn_peers }}
    </div>
</div>
@endforeach
@endif
@endsection
