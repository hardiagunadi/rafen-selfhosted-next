@extends('emails.layout')

@section('content')
<span class="badge-success" style="font-size:14px;">✓ Pembayaran Diterima</span>

<h2 style="margin-top:14px;">Halo, {{ $tenant->name }}</h2>
<p>Pembayaran Anda telah berhasil dikonfirmasi. Berikut detail langganan yang aktif:</p>

<div class="info-box">
    <table>
        <tr>
            <td>Paket</td>
            <td>{{ $subscription->plan?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Metode Pembayaran</td>
            <td>{{ $payment->payment_method ?? '-' }}</td>
        </tr>
        <tr>
            <td>Jumlah</td>
            <td><strong>Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td>Tanggal Bayar</td>
            <td>{{ \Carbon\Carbon::parse($payment->paid_at ?? $payment->created_at)->format('d/m/Y H:i') }}</td>
        </tr>
        @if($subscription->ends_at)
        <tr>
            <td>Aktif Hingga</td>
            <td><strong>{{ \Carbon\Carbon::parse($subscription->ends_at)->format('d/m/Y') }}</strong></td>
        </tr>
        @endif
    </table>
</div>

<p style="text-align:center;">
    <a href="{{ $dashboardUrl }}" class="btn">Buka Dashboard</a>
</p>

<p style="font-size:13px;color:#666;">Terima kasih atas kepercayaan Anda menggunakan <strong>{{ config('app.name') }}</strong>. Jika ada pertanyaan, silakan hubungi kami.</p>
@endsection
