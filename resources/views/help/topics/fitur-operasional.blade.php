@extends('layouts.admin')

@section('title', 'Bantuan: Peta Fitur Operasional')

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
    <div class="card-header bg-success text-white">
        <h4 class="card-title mb-0"><i class="fas fa-project-diagram mr-2"></i>Peta Fitur Operasional RAFEN</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#siklus-harian">Siklus Kerja Harian yang Disarankan</a></li>
                <li><a href="#fitur-utama">Ringkasan Semua Fitur Utama</a></li>
                <li><a href="#alur-pelanggan">Alur Lengkap dari Registrasi sampai Pelunasan</a></li>
                <li><a href="#kontrol-risiko">Kontrol Risiko Operasional</a></li>
            </ol>
        </div>

        <h5 id="siklus-harian" class="border-bottom pb-2"><i class="fas fa-clock mr-1"></i>1. Siklus Kerja Harian yang Disarankan</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-header bg-primary text-white py-2"><strong>Pagi (Monitoring)</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Cek <strong>Dashboard</strong> untuk ringkasan pelanggan online/offline dan status pembayaran.</li>
                            <li>Buka <strong>Session User</strong> agar data PPPoE/Hotspot auto-sync dari MikroTik.</li>
                            <li>Buka <strong>Monitoring OLT</strong> untuk validasi status PON/ONU dan kualitas optik.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-success h-100">
                    <div class="card-header bg-success text-white py-2"><strong>Siang (Operasional)</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Kelola pelanggan baru di <strong>List Pelanggan</strong> (PPPoE/Hotspot/Voucher).</li>
                            <li>Pastikan paket sudah benar di <strong>Profil Paket</strong>.</li>
                            <li>Verifikasi kondisi router di <strong>Router (NAS)</strong>.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-header bg-warning py-2"><strong>Sore (Billing)</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Proses <strong>Data Tagihan</strong>: invoice jatuh tempo, konfirmasi transfer, reminder WA.</li>
                            <li>Tutup kas teknisi di <strong>Rekonsiliasi Nota</strong>.</li>
                            <li>Review pendapatan/pengeluaran di <strong>Data Keuangan</strong>.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-header bg-info text-white py-2"><strong>Malam (Pemeliharaan)</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Cek <strong>Log Aplikasi</strong> untuk error autentikasi, BG process, dan WA blast.</li>
                            <li>Pastikan cron/scheduler berjalan untuk generate invoice dan sinkronisasi berkala.</li>
                            <li>Jika perlu, gunakan <strong>Tool Sistem</strong> (import/export/backup) sesuai otorisasi.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <h5 id="fitur-utama" class="border-bottom pb-2 mt-2"><i class="fas fa-th-list mr-1"></i>2. Ringkasan Semua Fitur Utama</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Menu</th>
                        <th>Fungsi Inti</th>
                        <th>Output yang Diharapkan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Dashboard</strong></td>
                        <td>{{ $isSelfHostedApp ? 'Ringkasan operasional instance: pelanggan, session, tagihan, dan performa singkat.' : 'Ringkasan operasional tenant: pelanggan, session, tagihan, performa singkat.' }}</td>
                        <td>Keputusan cepat untuk prioritas harian.</td>
                    </tr>
                    <tr>
                        <td><strong>Session User</strong></td>
                        <td>Menampilkan user PPPoE/Hotspot aktif dan tidak aktif, plus sync data realtime dari router.</td>
                        <td>Status koneksi pelanggan selalu up-to-date tanpa reload penuh.</td>
                    </tr>
                    <tr>
                        <td><strong>List Pelanggan</strong></td>
                        <td>Kelola user PPPoE, user hotspot, voucher, peta pelanggan, dan ODP.</td>
                        <td>Data pelanggan tertata dan siap ditagih/diisolir.</td>
                    </tr>
                    <tr>
                        <td><strong>Router (NAS)</strong></td>
                        <td>Manajemen koneksi MikroTik, ping, sync RADIUS client, maintenance API.</td>
                        <td>Router stabil, terhubung, dan siap sinkronisasi autentikasi.</td>
                    </tr>
                    <tr>
                        <td><strong>Monitoring OLT</strong></td>
                        <td>Polling SNMP OLT/ONU, status PON, nilai optic, deteksi gangguan akses fiber.</td>
                        <td>Trouble fiber lebih cepat ditemukan dan ditindak.</td>
                    </tr>
                    <tr>
                        <td><strong>CPE Management</strong></td>
                        <td>Kelola modem/CPE via GenieACS: link perangkat, reboot, update WiFi, dan sinkronisasi parameter.</td>
                        <td>Troubleshooting perangkat pelanggan lebih cepat dan terukur.</td>
                    </tr>
                    <tr>
                        <td><strong>Profil Paket</strong></td>
                        <td>Master data bandwidth, profile group, profile PPP, profile hotspot.</td>
                        <td>Paket layanan konsisten, mudah dipilih saat registrasi.</td>
                    </tr>
                    <tr>
                        <td><strong>Data Tagihan</strong></td>
                        <td>Generate invoice, payment confirm, perpanjangan tanpa bayar, kirim WA reminder.</td>
                        <td>Arus kas tertagih tepat waktu dan status pelanggan jelas.</td>
                    </tr>
                    <tr>
                        <td><strong>Rekonsiliasi Nota</strong></td>
                        <td>Pencatatan setoran teknisi, verifikasi tunai setor, ringkasan total.</td>
                        <td>Audit kas lapangan transparan.</td>
                    </tr>
                    <tr>
                        <td><strong>Data Keuangan</strong></td>
                        <td>Income harian/periode, pengeluaran, laba rugi, hitung BHP/USO.</td>
                        <td>Laporan keuangan siap evaluasi manajemen.</td>
                    </tr>
                    <tr>
                        <td><strong>Tool Sistem</strong></td>
                        <td>Import/export data, cek pemakaian, backup/restore, reset laporan (khusus).</td>
                        <td>Operasional skala besar lebih efisien dan terkontrol.</td>
                    </tr>
                    <tr>
                        <td><strong>Log Aplikasi</strong></td>
                        <td>Log login, aktivitas, background process, auth RADIUS, WA blast.</td>
                        <td>Jejak audit lengkap dan mudah ditelusuri saat insiden.</td>
                    </tr>
                    <tr>
                        <td><strong>Chat WA &amp; Tiket</strong></td>
                        <td>Inbox pelanggan, assignment percakapan, tiket pengaduan, catatan teknisi, dan eskalasi layanan.</td>
                        <td>Komunikasi pelanggan lebih terpusat dan tindak lanjut antar tim lebih jelas.</td>
                    </tr>
                    <tr>
                        <td><strong>Gangguan Jaringan</strong></td>
                        <td>Pencatatan outage, preview pelanggan terdampak, blast notifikasi, assignment teknisi, dan update penyelesaian.</td>
                        <td>Insiden jaringan lebih cepat diinformasikan dan dikendalikan.</td>
                    </tr>
                    <tr>
                        <td><strong>Jadwal Shift</strong></td>
                        <td>Definisi shift, penjadwalan staf, reminder, dan review permintaan tukar shift.</td>
                        <td>Distribusi personel operasional lebih tertata.</td>
                    </tr>
                    @if(! $isSelfHostedApp)
                    <tr>
                        <td><strong>Wallet Saldo</strong></td>
                        <td>Saldo tenant pengguna Platform Gateway, histori transaksi, dan pengajuan penarikan.</td>
                        <td>Arus dana platform lebih transparan bagi tenant.</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Pengaturan</strong></td>
                        <td>{{ $isSelfHostedApp ? 'Pengaturan sistem, WA Gateway (wizard onboarding, manajemen device, scan QR modal, status koneksi live, optimasi blast multi-device), FreeRADIUS, WireGuard, dan manajemen pengguna.' : 'Tenant settings, WA Gateway (wizard onboarding, manajemen device, scan QR modal, status koneksi live, optimasi blast multi-device), FreeRADIUS, WireGuard, manajemen pengguna.' }}</td>
                        <td>{{ $isSelfHostedApp ? 'Konfigurasi instance terstandarisasi, onboarding lebih cepat, dan operasional WA lebih stabil.' : 'Konfigurasi tenant terstandarisasi, onboarding lebih cepat, dan operasional WA lebih stabil.' }}</td>
                    </tr>
                    @if(! $isSelfHostedApp)
                    <tr>
                        <td><strong>Langganan</strong></td>
                        <td>Status langganan tenant, riwayat, perpanjangan paket.</td>
                        <td>Akses fitur tetap aktif sesuai paket/langganan.</td>
                    </tr>
                    @else
                    <tr>
                        <td><strong>Lisensi Sistem</strong></td>
                        <td>Status aktivasi self-hosted, mode akses host/IP, grace period, dan verifikasi file lisensi.</td>
                        <td>Instance tetap aktif dan kepatuhan lisensi mudah dipantau.</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Super Admin</strong></td>
                        <td>{{ $isSelfHostedApp ? 'Kelola lisensi sistem, payment gateway lokal, server health, terminal, dan pengaturan global instance.' : 'Kelola tenant, payment gateway global, laporan pendapatan platform, server health, terminal, dan approval device platform.' }}</td>
                        <td>{{ $isSelfHostedApp ? 'Governance self-hosted tetap konsisten dan mudah diaudit.' : 'Governance multi-tenant berjalan konsisten.' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h5 id="alur-pelanggan" class="border-bottom pb-2"><i class="fas fa-route mr-1"></i>3. Alur Lengkap dari Registrasi sampai Pelunasan</h5>
        <div class="alert alert-light border mb-3">
            <strong>Alur standar yang disarankan:</strong>
            <ol class="mt-2 mb-0">
                <li>Siapkan profil layanan di <strong>Profil Paket</strong>.</li>
                <li>Tambahkan router/validasi koneksi di <strong>Router (NAS)</strong>.</li>
                <li>Registrasi pelanggan di <strong>List Pelanggan</strong> dan pilih paket yang tepat.</li>
                <li>Sistem membuat invoice sesuai periode/billing cycle.</li>
                <li>Di <strong>Pengaturan → WhatsApp</strong>, ikuti wizard onboarding: validasi koneksi, tambah device, scan QR, lalu aktifkan otomasi.</li>
                <li>Kirim pengingat melalui WA (manual/otomatis sesuai aturan {{ $isSelfHostedApp ? 'sistem' : 'tenant' }}), gunakan multi-device blast untuk membagi beban kirim.</li>
                <li>Jika belum lunas melewati jatuh tempo, status bisa masuk mekanisme isolir.</li>
                <li>Jika bayar, konfirmasi pembayaran dan status layanan kembali normal/lunas.</li>
                <li>Akhiri hari dengan rekonsiliasi setoran dan review laporan keuangan.</li>
            </ol>
        </div>

        <h5 id="kontrol-risiko" class="border-bottom pb-2"><i class="fas fa-shield-alt mr-1"></i>4. Kontrol Risiko Operasional</h5>
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Risiko</th>
                    <th>Kontrol di RAFEN</th>
                    <th>Frekuensi Cek</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tagihan tertunda tidak tertagih</td>
                    <td>Monitor invoice overdue + reminder WA + status isolir</td>
                    <td>Harian</td>
                </tr>
                <tr>
                    <td>Data session tidak sinkron</td>
                    <td>Buka Session User (auto sync), gunakan tombol refresh manual bila perlu</td>
                    <td>Harian / saat keluhan masuk</td>
                </tr>
                <tr>
                    <td>Router/API down</td>
                    <td>Monitoring Router (NAS), ping, verifikasi service API MikroTik</td>
                    <td>Harian</td>
                </tr>
                <tr>
                    <td>Gangguan fiber OLT tidak cepat terdeteksi</td>
                    <td>Polling OLT berkala, cek nilai optic PON/ONU, alarm warna</td>
                    <td>Harian dan saat insiden</td>
                </tr>
                <tr>
                    <td>Selisih setoran teknisi</td>
                    <td>Rekonsiliasi Nota dengan total tagihan vs tunai setor</td>
                    <td>Harian</td>
                </tr>
                <tr>
                    <td>Perubahan konfigurasi tanpa jejak</td>
                    <td>Audit melalui Log Aplikasi dan pembatasan role</td>
                    <td>Harian / mingguan</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
