@extends('emails.layout')

@section('content')
<span class="badge-danger" style="font-size:14px;">Akun Dihapus</span>

<h2 style="margin-top:14px;">Halo, {{ $tenantName }}</h2>

<p>Akun trial Anda (<strong>{{ $tenantEmail }}</strong>) di <strong>{{ config('app.name') }}</strong> telah dihapus secara otomatis karena masa trial sudah berakhir lebih dari 7 hari dan belum ada paket berlangganan yang aktif.</p>

<p>Semua data yang terkait dengan akun Anda (pelanggan, konfigurasi router, invoice, dll.) telah dihapus dari sistem kami.</p>

<hr>

<p>Jika Anda ingin menggunakan layanan {{ config('app.name') }} kembali, Anda dapat mendaftar ulang sebagai akun baru:</p>

<p style="text-align:center;">
    <a href="{{ $registerUrl }}" class="btn">Daftar Akun Baru</a>
</p>

<p style="font-size:13px;color:#666;">Jika Anda merasa ini adalah kesalahan atau membutuhkan bantuan, silakan hubungi tim support kami melalui <a href="{{ $contactUrl }}" style="color:#0f6b95;">{{ $contactUrl }}</a>.</p>
@endsection
