@extends('layouts.admin')

@section('title', 'Bantuan: Operasional Super Admin')

@section('content')
@php
    $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
@endphp
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h4 class="card-title mb-0"><i class="fas fa-crown mr-2"></i>{{ $isSelfHostedApp ? 'Administrasi Self-Hosted' : 'Operasional Super Admin Platform' }}</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-danger">
            {{ $isSelfHostedApp
                ? 'Halaman ini ditujukan untuk admin utama instance self-hosted. Fokusnya adalah lisensi, operasional server, kesehatan service, dan pengaturan global instance.'
                : 'Halaman ini ditujukan untuk Super Admin. Fokusnya adalah governance platform, operasional server, dan kontrol global multi-tenant.' }}
        </div>

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#tenant">{{ $isSelfHostedApp ? 'Lisensi & Akses Sistem' : 'Kelola Tenant' }}</a></li>
                <li><a href="#billing">{{ $isSelfHostedApp ? 'Konfigurasi Pembayaran & Bisnis' : 'Payment Gateway, Saldo, dan Withdrawal' }}</a></li>
                <li><a href="#ops">Server Health &amp; Terminal</a></li>
                <li><a href="#wa">{{ $isSelfHostedApp ? 'WA Gateway Sistem' : 'WA Platform Device' }}</a></li>
                <li><a href="#email">Pengaturan Email</a></li>
            </ol>
        </div>

        <h5 id="tenant" class="border-bottom pb-2"><i class="fas fa-building mr-1"></i>1. {{ $isSelfHostedApp ? 'Lisensi & Akses Sistem' : 'Kelola Tenant' }}</h5>
        <ul>
            @if($isSelfHostedApp)
            <li>Pastikan lisensi instance aktif, host/IP sesuai, dan grace period tetap terpantau.</li>
            <li>Kelola akun admin utama serta pastikan role operasional internal sudah tepat.</li>
            <li>Lakukan perubahan sensitif hanya setelah backup dan audit log terakhir diperiksa.</li>
            @else
            <li>Buat, edit, aktifkan, suspend, atau perpanjang tenant.</li>
            <li>Gunakan impersonate saat perlu memeriksa tenant dari sudut pandang operasional tenant.</li>
            <li>Pastikan penghapusan tenant dilakukan hanya setelah audit data aktif selesai.</li>
            @endif
        </ul>

        <h5 id="billing" class="border-bottom pb-2 mt-4"><i class="fas fa-credit-card mr-1"></i>2. {{ $isSelfHostedApp ? 'Konfigurasi Pembayaran & Bisnis' : 'Payment Gateway, Saldo, dan Withdrawal' }}</h5>
        <ul>
            @if($isSelfHostedApp)
            <li><strong>Payment Gateway</strong>: atur integrasi pembayaran milik instance sendiri, bukan gateway platform bersama.</li>
            <li><strong>Bank Account & Branding</strong>: pastikan rekening, logo, dan parameter bisnis sudah sesuai sebelum onboarding pelanggan.</li>
            <li><strong>Modul Sistem</strong>: aktifkan hanya modul yang benar-benar dipakai agar menu operasional tetap ringkas.</li>
            @else
            <li><strong>Payment Gateway</strong>: mengatur integrasi pembayaran global platform.</li>
            <li><strong>Saldo Tenant</strong>: memantau saldo wallet tenant pengguna Platform Gateway.</li>
            <li><strong>Penarikan Saldo</strong>: approve, reject, dan settle withdrawal tenant.</li>
            @endif
        </ul>

        <h5 id="ops" class="border-bottom pb-2 mt-4"><i class="fas fa-server mr-1"></i>3. Server Health &amp; Terminal</h5>
        <p><strong>Server Health</strong> dipakai untuk memantau layanan, resource, dan tindakan perawatan ringan. <strong>Terminal</strong> dipakai menjalankan command bantuan RAFEN yang sudah diizinkan untuk {{ $isSelfHostedApp ? 'operasi instance' : 'operasi platform' }} tanpa SSH langsung.</p>

        <h5 id="wa" class="border-bottom pb-2 mt-4"><i class="fab fa-whatsapp mr-1"></i>4. {{ $isSelfHostedApp ? 'WA Gateway Sistem' : 'WA Platform Device' }}</h5>
        <ul>
            @if($isSelfHostedApp)
            <li>Kelola device utama instance untuk invoice, tiket, reminder, dan notifikasi operasional lainnya.</li>
            <li>Pastikan ada device default yang sehat dan fallback device bila memakai multi-device blast.</li>
            <li>Uji template setelah perubahan besar agar notifikasi tidak gagal diam-diam.</li>
            @else
            <li>Kelola device global platform untuk notifikasi tingkat platform.</li>
            <li>Review dan approve request tenant yang ingin memakai device platform tertentu.</li>
            <li>Pastikan tenant hanya diberi akses pada device yang memang ditandai sebagai <strong>Platform</strong>.</li>
            @endif
        </ul>

        <h5 id="email" class="border-bottom pb-2 mt-4"><i class="fas fa-envelope mr-1"></i>5. Pengaturan Email</h5>
        <div class="alert alert-light border mb-0">
            Pengaturan email di area Super Admin bersifat global. Gunakan fitur test email setelah perubahan konfigurasi agar notifikasi {{ $isSelfHostedApp ? 'sistem' : 'platform' }} tetap terverifikasi.
        </div>
    </div>
</div>
@endsection
