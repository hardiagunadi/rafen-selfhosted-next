@extends('layouts.admin')

@section('title', 'Bantuan: Voucher')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-secondary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-ticket-alt mr-2"></i>Voucher — Panduan Lengkap</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#buat-voucher">Membuat Batch Voucher</a></li>
                <li><a href="#status">Status Voucher</a></li>
                <li><a href="#cetak">Cetak Voucher</a></li>
                <li><a href="#sync-voucher">Sinkronisasi ke RADIUS</a></li>
                <li><a href="#tips-voucher">Tips Penting</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="buat-voucher" class="border-bottom pb-2 text-secondary"><i class="fas fa-plus-circle mr-1"></i>1. Membuat Batch Voucher</h5>
        <p>Voucher dibuat dalam batch (kelompok). Setiap batch menghasilkan sejumlah voucher dengan credential unik.</p>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Profil Hotspot</strong></td><td>Profil yang berlaku untuk semua voucher di batch ini (bandwidth, durasi, shared users)</td></tr>
                <tr><td><strong>Jumlah</strong></td><td>Berapa voucher yang akan dibuat dalam 1 batch</td></tr>
                <tr><td><strong>Prefix</strong></td><td>Awalan username, mis. <code>VCR</code> → username: <code>VCR-XXXXX</code></td></tr>
                <tr><td><strong>Masa Berlaku</strong></td><td>Durasi voucher aktif setelah digunakan pertama kali</td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="status" class="border-bottom pb-2 text-secondary"><i class="fas fa-info-circle mr-1"></i>2. Status Voucher</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><span class="badge badge-success">unused</span></td><td>Voucher belum pernah digunakan. Ada di radcheck, bisa login.</td></tr>
                <tr><td><span class="badge badge-primary">used</span></td><td>Voucher sudah pernah digunakan. Masih ada di radcheck selama belum expired.</td></tr>
                <tr><td><span class="badge badge-danger">expired</span></td><td>Voucher sudah kadaluarsa. Dihapus dari radcheck saat sync berjalan.</td></tr>
            </tbody>
        </table>

        {{-- 3 --}}
        <h5 id="cetak" class="border-bottom pb-2 text-secondary"><i class="fas fa-print mr-1"></i>3. Cetak Voucher</h5>
        <p>Setiap batch voucher bisa dicetak langsung dari halaman <strong>Voucher</strong>. Klik tombol <strong>Print</strong> pada baris batch yang diinginkan. Tampilan cetak menampilkan:</p>
        <ul>
            <li>Username &amp; Password voucher</li>
            <li>Profil / paket</li>
            <li>Informasi masa berlaku</li>
        </ul>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Tampilan cetak dioptimalkan untuk kertas voucher ukuran kecil. Gunakan <em>Print to PDF</em> untuk menyimpan digital.</div>

        {{-- 4 --}}
        <h5 id="sync-voucher" class="border-bottom pb-2 text-secondary"><i class="fas fa-sync mr-1"></i>4. Sinkronisasi ke RADIUS</h5>
        <p>Voucher dengan status <code>unused</code> dan <code>used</code> disync ke radcheck. Voucher <code>expired</code> dihapus dari radcheck.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Yang Disync</th><th>Tabel</th><th>Attribute</th></tr></thead>
            <tbody>
                <tr><td>Password</td><td>radcheck</td><td>Cleartext-Password</td></tr>
                <tr><td>Shared Users (dari profil)</td><td>radcheck</td><td>Simultaneous-Use</td></tr>
                <tr><td>Bandwidth</td><td>radreply</td><td>Mikrotik-Rate-Limit</td></tr>
            </tbody>
        </table>

        {{-- 5 --}}
        <h5 id="tips-voucher" class="border-bottom pb-2 text-secondary"><i class="fas fa-lightbulb mr-1"></i>5. Tips Penting</h5>
        <ul>
            <li>Jika profil voucher menggunakan <code>address-pool</code> yang tidak ada di MikroTik, export akan gagal. Pastikan nama pool di profil group sesuai dengan pool di router.</li>
            <li>Voucher expired tidak otomatis disconnect user yang sedang online — hanya mencegah login baru setelah sync.</li>
            <li>Satu username voucher hanya bisa digunakan oleh satu pelanggan (kecuali Shared Users &gt; 1).</li>
            <li>Hapus voucher expired secara berkala agar tabel tidak penuh.</li>
        </ul>

    </div>
</div>
@endsection
