@extends('emails.layout')

@section('content')
<h2>Selamat Datang, {{ $tenant->name }}! 🎉</h2>
<p>Akun Anda di <strong>{{ config('app.name') }}</strong> telah berhasil dibuat. Berikut detail login Anda:</p>

<div class="info-box">
    <table>
        <tr>
            <td>Email Login</td>
            <td>{{ $tenant->email }}</td>
        </tr>
        <tr>
            <td>Password</td>
            <td><code style="background:#e9ecef;padding:2px 6px;border-radius:4px;">{{ $plainPassword }}</code></td>
        </tr>
        <tr>
            <td>Status</td>
            <td><span class="badge-warning">Trial Aktif ({{ $tenant->trial_days_remaining }} hari)</span></td>
        </tr>
        @if($tenant->subscription_expires_at)
        <tr>
            <td>Berlaku Hingga</td>
            <td>{{ \Carbon\Carbon::parse($tenant->subscription_expires_at)->format('d/m/Y') }}</td>
        </tr>
        @endif
    </table>
</div>

<p style="text-align:center;">
    <a href="{{ $loginUrl }}" class="btn">Masuk ke Dashboard</a>
</p>

<p style="font-size:13px;color:#e74c3c;"><strong>Penting:</strong> Segera ubah password Anda setelah login pertama kali.</p>

<hr>

@if($plans->isNotEmpty())
<h2>Paket Tersedia</h2>
<p style="color:#666;font-size:13px;">Setelah masa trial berakhir, pilih paket yang sesuai untuk melanjutkan layanan:</p>

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

<p style="text-align:center;margin-top:16px;">
    <a href="{{ config('app.url') }}/subscription/renew" class="btn">Pilih Paket Sekarang</a>
</p>
@endif
@endsection
