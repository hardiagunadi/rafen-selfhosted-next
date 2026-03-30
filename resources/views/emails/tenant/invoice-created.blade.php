@extends('emails.layout')

@section('content')
<h2>Tagihan Langganan</h2>
<p>Halo, <strong>{{ $tenant->name }}</strong> — tagihan langganan berikut telah diterbitkan:</p>

<div class="info-box">
    <table>
        <tr>
            <td>Paket</td>
            <td>{{ $subscription->plan?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>
                {{ \Carbon\Carbon::parse($subscription->start_date)->format('d/m/Y') }}
                &mdash;
                {{ \Carbon\Carbon::parse($subscription->end_date)->format('d/m/Y') }}
            </td>
        </tr>
        <tr>
            <td>Total</td>
            <td><strong>Rp {{ number_format($subscription->amount_paid, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td>Status</td>
            <td><span class="badge-warning">Menunggu Pembayaran</span></td>
        </tr>
    </table>
</div>

<p style="text-align:center;">
    <a href="{{ $paymentUrl }}" class="btn">Bayar Sekarang</a>
</p>

<p style="font-size:13px;color:#666;">Setelah pembayaran berhasil, langganan akan aktif otomatis dan Anda akan menerima konfirmasi via email. Terima kasih.</p>
@endsection
