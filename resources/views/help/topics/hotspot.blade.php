@extends('layouts.admin')

@section('title', 'Bantuan: Hotspot')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <h4 class="card-title mb-0"><i class="fas fa-broadcast-tower mr-2"></i>Hotspot — Panduan Lengkap</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#profil">Profil Hotspot</a></li>
                <li><a href="#shared">Shared Users (Multi-Device)</a></li>
                <li><a href="#user">User Hotspot</a></li>
                <li><a href="#sync">Sinkronisasi ke RADIUS</a></li>
                <li><a href="#tips">Tips &amp; Catatan Penting</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="profil" class="border-bottom pb-2 text-success"><i class="fas fa-box mr-1"></i>1. Profil Hotspot</h5>
        <p>Profil hotspot mendefinisikan <strong>kebijakan akses</strong> untuk sekelompok pengguna hotspot. Setiap user atau voucher wajib memiliki profil.</p>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama Profil</strong></td><td>Nama identifikasi profil (mis. <em>Paket Rumah 10Mbps</em>)</td></tr>
                <tr><td><strong>Shared Users</strong></td><td>Jumlah perangkat yang boleh login bersamaan dengan 1 akun. Default: <code>1</code>. Ubah ke <code>3</code>, <code>5</code>, dll untuk multi-device.</td></tr>
                <tr><td><strong>Bandwidth</strong></td><td>Pilih profil bandwidth untuk rate limit upload/download</td></tr>
                <tr><td><strong>Profil Group</strong></td><td>Pengelompokan untuk IP Pool terpisah (opsional)</td></tr>
                <tr><td><strong>Tipe</strong></td><td>Unlimited / Terbatas (waktu atau kuota)</td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="shared" class="border-bottom pb-2 text-success"><i class="fas fa-users mr-1"></i>2. Shared Users (Multi-Device)</h5>
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle mr-1"></i><strong>Masalah umum:</strong> User hanya bisa login di 1 perangkat meski Shared Users sudah diset lebih dari 1.
        </div>
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-1"></i><strong>Penyebab &amp; Solusi:</strong> Nilai <code>Shared Users</code> di database belum disinkronkan ke FreeRADIUS. Pastikan sync dijalankan setelah mengubah profil:
            <pre class="bg-dark text-white p-2 rounded mt-2 mb-0"><code>php artisan radius:sync-replies</code></pre>
        </div>
        <p><strong>Cara kerja teknis:</strong> Setiap user/voucher mendapatkan entry di tabel <code>radcheck</code>:</p>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>username</th><th>attribute</th><th>op</th><th>value</th></tr></thead>
            <tbody>
                <tr><td>user123</td><td>Simultaneous-Use</td><td>:=</td><td>3</td></tr>
            </tbody>
        </table>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>FreeRADIUS akan menolak login perangkat ke-4 jika value = 3.</div>

        {{-- 3 --}}
        <h5 id="user" class="border-bottom pb-2 text-success"><i class="fas fa-user mr-1"></i>3. User Hotspot</h5>
        <p>User hotspot berbeda dari voucher: user hotspot bersifat <strong>tetap / berlangganan</strong>, sementara voucher bersifat <strong>sementara / habis pakai</strong>.</p>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Username</strong></td><td>Username login hotspot (unik per router)</td></tr>
                <tr><td><strong>Password</strong></td><td>Password login hotspot</td></tr>
                <tr><td><strong>Status Akun</strong></td><td><code>enable</code> = aktif, <code>disable</code> = tidak bisa login, <code>isolir</code> = diblokir karena tunggakan</td></tr>
                <tr><td><strong>Profil</strong></td><td>Pilih profil hotspot yang berlaku untuk user ini</td></tr>
            </tbody>
        </table>

        {{-- 4 --}}
        <h5 id="sync" class="border-bottom pb-2 text-success"><i class="fas fa-sync mr-1"></i>4. Sinkronisasi ke RADIUS</h5>
        <p>Data user hotspot di Rafen disinkronkan ke tabel FreeRADIUS:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Yang Disync</th><th>Tabel RADIUS</th><th>Attribute</th></tr></thead>
            <tbody>
                <tr><td>Password</td><td>radcheck</td><td>Cleartext-Password</td></tr>
                <tr><td>Shared Users</td><td>radcheck</td><td>Simultaneous-Use</td></tr>
                <tr><td>Bandwidth</td><td>radreply</td><td>Mikrotik-Rate-Limit</td></tr>
                <tr><td>IP Pool (jika group_only)</td><td>radreply</td><td>Framed-Pool</td></tr>
            </tbody>
        </table>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>User dengan status <code>disable</code> atau <code>isolir</code> akan <strong>dihapus</strong> dari radcheck/radreply (tidak bisa login RADIUS).</div>

        {{-- 5 --}}
        <h5 id="tips" class="border-bottom pb-2 text-success"><i class="fas fa-lightbulb mr-1"></i>5. Tips &amp; Catatan Penting</h5>
        <ul>
            <li>Setelah mengubah profil (shared_users, bandwidth), selalu jalankan sync RADIUS.</li>
            <li>Username hotspot bersifat <strong>unik per router + service</strong>. Username sama bisa ada di router berbeda.</li>
            <li>Voucher dengan status <code>expired</code> otomatis dihapus dari radcheck saat sync berjalan.</li>
            <li>Cron job sync dijalankan otomatis setiap periode tertentu. Cek jadwal di pengaturan server.</li>
            <li>Jika export ke MikroTik gagal karena <code>address-pool</code>, pastikan nama pool di profil group sesuai dengan nama pool di router MikroTik.</li>
        </ul>

    </div>
</div>
@endsection
