@extends('layouts.admin')

@section('title', 'Bantuan: Session User')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h4 class="card-title mb-0"><i class="fas fa-signal mr-2"></i>Session User — Panduan Monitoring</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#cara-kerja">Cara Kerja Auto-Sync</a></li>
                <li><a href="#pppoe-session">Session PPPoE</a></li>
                <li><a href="#hotspot-session">Session Hotspot</a></li>
                <li><a href="#ajax-refresh">AJAX Auto-Refresh</a></li>
                <li><a href="#tips-session">Tips &amp; Troubleshoot</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="cara-kerja" class="border-bottom pb-2 text-info"><i class="fas fa-sync mr-1"></i>1. Cara Kerja Auto-Sync</h5>
        <p>Setiap kali halaman Session dibuka, Rafen otomatis mengambil data session aktif dari semua router MikroTik yang dapat diakses, lalu menyimpannya sementara di tabel <code>radius_accounts</code>.</p>
        <div class="bg-light border rounded p-3 mb-3">
            <code>Buka halaman → Rafen konek ke MikroTik API → Ambil session aktif → Simpan ke DB → Tampilkan</code>
        </div>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Jika router tidak dapat dijangkau (offline / IP salah), session dari router tersebut akan dilewati (skip) tanpa error.</div>

        {{-- 2 --}}
        <h5 id="pppoe-session" class="border-bottom pb-2 text-info"><i class="fas fa-wifi mr-1"></i>2. Session PPPoE</h5>
        <p>Data diambil dari MikroTik via perintah API: <code>/ppp/active/print</code></p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Kolom</th><th>Sumber Data</th></tr></thead>
            <tbody>
                <tr><td>Username</td><td>Field <code>name</code> dari MikroTik</td></tr>
                <tr><td>IP Address</td><td>Field <code>address</code></td></tr>
                <tr><td>Uptime</td><td>Field <code>uptime</code></td></tr>
                <tr><td>Caller-ID</td><td>Field <code>caller-id</code> (MAC / interface)</td></tr>
                <tr><td>Upload</td><td>Tabel <code>radacct.acctinputoctets</code> (session aktif)</td></tr>
                <tr><td>Download</td><td>Tabel <code>radacct.acctoutputoctets</code> (session aktif)</td></tr>
                <tr><td>Profile</td><td>Field <code>service</code> dari MikroTik</td></tr>
            </tbody>
        </table>

        {{-- 3 --}}
        <h5 id="hotspot-session" class="border-bottom pb-2 text-info"><i class="fas fa-broadcast-tower mr-1"></i>3. Session Hotspot</h5>
        <p>Data diambil dari MikroTik via perintah API: <code>/ip/hotspot/active/print</code></p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Kolom</th><th>Sumber Data</th></tr></thead>
            <tbody>
                <tr><td>Username</td><td>Field <code>user</code> dari MikroTik</td></tr>
                <tr><td>IP Address</td><td>Field <code>address</code></td></tr>
                <tr><td>MAC Address</td><td>Field <code>mac-address</code></td></tr>
                <tr><td>Uptime</td><td>Field <code>uptime</code></td></tr>
                <tr><td>Upload</td><td>Field <code>bytes-in</code> dari MikroTik API</td></tr>
                <tr><td>Download</td><td>Field <code>bytes-out</code> dari MikroTik API</td></tr>
                <tr><td>Server</td><td>Field <code>server</code> (hotspot server di MikroTik)</td></tr>
            </tbody>
        </table>

        {{-- 4 --}}
        <h5 id="ajax-refresh" class="border-bottom pb-2 text-info"><i class="fas fa-redo mr-1"></i>4. AJAX Auto-Refresh</h5>
        <p>Tabel session diperbarui otomatis setiap <strong>60 detik</strong> menggunakan AJAX tanpa reload halaman penuh. Hanya bagian tabel yang diperbarui.</p>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Filter router yang aktif (dari dropdown) akan tetap aktif selama auto-refresh karena menggunakan <code>window.location.href</code> yang sudah menyertakan parameter filter.</div>

        {{-- 5 --}}
        <h5 id="tips-session" class="border-bottom pb-2 text-info"><i class="fas fa-lightbulb mr-1"></i>5. Tips &amp; Troubleshoot</h5>
        <table class="table table-sm table-bordered">
            <thead class="thead-light"><tr><th>Masalah</th><th>Solusi</th></tr></thead>
            <tbody>
                <tr><td>Tabel session kosong</td><td>Pastikan API MikroTik aktif dan koneksi router di Rafen sudah benar (host, user, password)</td></tr>
                <tr><td>Upload/Download selalu "-"</td><td>Pastikan FreeRADIUS mengirim data accounting ke tabel <code>radacct</code>. Cek modul <code>sql</code> di default site FreeRADIUS.</td></tr>
                <tr><td>Session tidak terupdate</td><td>Reload halaman manual. Jika tetap tidak update, cek koneksi ke router via menu Router (NAS) → Ping.</td></tr>
                <tr><td>Dashboard PPP/Hotspot Online = 0</td><td>Buka halaman Session untuk trigger sync. Atau pastikan cron sync berjalan.</td></tr>
            </tbody>
        </table>

    </div>
</div>
@endsection
