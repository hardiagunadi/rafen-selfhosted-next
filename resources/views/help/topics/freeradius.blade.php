@extends('layouts.admin')

@section('title', 'Bantuan: FreeRADIUS')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h4 class="card-title mb-0"><i class="fas fa-broadcast-tower mr-2"></i>FreeRADIUS — Panduan Konfigurasi</h4>
    </div>
    <div class="card-body">

        {{-- TOC --}}
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#arsitektur">Arsitektur FreeRADIUS di Rafen</a></li>
                <li><a href="#sql-module">Konfigurasi SQL Module</a></li>
                <li><a href="#clients">Klien RADIUS (NAS)</a></li>
                <li><a href="#radcheck">Tabel radcheck &amp; radreply</a></li>
                <li><a href="#sync">Sinkronisasi User ke RADIUS</a></li>
                <li><a href="#simultaneous">Simultaneous-Use (Multi-Device)</a></li>
                <li><a href="#perintah">Perintah Penting</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="arsitektur" class="border-bottom pb-2 text-danger"><i class="fas fa-sitemap mr-1"></i>1. Arsitektur FreeRADIUS di Rafen</h5>
        <p>Rafen menggunakan FreeRADIUS 3.x dengan backend database MariaDB. Alur autentikasi:</p>
        <div class="bg-light border rounded p-3 mb-3">
            <code>MikroTik → RADIUS Request → FreeRADIUS → SQL query ke DB rafen → Accept / Reject</code>
        </div>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Komponen</th><th>Path / Nilai</th></tr></thead>
            <tbody>
                <tr><td>Config FreeRADIUS</td><td><code>/etc/freeradius/3.0/</code></td></tr>
                <tr><td>SQL module</td><td><code>/etc/freeradius/3.0/mods-available/sql</code></td></tr>
                <tr><td>Klien dari Rafen</td><td><code>/etc/freeradius/3.0/clients.d/laravel.conf</code></td></tr>
                <tr><td>Log autentikasi</td><td><code>/var/log/freeradius/radius.log</code></td></tr>
                <tr><td>Database</td><td><code>rafen</code> (MariaDB, user: rafen)</td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="sql-module" class="border-bottom pb-2 text-danger"><i class="fas fa-database mr-1"></i>2. Konfigurasi SQL Module</h5>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i><strong>Penting:</strong> SQL module HARUS menyertakan baris berikut agar query bawaan terbaca:</div>
        <pre class="bg-dark text-white p-3 rounded"><code>$INCLUDE ${modconfdir}/${.:name}/main/${dialect}/queries.conf</code></pre>
        <p>Pastikan symlink aktif:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>ls -la /etc/freeradius/3.0/mods-enabled/sql</code></pre>
        <p>Jika belum ada:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql</code></pre>

        {{-- 3 --}}
        <h5 id="clients" class="border-bottom pb-2 text-danger"><i class="fas fa-server mr-1"></i>3. Klien RADIUS (NAS)</h5>
        <p>Setiap MikroTik yang terdaftar di Rafen (menu <strong>Router (NAS)</strong>) harus didaftarkan sebagai RADIUS client. Klik tombol <strong>Sync RADIUS Clients</strong> di halaman Router untuk menulis ulang file <code>laravel.conf</code>.</p>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>File <code>clients.conf</code> (localhost) menggunakan <code>require_message_authenticator = yes</code>. Pastikan MikroTik juga mengirimkan Message-Authenticator.</div>

        {{-- 4 --}}
        <h5 id="radcheck" class="border-bottom pb-2 text-danger"><i class="fas fa-table mr-1"></i>4. Tabel radcheck &amp; radreply</h5>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Tabel</th><th>Attribute</th><th>Op</th><th>Fungsi</th></tr></thead>
            <tbody>
                <tr><td>radcheck</td><td>Cleartext-Password</td><td>:=</td><td>Password user (plain text)</td></tr>
                <tr><td>radcheck</td><td>Simultaneous-Use</td><td>:=</td><td>Maks perangkat login bersamaan</td></tr>
                <tr><td>radreply</td><td>Mikrotik-Rate-Limit</td><td>:=</td><td>Bandwidth limit (contoh: <code>5M/10M</code>)</td></tr>
                <tr><td>radreply</td><td>Framed-Pool</td><td>:=</td><td>IP Pool khusus untuk group tertentu</td></tr>
            </tbody>
        </table>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i><strong>realm suffix</strong> dikonfigurasi dengan <code>strip = no</code> agar username penuh (mis. <code>user@domain.id</code>) tidak dipotong saat diteruskan ke SQL.</div>

        {{-- 5 --}}
        <h5 id="sync" class="border-bottom pb-2 text-danger"><i class="fas fa-sync mr-1"></i>5. Sinkronisasi User ke RADIUS</h5>
        <p>Rafen mensinkronisasi data user ke FreeRADIUS melalui dua jalur:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Sumber Data</th><th>Target Tabel</th><th>Trigger</th></tr></thead>
            <tbody>
                <tr><td>PPP Users (<code>ppp_users</code>)</td><td>radcheck, radreply</td><td>Otomatis via cron / manual Sync</td></tr>
                <tr><td>Hotspot Users (<code>hotspot_users</code>)</td><td>radcheck, radreply</td><td>Otomatis via cron / manual Sync</td></tr>
                <tr><td>Voucher (<code>vouchers</code>)</td><td>radcheck, radreply</td><td>Otomatis via cron / manual Sync</td></tr>
            </tbody>
        </table>
        <p>Jalankan sinkronisasi manual via terminal:</p>
        <pre class="bg-dark text-white p-3 rounded"><code>php /var/www/rafen/artisan radius:sync-replies</code></pre>

        {{-- 6 --}}
        <h5 id="simultaneous" class="border-bottom pb-2 text-danger"><i class="fas fa-users mr-1"></i>6. Simultaneous-Use (Multi-Device)</h5>
        <p>Pengaturan berapa perangkat yang bisa login bersamaan dengan 1 akun dikontrol melalui kolom <strong>Shared Users</strong> di Profil Hotspot.</p>
        <div class="row">
            <div class="col-md-6">
                <div class="card border-success mb-3">
                    <div class="card-header bg-success text-white py-2"><i class="fas fa-check mr-1"></i>Cara Setting</div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Buka <strong>Profil Paket → Profil Hotspot</strong></li>
                            <li>Edit profil yang diinginkan</li>
                            <li>Ubah nilai <strong>Shared Users</strong> (mis. <code>3</code> untuk 3 perangkat)</li>
                            <li>Simpan, lalu jalankan: <code>radius:sync-replies</code></li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-info mb-3">
                    <div class="card-header bg-info text-white py-2"><i class="fas fa-info-circle mr-1"></i>Cara Kerja</div>
                    <div class="card-body">
                        <p class="mb-1">Nilai <code>shared_users</code> disync sebagai:</p>
                        <pre class="bg-dark text-white p-2 rounded mb-0"><code>radcheck:
attribute = Simultaneous-Use
op = :=
value = 3</code></pre>
                    </div>
                </div>
            </div>
        </div>

        {{-- 7 --}}
        <h5 id="perintah" class="border-bottom pb-2 text-danger"><i class="fas fa-terminal mr-1"></i>7. Perintah Penting</h5>
        <table class="table table-sm table-bordered">
            <thead class="thead-light"><tr><th>Perintah</th><th>Fungsi</th></tr></thead>
            <tbody>
                <tr><td><code>systemctl reload freeradius</code></td><td>Reload config tanpa putus koneksi aktif</td></tr>
                <tr><td><code>systemctl restart freeradius</code></td><td>Restart penuh FreeRADIUS</td></tr>
                <tr><td><code>systemctl status freeradius</code></td><td>Cek status FreeRADIUS</td></tr>
                <tr><td><code>tail -f /var/log/freeradius/radius.log</code></td><td>Monitor log autentikasi real-time</td></tr>
                <tr><td><code>php artisan radius:sync-replies</code></td><td>Sinkronisasi user Rafen → radcheck/radreply</td></tr>
            </tbody>
        </table>

    </div>
</div>
@endsection
