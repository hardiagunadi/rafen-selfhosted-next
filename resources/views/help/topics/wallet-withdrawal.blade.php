@extends('layouts.admin')

@section('title', 'Bantuan: Wallet & Withdrawal')

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
    <div class="card-header bg-secondary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-wallet mr-2"></i>{{ $isSelfHostedApp ? 'Catatan Kompatibilitas Wallet & Withdrawal' : 'Wallet & Withdrawal Tenant' }}</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            {{ $isSelfHostedApp
                ? 'Mode self-hosted normalnya tidak memakai wallet platform bersama. Jika halaman ini terbuka, anggap ini referensi kompatibilitas dari versi SaaS dan bukan alur operasional utama self-hosted.'
                : 'Fitur ini hanya tersedia untuk tenant yang menggunakan Platform Gateway dan umumnya tidak tampil pada akun sub-user.' }}
        </div>

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#akses">{{ $isSelfHostedApp ? 'Status di Mode Self-Hosted' : 'Kapan Menu Muncul' }}</a></li>
                <li><a href="#saldo">{{ $isSelfHostedApp ? 'Alternatif yang Dipakai' : 'Saldo & Riwayat Transaksi' }}</a></li>
                <li><a href="#tarik">{{ $isSelfHostedApp ? 'Kalau Ingin Menambahkan Fitur Ini' : 'Pengajuan Penarikan' }}</a></li>
                <li><a href="#status">{{ $isSelfHostedApp ? 'Status Kompatibilitas' : 'Status Withdrawal' }}</a></li>
            </ol>
        </div>

        <h5 id="akses" class="border-bottom pb-2"><i class="fas fa-eye mr-1"></i>1. {{ $isSelfHostedApp ? 'Status di Mode Self-Hosted' : 'Kapan Menu Muncul' }}</h5>
        <ul>
            @if($isSelfHostedApp)
            <li>Secara default fitur ini tidak aktif karena pembayaran dikelola langsung oleh instance Anda sendiri.</li>
            <li>Arus dana biasanya dipantau lewat invoice, payment confirmation, dan laporan keuangan.</li>
            <li>Jika benar-benar dibutuhkan, fitur serupa bisa ditambahkan sebagai modul kustom internal.</li>
            @else
            <li>Tenant harus memakai Platform Gateway.</li>
            <li>Akun yang sedang login bukan sub-user.</li>
            <li>Bank account untuk settlement sebaiknya sudah disiapkan di pengaturan tenant.</li>
            @endif
        </ul>

        <h5 id="saldo" class="border-bottom pb-2 mt-4"><i class="fas fa-coins mr-1"></i>2. {{ $isSelfHostedApp ? 'Alternatif yang Dipakai' : 'Saldo & Riwayat Transaksi' }}</h5>
        <p>
            {{ $isSelfHostedApp
                ? 'Untuk self-hosted, kontrol finansial utamanya ada di halaman invoice, payment, rekening bank, dan laporan keuangan. Area tersebut menjadi pengganti kebutuhan wallet platform.'
                : 'Halaman wallet menampilkan saldo aktif, transaksi terbaru, dan histori pengajuan withdrawal. Gunakan data ini untuk mengecek apakah dana platform sudah masuk dan apakah ada permintaan penarikan yang masih pending.' }}
        </p>

        <h5 id="tarik" class="border-bottom pb-2 mt-4"><i class="fas fa-money-check-alt mr-1"></i>3. {{ $isSelfHostedApp ? 'Kalau Ingin Menambahkan Fitur Ini' : 'Pengajuan Penarikan' }}</h5>
        <ol>
            @if($isSelfHostedApp)
            <li>Tentukan dulu apakah benar-benar butuh model saldo internal, atau cukup memakai laporan kas/bank yang sudah ada.</li>
            <li>Kalau dibangun, pisahkan jelas antara saldo virtual, rekening settlement, dan approval internal.</li>
            <li>Pastikan audit log dan role approval ikut dirancang sejak awal.</li>
            <li>Jangan aktifkan fitur ini setengah jadi, karena risikonya tinggi ke rekonsiliasi keuangan.</li>
            @else
            <li>Pastikan saldo mencukupi.</li>
            <li>Masukkan nominal sesuai batas minimum dan maksimum yang diizinkan.</li>
            <li>Pilih rekening tujuan yang valid.</li>
            <li>Tunggu review dari Super Admin.</li>
            @endif
        </ol>

        <h5 id="status" class="border-bottom pb-2 mt-4"><i class="fas fa-stream mr-1"></i>4. {{ $isSelfHostedApp ? 'Status Kompatibilitas' : 'Status Withdrawal' }}</h5>
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Status</th>
                    <th>Arti</th>
                </tr>
            </thead>
            <tbody>
                @if($isSelfHostedApp)
                <tr><td><strong>Default</strong></td><td>Tidak dipakai pada instalasi self-hosted standar.</td></tr>
                <tr><td><strong>Opsional</strong></td><td>Hanya relevan jika Anda sengaja membangun modul saldo internal sendiri.</td></tr>
                <tr><td><strong>Pengganti</strong></td><td>Gunakan invoice, payment, rekening bank, dan laporan keuangan untuk alur bawaan.</td></tr>
                <tr><td><strong>Rekomendasi</strong></td><td>Biarkan nonaktif agar operasional tetap sederhana dan mudah diaudit.</td></tr>
                @else
                <tr><td><strong>Pending</strong></td><td>Menunggu review Super Admin.</td></tr>
                <tr><td><strong>Approved</strong></td><td>Sudah disetujui, menunggu settlement.</td></tr>
                <tr><td><strong>Rejected</strong></td><td>Ditolak, cek alasan penolakan.</td></tr>
                <tr><td><strong>Settled</strong></td><td>Dana sudah dicairkan dan dibukukan.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
