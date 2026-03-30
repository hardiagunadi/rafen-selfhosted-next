@extends('layouts.admin')

@section('title', 'Bantuan: Panduan Per Role')

@section('content')
@php
    $currentUser = auth()->user();
    $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
    $currentRole = $currentUser?->isSuperAdmin() ? 'super_admin' : (string) ($currentUser?->role ?? 'guest');
    $roleLabels = [
        'super_admin' => 'Super Admin',
        'administrator' => 'Admin',
        'it_support' => 'IT Support',
        'noc' => 'NOC',
        'keuangan' => 'Keuangan',
        'teknisi' => 'Teknisi',
        'cs' => 'Customer Services',
    ];
@endphp

<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-user-tag mr-2"></i>Panduan Per Role</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Role Anda saat ini:</strong>
            <span class="badge badge-light border ml-1">{{ $roleLabels[$currentRole] ?? strtoupper(str_replace('_', ' ', $currentRole)) }}</span>
            <span class="ml-2 text-muted">Gunakan panduan ini sebagai standar operasional tiap role.</span>
        </div>

        <h5 class="border-bottom pb-2"><i class="fas fa-table mr-1"></i>Matriks Akses Fitur</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Menu / Fitur</th>
                        <th>Super Admin</th>
                        <th>Admin</th>
                        <th>IT Support</th>
                        <th>NOC</th>
                        <th>Keuangan</th>
                        <th>Teknisi</th>
                        <th>CS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dashboard</td>
                        <td>{{ $isSelfHostedApp ? 'Dashboard sistem' : 'Semua tenant' }}</td>
                        <td>Penuh</td>
                        <td>Penuh</td>
                        <td>Penuh</td>
                        <td>Terbatas</td>
                        <td>Terbatas</td>
                        <td>Penuh</td>
                    </tr>
                    <tr>
                        <td>Session User (PPPoE/Hotspot)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>List Pelanggan (PPPoE/Hotspot/Voucher)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Baca + tagihan</td>
                        <td>Operasional lapangan</td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Router (NAS)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Lihat status</td>
                        <td>Lihat status</td>
                        <td>Lihat status</td>
                    </tr>
                    <tr>
                        <td>Monitoring OLT</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td>Polling Sekarang</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Profil Paket</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td>Terbatas (tanpa kelola)</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Data Tagihan / Invoice</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Tanpa hapus + tanpa nota</td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Konfirmasi Transfer</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>WA Blast</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Rekonsiliasi Nota</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Data Keuangan</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Terbatas (difilter ke teknisi sendiri)</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Tool Sistem</td>
                        <td>Penuh (termasuk reset)</td>
                        <td>Import/Export/Usage</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Log Aplikasi</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>{{ $isSelfHostedApp ? 'Pengaturan Sistem / WA Gateway / FreeRADIUS / WG' : 'Pengaturan Tenant / WA Gateway / FreeRADIUS / WG' }}</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Lihat sesuai kebutuhan</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>{{ $isSelfHostedApp ? 'Dashboard Sistem + Lisensi Sistem' : 'Super Admin Dashboard + Kelola Tenant' }}</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h5 class="border-bottom pb-2"><i class="fas fa-layer-group mr-1"></i>Fitur Tambahan Sesuai Modul</h5>
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-header bg-info text-white py-2"><strong>{{ $isSelfHostedApp ? 'Operasional Sistem' : 'Operasional Tenant' }}</strong></div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>CPE Management</strong>: umumnya untuk Admin, NOC, dan IT Support.</li>
                            <li><strong>Chat WA &amp; Tiket Pengaduan</strong>: Admin, NOC, IT Support, CS; teknisi fokus pada tiket yang di-assign.</li>
                            <li><strong>Gangguan Jaringan</strong>: Admin/NOC/IT Support mengelola insiden, CS/Teknisi memberi update lapangan.</li>
                            <li><strong>Jadwal Shift</strong>: muncul jika modul shift aktif; Admin kelola jadwal, role lain melihat jadwal masing-masing.</li>
                            <li><strong>{{ $isSelfHostedApp ? 'Lisensi Sistem' : 'Wallet Saldo' }}</strong>: {{ $isSelfHostedApp ? 'dipakai admin utama untuk memantau status aktivasi dan grace period instance.' : 'hanya muncul untuk tenant yang memakai Platform Gateway dan bukan akun sub-user.' }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-danger h-100">
                    <div class="card-header bg-danger text-white py-2"><strong>{{ $isSelfHostedApp ? 'Administrasi Sistem' : 'Operasional Super Admin' }}</strong></div>
                    <div class="card-body">
                        <ul class="mb-0">
                            @if($isSelfHostedApp)
                            <li><strong>Lisensi Sistem</strong>, <strong>Server Health</strong>, dan <strong>Terminal</strong> dipakai admin utama untuk menjaga instance tetap sehat.</li>
                            <li><strong>WA Gateway</strong> dikelola langsung di instance ini tanpa approval device platform.</li>
                            <li><strong>Pengaturan Email</strong> dan parameter bisnis berlaku untuk seluruh instance, bukan tenant terpisah.</li>
                            <li><strong>Tool Sistem</strong> dipakai untuk backup, audit, dan troubleshooting terkontrol.</li>
                            @else
                            <li><strong>Payment Gateway</strong>, <strong>Saldo Tenant</strong>, dan <strong>Penarikan Saldo</strong> khusus Super Admin.</li>
                            <li><strong>Server Health</strong> dan <strong>Terminal</strong> dipakai untuk operasi platform dan troubleshooting server.</li>
                            <li><strong>Device Request WA</strong> dipakai untuk approve/revoke device platform milik tenant.</li>
                            <li><strong>Pengaturan Email</strong> mengatur email platform global, bukan email per tenant.</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="border-bottom pb-2"><i class="fas fa-clipboard-check mr-1"></i>Standar Kerja Tiap Role</h5>
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-header bg-warning py-2"><strong>Super Admin</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            @if($isSelfHostedApp)
                            <li>Pastikan lisensi sistem aktif, host/IP sesuai, dan grace period terpantau.</li>
                            <li>Kelola akun administrator utama serta batas akses operasional internal.</li>
                            <li>Pastikan service inti seperti FreeRADIUS, WA Gateway, dan backup berjalan sehat.</li>
                            <li>Review kesehatan server, terminal, dan log untuk kebutuhan troubleshooting cepat.</li>
                            @else
                            <li>Kelola tenant baru, pilih metode langganan bulanan atau lisensi tahunan.</li>
                            <li>Pastikan tenant kategori adalah akun <code>role = administrator</code>.</li>
                            <li>Atur limit lisensi tenant (Mikrotik/PPP user) sesuai kontrak.</li>
                            <li>Pantau pendapatan global dan status pembayaran langganan tenant.</li>
                            @endif
                            <li>Gunakan tool reset/backup hanya untuk kondisi insiden terkontrol.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-header bg-primary text-white py-2"><strong>{{ $isSelfHostedApp ? 'Admin Sistem' : 'Admin Tenant' }}</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Setup Router (NAS), profil paket, dan data pelanggan.</li>
                            <li>Pantau dashboard, session user, dan Monitoring OLT setiap hari.</li>
                            <li>Kelola invoice, konfirmasi pembayaran, dan isolir jatuh tempo.</li>
                            <li>Atur WA Gateway lewat wizard onboarding (koneksi, device, scan QR), template notifikasi, dan {{ $isSelfHostedApp ? 'pengaturan sistem' : 'pengaturan tenant' }}.</li>
                            <li>Review laporan keuangan harian/periode sebagai kontrol bisnis.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-header bg-info text-white py-2"><strong>NOC &amp; IT Support</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Fokus pada stabilitas jaringan, autentikasi, session, dan log.</li>
                            <li>NOC memantau OLT dan kualitas optik; lakukan polling saat alarm muncul.</li>
                            <li>IT Support menangani sinkronisasi RADIUS, router API, dan issue user massal.</li>
                            <li>Gunakan halaman Troubleshooting sebagai alur diagnosis berurutan.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-success h-100">
                    <div class="card-header bg-success text-white py-2"><strong>Keuangan &amp; Teknisi</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Keuangan fokus ke invoice, konfirmasi transfer, pengeluaran, laba rugi, BHP/USO.</li>
                            <li>Teknisi fokus ke eksekusi lapangan: monitoring OLT, tiket/gangguan yang di-assign, penagihan, rekonsiliasi nota.</li>
                            <li>Pada role teknisi, aksi sensitif seperti hapus invoice/nota tidak ditampilkan.</li>
                            <li>Laporan keuangan untuk teknisi bersifat terbatas dan difilter ke transaksi teknisi sendiri.</li>
                            <li>Validasi setoran tunai harian dan cocokkan dengan Rekonsiliasi Nota.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-secondary h-100">
                    <div class="card-header bg-secondary text-white py-2"><strong>Customer Services (CS)</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Layani pelanggan: cek status akun PPPoE/Hotspot, update data kontak, dan aktifkan/nonaktifkan akun.</li>
                            <li>Cek dan kirim tagihan (invoice) ke pelanggan via WA.</li>
                            <li>Konfirmasi transfer/pembayaran dari pelanggan.</li>
                            <li>Gunakan Chat WA, Tiket Pengaduan, dan WA Blast untuk follow-up pelanggan.</li>
                            <li>Pantau log pengiriman WA dan aktivitas pelanggan.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mb-0">
            <strong>Catatan:</strong> Jika ada menu yang tidak muncul, cek role akun dan status modul {{ $isSelfHostedApp ? 'sistem' : 'tenant' }} terlebih dahulu (misalnya modul hotspot atau hak akses role).
        </div>
    </div>
</div>
@endsection
