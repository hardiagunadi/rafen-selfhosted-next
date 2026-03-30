@extends('layouts.admin')

@section('title', 'Bantuan: PPPoE')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h4 class="card-title mb-0"><i class="fas fa-wifi mr-2"></i>PPPoE — Panduan Lengkap</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#profil-ppp">Profil PPP</a></li>
                <li><a href="#user-ppp">User PPP</a></li>
                <li><a href="#sync-ppp">Sinkronisasi ke RADIUS</a></li>
                <li><a href="#rateLimit">Rate Limit &amp; Bandwidth</a></li>
                <li><a href="#session-ppp">Session Aktif PPPoE</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="profil-ppp" class="border-bottom pb-2 text-info"><i class="fas fa-box mr-1"></i>1. Profil PPP</h5>
        <p>Profil PPP mendefinisikan kebijakan koneksi untuk user PPPoE. Profil ini wajib ada sebelum menambahkan user PPP.</p>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama Profil</strong></td><td>Nama profil, mis. <em>Paket 20Mbps</em></td></tr>
                <tr><td><strong>Bandwidth</strong></td><td>Profil bandwidth upload/download (Mikrotik-Rate-Limit)</td></tr>
                <tr><td><strong>Profil Group</strong></td><td>Grup untuk IP Pool terpisah (opsional)</td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="user-ppp" class="border-bottom pb-2 text-info"><i class="fas fa-user mr-1"></i>2. User PPP</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Username</strong></td><td>Username PPPoE (contoh: <code>pelanggan@domain.id</code>)</td></tr>
                <tr><td><strong>Password PPP</strong></td><td>Password koneksi PPPoE</td></tr>
                <tr><td><strong>Status</strong></td><td><code>enable</code> = aktif, <code>disable</code> = nonaktif</td></tr>
                <tr><td><strong>Profil PPP</strong></td><td>Profil yang berlaku untuk user ini</td></tr>
            </tbody>
        </table>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Field status menggunakan nilai <code>enable</code> / <code>disable</code> (bukan aktif/disable). Hanya user dengan status <code>enable</code> yang disync ke radcheck.</div>

        {{-- 3 --}}
        <h5 id="sync-ppp" class="border-bottom pb-2 text-info"><i class="fas fa-sync mr-1"></i>3. Sinkronisasi ke RADIUS</h5>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Yang Disync</th><th>Tabel RADIUS</th><th>Attribute</th></tr></thead>
            <tbody>
                <tr><td>Password</td><td>radcheck</td><td>Cleartext-Password</td></tr>
                <tr><td>Bandwidth</td><td>radreply</td><td>Mikrotik-Rate-Limit</td></tr>
                <tr><td>IP Pool (jika group_only)</td><td>radreply</td><td>Framed-Pool</td></tr>
            </tbody>
        </table>
        <p>Sinkronisasi otomatis via cron, atau manual:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>php artisan radius:sync-replies</code></pre>
        <div class="alert alert-warning mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>User PPP yang di-<code>disable</code> akan dihapus dari radcheck. Pastikan status user benar sebelum sync.</div>

        {{-- 4 --}}
        <h5 id="rateLimit" class="border-bottom pb-2 text-info"><i class="fas fa-tachometer-alt mr-1"></i>4. Rate Limit &amp; Bandwidth</h5>
        <p>Format rate limit yang dikirim ke MikroTik via RADIUS:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>Mikrotik-Rate-Limit = "10M/20M"
# Format: Upload/Download
# Contoh: 5M/10M = upload 5Mbps, download 10Mbps</code></pre>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Nilai diambil dari profil bandwidth yang terpilih. Jika bandwidth tidak diset, rate limit tidak dikirimkan (user mengikuti kebijakan default router).</div>

        {{-- 5 --}}
        <h5 id="session-ppp" class="border-bottom pb-2 text-info"><i class="fas fa-signal mr-1"></i>5. Session Aktif PPPoE</h5>
        <p>Halaman <strong>Session User → PPPoE</strong> menampilkan session aktif yang diambil langsung dari MikroTik (via API) dan disimpan sementara di tabel <code>radius_accounts</code>.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Kolom</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td>Username</td><td>Username PPPoE yang sedang terkoneksi</td></tr>
                <tr><td>IP Address</td><td>IP yang diberikan ke perangkat</td></tr>
                <tr><td>Uptime</td><td>Lama koneksi berlangsung</td></tr>
                <tr><td>Caller-ID</td><td>MAC address atau interface perangkat</td></tr>
                <tr><td>Upload / Download</td><td>Total data terpakai (dari tabel <code>radacct</code>)</td></tr>
                <tr><td>Profile</td><td>Service/profil PPP dari MikroTik</td></tr>
                <tr><td>Router</td><td>Router asal session</td></tr>
            </tbody>
        </table>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Data Upload/Download diambil dari tabel <code>radacct</code> (kolom <code>acctinputoctets</code> / <code>acctoutputoctets</code>) untuk session yang belum berhenti (<code>acctstoptime IS NULL</code>).</div>

    </div>
</div>
@endsection
