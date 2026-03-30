@extends('layouts.admin')

@section('title', 'Bantuan: WireGuard VPN')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-warning">
        <h4 class="card-title mb-0"><i class="fas fa-shield-alt mr-2"></i>WireGuard VPN — Panduan Konfigurasi</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#arsitektur-wg">Arsitektur WireGuard di Rafen</a></li>
                <li><a href="#server-wg">Konfigurasi Server WireGuard</a></li>
                <li><a href="#peer-wg">Menambah Peer (MikroTik)</a></li>
                <li><a href="#env-wg">Pengaturan .env</a></li>
                <li><a href="#cron-wg">Cron Job Otomatis</a></li>
                <li><a href="#perintah-wg">Perintah Penting</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="arsitektur-wg" class="border-bottom pb-2"><i class="fas fa-sitemap mr-1"></i>1. Arsitektur WireGuard di Rafen</h5>
        <div class="bg-light border rounded p-3 mb-3">
            <code>Rafen Server (10.0.0.1 / wg0) ←—VPN Tunnel—→ MikroTik Peer (10.0.0.x) ←—→ Pelanggan</code>
        </div>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Parameter</th><th>Nilai</th></tr></thead>
            <tbody>
                <tr><td>IP Server VPN</td><td><code>10.0.0.1</code></td></tr>
                <tr><td>Interface</td><td><code>wg0</code></td></tr>
                <tr><td>MikroTik "wireless" peer</td><td><code>10.0.0.2</code></td></tr>
                <tr><td>Port WireGuard</td><td>Konfigurasi di <code>wg0.conf</code></td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="server-wg" class="border-bottom pb-2"><i class="fas fa-server mr-1"></i>2. Konfigurasi Server WireGuard</h5>
        <p>Buka halaman <strong>Pengaturan → WireGuard</strong> di Rafen untuk:</p>
        <ul>
            <li>Generate keypair server (Public Key &amp; Private Key)</li>
            <li>Set WG_HOST (IP publik atau domain server)</li>
            <li>Simpan public key server yang akan digunakan di konfigurasi MikroTik</li>
        </ul>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i><strong>Penting:</strong> Setelah generate keypair baru, semua peer yang sudah ada harus dikonfigurasi ulang di router MikroTik menggunakan public key server yang baru.</div>

        {{-- 3 --}}
        <h5 id="peer-wg" class="border-bottom pb-2"><i class="fas fa-plug mr-1"></i>3. Menambah Peer (MikroTik)</h5>
        <p>Setiap router MikroTik yang ingin terhubung ke Rafen via VPN harus ditambahkan sebagai <strong>WireGuard Peer</strong>.</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Field</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>Nama</strong></td><td>Nama identifikasi peer</td></tr>
                <tr><td><strong>VPN IP</strong></td><td>IP tunnel peer, mis. <code>10.0.0.2</code></td></tr>
                <tr><td><strong>Public Key</strong></td><td>Public key dari MikroTik (generate di router)</td></tr>
            </tbody>
        </table>
        <p>Setelah peer ditambah:</p>
        <ol>
            <li>Klik <strong>Sync</strong> untuk menulis ulang <code>wg0.conf</code> dan reload WireGuard</li>
            <li>Klik <strong>Create NAS</strong> untuk otomatis mendaftarkan peer sebagai Router (NAS) di Rafen</li>
        </ol>

        {{-- 4 --}}
        <h5 id="env-wg" class="border-bottom pb-2"><i class="fas fa-file-code mr-1"></i>4. Pengaturan .env</h5>
        <pre class="bg-dark text-white p-3 rounded"><code>WG_HOST=your-server-ip-or-domain
WG_PRIVATE_KEY=...
WG_PUBLIC_KEY=...
WG_INTERFACE=wg0</code></pre>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i><code>WG_HOST</code> adalah IP publik atau domain server yang digunakan MikroTik untuk menjangkau server VPN.</div>

        {{-- 5 --}}
        <h5 id="cron-wg" class="border-bottom pb-2"><i class="fas fa-clock mr-1"></i>5. Cron Job Otomatis</h5>
        <p>Rafen dapat memasang cron job untuk memantau status koneksi WireGuard secara berkala. Klik tombol <strong>Install Cron</strong> di halaman WireGuard Settings.</p>
        <p>Cron job melakukan ping ke setiap peer dan memperbarui status koneksi di dashboard.</p>

        {{-- 6 --}}
        <h5 id="perintah-wg" class="border-bottom pb-2"><i class="fas fa-terminal mr-1"></i>6. Perintah Penting</h5>
        <table class="table table-sm table-bordered">
            <thead class="thead-light"><tr><th>Perintah</th><th>Fungsi</th></tr></thead>
            <tbody>
                <tr><td><code>wg show</code></td><td>Tampilkan status interface dan peer WireGuard</td></tr>
                <tr><td><code>wg showconf wg0</code></td><td>Tampilkan konfigurasi aktif wg0</td></tr>
                <tr><td><code>systemctl status wg-quick@wg0</code></td><td>Cek status WireGuard</td></tr>
                <tr><td><code>systemctl restart wg-quick@wg0</code></td><td>Restart WireGuard</td></tr>
                <tr><td><code>ping 10.0.0.2</code></td><td>Tes koneksi ke peer MikroTik</td></tr>
            </tbody>
        </table>

    </div>
</div>
@endsection
