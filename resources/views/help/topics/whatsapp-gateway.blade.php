@extends('layouts.admin')

@section('title', 'Bantuan: WhatsApp Gateway')

@section('content')
@php
    $currentUser = auth()->user();
    $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
    $normalizedRole = $currentUser?->isSuperAdmin() ? 'super_admin' : (string) ($currentUser?->role ?? 'guest');

    $isSuperAdminRole = $normalizedRole === 'super_admin';
    $isWaOperatorRole = in_array($normalizedRole, ['administrator', 'noc', 'it_support', 'cs'], true);
    $isLimitedWaRole = in_array($normalizedRole, ['teknisi', 'keuangan'], true);
@endphp

<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-success">
        <h4 class="card-title mb-0"><i class="fab fa-whatsapp mr-2"></i>WhatsApp Gateway — Panduan Praktis</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#menu-baru">Struktur Menu WhatsApp Terbaru</a></li>
                <li><a href="#wizard-onboarding">Wizard Onboarding (5 Langkah)</a></li>
                <li><a href="#manajemen-device">Manajemen Device &amp; Status Koneksi</a></li>
                <li><a href="#scan-qr">Scan QR via Modal Popup</a></li>
                <li><a href="#optimasi-blast">Optimasi WA Blast Multi Device</a></li>
                <li><a href="#pola-pengiriman">Pola Pengiriman Single vs Bulk</a></li>
                <li><a href="#troubleshoot-ringkas">Troubleshoot Ringkas</a></li>
            </ol>
        </div>

        <h5 id="menu-baru" class="border-bottom pb-2"><i class="fas fa-sitemap mr-1"></i>1. Struktur Menu WhatsApp Terbaru</h5>
        <p>Masuk ke <strong>Pengaturan → WhatsApp</strong>, lalu pilih tab:</p>
        <ul>
            <li><strong>Gateway &amp; Template</strong>: koneksi gateway, toggle notifikasi otomatis, anti-spam, dan template pesan.</li>
            <li><strong>Manajemen Device</strong>: tambah device, scan QR, set default, cek/restart sesi, hapus device.</li>
        </ul>

        <h5 id="wizard-onboarding" class="border-bottom pb-2 mt-4"><i class="fas fa-route mr-1"></i>2. Wizard Onboarding (5 Langkah)</h5>
        <p>Di tab <strong>Gateway &amp; Template</strong>, ikuti wizard agar setup {{ $isSelfHostedApp ? 'instance' : 'tenant' }} tidak lompat-lompat:</p>
        <ol>
            <li>Validasi koneksi gateway.</li>
            <li>Tambah device WA {{ $isSelfHostedApp ? 'utama instance' : 'tenant' }}.</li>
            <li>Scan QR pada device.</li>
            <li>Aktifkan otomasi notifikasi &amp; blast.</li>
            <li>Uji template dan simpan.</li>
        </ol>

        <h5 id="manajemen-device" class="border-bottom pb-2 mt-4"><i class="fas fa-mobile-alt mr-1"></i>3. Manajemen Device &amp; Status Koneksi</h5>
        <p>Setiap baris device menampilkan status berikut:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light">
                <tr>
                    <th>Status</th>
                    <th>Arti</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><span class="badge badge-success">Connected</span></td><td>Device sudah terhubung dan siap kirim.</td></tr>
                <tr><td><span class="badge badge-warning">Belum Scan</span></td><td>Device belum pernah scan / sesi belum aktif.</td></tr>
                <tr><td><span class="badge badge-info">Proses Login</span></td><td>QR sudah dipindai, sistem sedang sinkronisasi ke WhatsApp.</td></tr>
                <tr><td><span class="badge badge-danger">Disconnected</span></td><td>Sesi putus, perlu scan ulang atau restart sesi.</td></tr>
            </tbody>
        </table>
        <p class="text-muted small">Status koneksi auto-refresh berkala saat tab device dibuka.</p>

        <h5 id="scan-qr" class="border-bottom pb-2 mt-4"><i class="fas fa-qrcode mr-1"></i>4. Scan QR via Modal Popup</h5>
        <ol>
            <li>Klik <strong>Scan QR</strong> pada device.</li>
            <li>Pindai QR dari WhatsApp (Perangkat Tertaut).</li>
            <li>Tunggu status berubah ke <strong>Connected</strong>; modal akan menutup otomatis.</li>
        </ol>
        <p class="text-muted small mb-0">Jika QR habis waktu, sistem akan generate ulang otomatis.</p>

        <h5 id="optimasi-blast" class="border-bottom pb-2 mt-4"><i class="fas fa-paper-plane mr-1"></i>5. Optimasi WA Blast Multi Device</h5>
        <ul>
            <li><strong>Distribusi Multi Device Aktif</strong>: kirim bergiliran (round-robin) ke device yang terhubung.</li>
            <li><strong>Failover</strong>: jika satu sesi gagal, sistem mencoba sesi lain.</li>
            <li><strong>Variasi Pesan Natural Profesional</strong>: sapaan/penutup ringan agar tidak terlalu seragam.</li>
            <li><strong>Delay Blast Min/Max</strong>: jeda acak antar pesan (dalam detik) untuk mengurangi risiko blokir.</li>
        </ul>

        <h5 id="pola-pengiriman" class="border-bottom pb-2 mt-4"><i class="fas fa-random mr-1"></i>6. Pola Pengiriman Single vs Bulk</h5>
        @if($isSuperAdminRole)
            <div class="alert alert-primary">
                <strong><i class="fas fa-crown mr-1"></i>{{ $isSelfHostedApp ? 'Mode Admin Sistem:' : 'Mode Super Admin:' }}</strong>
                {{ $isSelfHostedApp ? 'fokus Anda ada di kesehatan sesi device, template notifikasi, dan kesiapan pengiriman untuk seluruh instance.' : 'fokus Anda ada di penandaan Platform Device dan approval akses tenant ke device tersebut.' }}
            </div>
        @elseif($isWaOperatorRole)
            <div class="alert alert-success">
                <strong><i class="fas fa-user-cog mr-1"></i>Mode Operasional:</strong>
                Anda bisa kirim single/bulk sesuai izin role. Gunakan tabel ini untuk troubleshooting jalur session saat blast lambat/gagal.
            </div>
        @elseif($isLimitedWaRole)
            <div class="alert alert-warning">
                <strong><i class="fas fa-user-lock mr-1"></i>Akses Terbatas:</strong>
                role Anda umumnya tidak mengelola konfigurasi WhatsApp Gateway. Gunakan panduan ini sebagai referensi, dan koordinasikan perubahan ke Admin/NOC/IT Support.
            </div>
        @endif

        <p class="mb-2">Agar mudah audit saat ada komplain "pesan tidak masuk", gunakan ringkasan pola berikut:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light">
                <tr>
                    <th>Jenis Pengiriman</th>
                    <th>Metode</th>
                    <th>Pola Session</th>
                    <th>Role Utama</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Single message (invoice, payment, tiket, auto-reply)</td>
                    <td><code>sendMessage</code></td>
                    <td>Default ke device utama {{ $isSelfHostedApp ? 'instance' : 'tenant' }}. Dapat sticky per nomor agar konsisten.</td>
                    <td><span class="badge badge-success">Admin / NOC / IT Support / CS</span></td>
                </tr>
                <tr>
                    <td>Blast massal pelanggan</td>
                    <td><code>sendBulk</code></td>
                    <td>Round-robin antar device connected + failover + cooldown saat sesi gagal.</td>
                    <td><span class="badge badge-success">Admin / NOC / IT Support / CS</span></td>
                </tr>
                <tr>
                    <td>Pengingat shift personal</td>
                    <td><code>sendBulk</code></td>
                    <td>Sama seperti blast: bergiliran dan failover otomatis.</td>
                    <td><span class="badge badge-success">Admin</span></td>
                </tr>
                <tr>
                    <td>Ringkasan shift ke grup</td>
                    <td><code>sendGroupMessage</code></td>
                    <td>Single session aktif {{ $isSelfHostedApp ? 'instance' : 'tenant' }} (bukan round-robin).</td>
                    <td><span class="badge badge-success">Admin</span></td>
                </tr>
                <tr>
                    <td>{{ $isSelfHostedApp ? 'Notifikasi sistem (aktivasi, alarm internal, dsb.)' : 'Notifikasi platform (registrasi tenant baru, dsb.)' }}</td>
                    <td><code>{{ $isSelfHostedApp ? 'sendMessage' : 'forSuperAdmin() + sendMessage' }}</code></td>
                    <td>{{ $isSelfHostedApp ? 'Menggunakan device default atau device yang ditetapkan sebagai pengirim utama.' : 'Hanya device bertanda Platform yang dipakai.' }}</td>
                    <td><span class="badge badge-primary">Super Admin</span></td>
                </tr>
            </tbody>
        </table>

        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle mr-1"></i>Catatan penting:</strong>
            @if($isSelfHostedApp)
            Pastikan minimal ada satu device default yang sehat. Seluruh notifikasi instance akan bergantung pada sesi aktif perangkat tersebut jika tidak ada fallback device lain.
            @else
            Menandai device sebagai <strong>Platform</strong> hanya membuatnya eligible untuk notifikasi platform.
            Tenant akan benar-benar terkunci ke 1 platform device jika <code>wa_platform_device_id</code> sudah di-approve oleh Super Admin.
            @endif
        </div>

        <h5 id="troubleshoot-ringkas" class="border-bottom pb-2 mt-4"><i class="fas fa-tools mr-1"></i>7. Troubleshoot Ringkas</h5>
        <ul class="mb-0">
            <li>Jika stuck saat scan: restart sesi device lalu scan ulang.</li>
            <li>Jika status sering disconnected: cek stabilitas internet HP yang menautkan akun WA.</li>
            <li>Jika blast lambat: ini normal saat delay anti-spam/optimasi blast aktif.</li>
            <li>Jika menu terasa lambat: lakukan hard refresh browser dan pastikan build aset frontend terbaru.</li>
        </ul>
    </div>
</div>
@endsection
