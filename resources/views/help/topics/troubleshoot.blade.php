@extends('layouts.admin')

@section('title', 'Bantuan: Troubleshooting')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h4 class="card-title mb-0"><i class="fas fa-bug mr-2"></i>Troubleshooting — Masalah Umum &amp; Solusi</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Masalah</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#login-gagal">User tidak bisa login (RADIUS reject)</a></li>
                <li><a href="#multi-device">User hanya bisa 1 perangkat</a></li>
                <li><a href="#session-kosong">Session PPPoE / Hotspot kosong</a></li>
                <li><a href="#radius-no-response">FreeRADIUS tidak merespon</a></li>
                <li><a href="#export-gagal">Export ke MikroTik gagal</a></li>
                <li><a href="#dashboard-nol">Dashboard PPP/Hotspot Online = 0</a></li>
                <li><a href="#bandwidth-no-apply">Rate Limit tidak diterapkan</a></li>
                <li><a href="#sync-gagal">Sync RADIUS gagal</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="login-gagal" class="border-bottom pb-2 text-danger"><i class="fas fa-times-circle mr-1"></i>1. User tidak bisa login (RADIUS reject)</h5>
        <div class="row">
            <div class="col-md-6">
                <strong>Kemungkinan Penyebab:</strong>
                <ul>
                    <li>User belum disync ke radcheck</li>
                    <li>Status akun <code>disable</code></li>
                    <li>Password salah</li>
                    <li>Router belum terdaftar sebagai RADIUS client</li>
                </ul>
            </div>
            <div class="col-md-6">
                <strong>Langkah Pengecekan:</strong>
                <ol>
                    <li>Cek status user di Rafen (enable/disable)</li>
                    <li>Jalankan: <code>php artisan radius:sync-replies</code></li>
                    <li>Cek entry di radcheck: <pre class="bg-dark text-white p-2 rounded mt-1"><code>SELECT * FROM radcheck WHERE username='...';</code></pre></li>
                    <li>Cek log RADIUS: <code>tail -f /var/log/freeradius/radius.log</code></li>
                </ol>
            </div>
        </div>

        {{-- 2 --}}
        <h5 id="multi-device" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-mobile-alt mr-1"></i>2. User hanya bisa 1 perangkat</h5>
        <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i><strong>Sudah diperbaiki di versi ini.</strong> Nilai <code>Shared Users</code> di profil hotspot sekarang disync otomatis sebagai <code>Simultaneous-Use</code> ke radcheck.</div>
        <strong>Jika masih bermasalah:</strong>
        <ol>
            <li>Buka <strong>Profil Paket → Profil Hotspot</strong>, pastikan nilai Shared Users sudah benar</li>
            <li>Jalankan: <code>php artisan radius:sync-replies</code></li>
            <li>Verifikasi di database:
                <pre class="bg-dark text-white p-2 rounded mt-1"><code>SELECT * FROM radcheck WHERE username='...' AND attribute='Simultaneous-Use';</code></pre>
            </li>
        </ol>

        {{-- 3 --}}
        <h5 id="session-kosong" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-table mr-1"></i>3. Session PPPoE / Hotspot kosong</h5>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Penyebab</th><th>Solusi</th></tr></thead>
            <tbody>
                <tr><td>API MikroTik mati</td><td>Aktifkan API di MikroTik: <code>IP → Services → API</code> → Enable</td></tr>
                <tr><td>Kredensial API salah</td><td>Edit router di Rafen, pastikan host/user/password API benar</td></tr>
                <tr><td>Firewall blokir port 8728</td><td>Buka port 8728 (API) atau 8729 (API-SSL) di firewall MikroTik</td></tr>
                <tr><td>Router tidak reachable via VPN</td><td>Cek koneksi WireGuard, ping ke IP router dari server Rafen</td></tr>
            </tbody>
        </table>

        {{-- 4 --}}
        <h5 id="radius-no-response" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-server mr-1"></i>4. FreeRADIUS tidak merespon</h5>
        <pre class="bg-dark text-white p-3 rounded"><code># Cek status
systemctl status freeradius

# Restart
systemctl restart freeradius

# Monitor log autentikasi
tail -f /var/log/freeradius/radius.log</code></pre>
        <div class="alert alert-warning mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Jika tetap gagal autentikasi setelah restart, cek log FreeRADIUS dan cari baris <code>ERROR</code> atau <code>FAILED</code>.</div>

        <h5 id="isolir-ip-mode" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-globe mr-1"></i>5. Halaman isolir tidak muncul pada instalasi tanpa domain</h5>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Penyebab</th><th>Solusi</th></tr></thead>
            <tbody>
                <tr><td><code>APP_URL</code> memakai IP, tetapi akses diuji via HTTPS</td><td>Uji dulu akses <code>http://APP_URL</code>. Bootstrap Nginx bawaan self-hosted hanya menyiapkan listener port 80.</td></tr>
                <tr><td>Rule isolir MikroTik aktif, tetapi klien tidak masuk pool isolir</td><td>Pastikan <code>Framed-Pool</code>, <code>Mikrotik-Group</code>, dan kick session berjalan saat pelanggan masuk status <code>isolir</code>.</td></tr>
                <tr><td>Host/IP aplikasi tidak reachable dari router atau klien isolir</td><td>Cek NAT/firewall router, pastikan IP server Rafen bisa diakses dari subnet isolir.</td></tr>
            </tbody>
        </table>

        {{-- 5 --}}
        <h5 id="export-gagal" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-file-export mr-1"></i>5. Export ke MikroTik gagal</h5>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Pesan Error</th><th>Solusi</th></tr></thead>
            <tbody>
                <tr><td><code>input does not match any value of address-pool</code></td><td>Nama IP Pool di profil group tidak sesuai dengan pool di MikroTik. Samakan nama pool.</td></tr>
                <tr><td><code>no such command</code></td><td>Perintah API salah. Pastikan versi RouterOS mendukung perintah yang digunakan.</td></tr>
                <tr><td>Timeout / connection refused</td><td>Cek koneksi ke router, port API, dan kredensial.</td></tr>
            </tbody>
        </table>

        {{-- 6 --}}
        <h5 id="dashboard-nol" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-chart-bar mr-1"></i>6. Dashboard PPP/Hotspot Online = 0</h5>
        <ol>
            <li>Buka halaman <strong>Session User → PPPoE</strong> atau <strong>Session User → Hotspot</strong> — ini akan trigger sync otomatis</li>
            <li>Kembali ke dashboard, angka seharusnya sudah terupdate</li>
            <li>Jika tetap 0, cek apakah ada session aktif di MikroTik secara manual</li>
        </ol>

        {{-- 7 --}}
        <h5 id="bandwidth-no-apply" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-tachometer-alt mr-1"></i>7. Rate Limit tidak diterapkan</h5>
        <ol>
            <li>Pastikan profil bandwidth sudah di-assign ke user/profil</li>
            <li>Jalankan sync: <code>php artisan radius:sync-replies</code></li>
            <li>Cek radreply: <pre class="bg-dark text-white p-2 rounded mt-1"><code>SELECT * FROM radreply WHERE username='...' AND attribute='Mikrotik-Rate-Limit';</code></pre></li>
            <li>Pastikan MikroTik dikonfigurasi menggunakan RADIUS untuk rate limit (bukan local profile)</li>
        </ol>

        {{-- 8 --}}
        <h5 id="sync-gagal" class="border-bottom pb-2 text-danger mt-4"><i class="fas fa-sync mr-1"></i>8. Sync RADIUS gagal</h5>
        <pre class="bg-dark text-white p-3 rounded"><code># Jalankan manual dengan output
php /var/www/rafen/artisan radius:sync-replies

# Jika ada error, cek log Laravel
tail -50 /var/www/rafen/storage/logs/laravel.log</code></pre>
        <div class="alert alert-info mt-2"><i class="fas fa-info-circle mr-1"></i>Pastikan user database <code>rafen</code> memiliki akses baca/tulis ke tabel <code>radcheck</code>, <code>radreply</code>, <code>radgroupcheck</code>, dan <code>radusergroup</code>.</div>

    </div>
</div>
@endsection
