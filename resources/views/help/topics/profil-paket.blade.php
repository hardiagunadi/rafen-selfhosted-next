@extends('layouts.admin')

@section('title', 'Bantuan: Profil Paket')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-box mr-2"></i>Profil Paket — Panduan Lengkap</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#bandwidth">Profil Bandwidth</a></li>
                <li><a href="#group">Profil Group</a></li>
                <li><a href="#hotspot-profil">Profil Hotspot</a></li>
                <li><a href="#ppp-profil">Profil PPP</a></li>
                <li><a href="#alur">Alur Penggunaan</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="bandwidth" class="border-bottom pb-2 text-primary"><i class="fas fa-tachometer-alt mr-1"></i>1. Profil Bandwidth</h5>
        <p>Mendefinisikan batas kecepatan upload dan download yang akan dikirimkan ke MikroTik via RADIUS.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama</strong></td><td>Identifikasi profil, mis. <em>10Mbps Down / 5Mbps Up</em></td></tr>
                <tr><td><strong>Upload Max (Mbps)</strong></td><td>Kecepatan upload maksimal</td></tr>
                <tr><td><strong>Download Max (Mbps)</strong></td><td>Kecepatan download maksimal</td></tr>
            </tbody>
        </table>
        <p>Nilai ini dikirim ke MikroTik sebagai:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>Mikrotik-Rate-Limit = "5M/10M"   # upload/download</code></pre>

        {{-- 2 --}}
        <h5 id="group" class="border-bottom pb-2 text-primary"><i class="fas fa-layer-group mr-1"></i>2. Profil Group</h5>
        <p>Profil Group digunakan untuk mengelompokkan user/voucher dengan kebutuhan <strong>IP Pool terpisah</strong>.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama Group</strong></td><td>Nama grup, mis. <em>Hotspot-Area-A</em></td></tr>
                <tr><td><strong>IP Pool Mode</strong></td><td><code>default</code> = ikuti pool router, <code>group_only</code> = pakai pool khusus</td></tr>
                <tr><td><strong>IP Pool Name</strong></td><td>Nama pool di MikroTik (wajib diisi jika mode = group_only)</td></tr>
            </tbody>
        </table>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Nama IP Pool <strong>harus sama persis</strong> dengan nama pool yang ada di router MikroTik. Kesalahan nama pool menyebabkan export gagal.</div>

        {{-- 3 --}}
        <h5 id="hotspot-profil" class="border-bottom pb-2 text-primary"><i class="fas fa-broadcast-tower mr-1"></i>3. Profil Hotspot</h5>
        <p>Profil yang menentukan kebijakan akses untuk user hotspot dan voucher.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama</strong></td><td>Nama profil</td></tr>
                <tr><td><strong>Shared Users</strong></td><td>Jumlah perangkat bersamaan yang diizinkan. Default: 1</td></tr>
                <tr><td><strong>Bandwidth</strong></td><td>Pilih profil bandwidth</td></tr>
                <tr><td><strong>Profil Group</strong></td><td>Pilih grup (opsional, untuk IP Pool terpisah)</td></tr>
            </tbody>
        </table>

        {{-- 4 --}}
        <h5 id="ppp-profil" class="border-bottom pb-2 text-primary"><i class="fas fa-wifi mr-1"></i>4. Profil PPP</h5>
        <p>Profil untuk user PPPoE. Struktur serupa dengan profil hotspot, namun tanpa pengaturan Shared Users.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama</strong></td><td>Nama profil PPP</td></tr>
                <tr><td><strong>Bandwidth</strong></td><td>Pilih profil bandwidth</td></tr>
                <tr><td><strong>Profil Group</strong></td><td>Pilih grup (opsional)</td></tr>
            </tbody>
        </table>

        {{-- 5 --}}
        <h5 id="alur" class="border-bottom pb-2 text-primary"><i class="fas fa-project-diagram mr-1"></i>5. Alur Penggunaan</h5>
        <div class="alert alert-light border">
            <strong>Urutan yang disarankan saat membuat paket baru:</strong>
            <ol class="mt-2 mb-0">
                <li>Buat <strong>Profil Bandwidth</strong> (upload/download speed)</li>
                <li>Buat <strong>Profil Group</strong> jika perlu IP Pool terpisah</li>
                <li>Buat <strong>Profil Hotspot</strong> atau <strong>Profil PPP</strong>, pilih bandwidth &amp; group yang sudah dibuat</li>
                <li>Assign profil ke <strong>User</strong> atau <strong>Voucher</strong></li>
                <li>Jalankan <code>radius:sync-replies</code> untuk menerapkan ke FreeRADIUS</li>
            </ol>
        </div>

    </div>
</div>
@endsection
