@extends('layouts.admin')

@section('title', 'Pusat Bantuan')

@section('content')
@php
    $currentUser = auth()->user();
    $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
    $normalizedRole = $currentUser?->isSuperAdmin() ? 'super_admin' : (string) ($currentUser?->role ?? 'guest');

    $roleLabels = [
        'super_admin' => 'SUPER ADMIN',
        'administrator' => 'ADMIN',
        'it_support' => 'IT SUPPORT',
        'noc' => 'NOC',
        'keuangan' => 'KEUANGAN',
        'teknisi' => 'TEKNISI',
        'cs' => 'CUSTOMER SERVICES',
    ];

    $quickAccessByRole = [
        'super_admin' => $isSelfHostedApp
            ? ['Dashboard Sistem', 'Operasional Jaringan', 'Lisensi Sistem', 'Server Health', 'Terminal', 'Pengaturan Sistem']
            : ['Dashboard Tenant', 'Kelola Tenant', 'Payment Gateway', 'Server Health', 'Terminal', 'Device Request WA'],
        'administrator' => ['Dashboard', 'List Pelanggan', 'CPE Management', 'Gangguan Jaringan', 'Data Keuangan', $isSelfHostedApp ? 'Pengaturan Sistem' : 'Pengaturan Tenant'],
        'it_support' => ['Dashboard', 'Session User', 'CPE Management', 'Router (NAS)', 'Gangguan Jaringan', 'Log Aplikasi'],
        'noc' => ['Dashboard', 'Session User', 'Monitoring OLT', 'Chat WA', 'Gangguan Jaringan', 'Jadwal Shift'],
        'keuangan' => ['Data Tagihan', 'Konfirmasi Transfer', 'Data Keuangan', 'Rekonsiliasi Nota', $isSelfHostedApp ? 'Laporan Pendapatan' : 'Laporan Pendapatan Tenant'],
        'teknisi' => ['Session User', 'Monitoring OLT (Polling Sekarang)', 'Tiket Saya', 'Gangguan Jaringan', 'Jadwal Shift', 'Rekonsiliasi Nota'],
        'cs' => ['Dashboard', 'List Pelanggan', 'Chat WA', 'Tiket Pengaduan', 'WA Blast', 'Konfirmasi Transfer'],
    ];

    $currentRoleLabel = $roleLabels[$normalizedRole] ?? strtoupper(str_replace('_', ' ', $normalizedRole));
    $currentQuickAccess = $quickAccessByRole[$normalizedRole] ?? [];
@endphp

<div class="row">
    <div class="col-12 mb-3">
        <div class="card card-primary card-outline">
            <div class="card-body py-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div class="mb-3 mb-md-0">
                        <h3 class="mb-1"><i class="fas fa-life-ring text-primary mr-2"></i>Pusat Bantuan RAFEN</h3>
                        <p class="text-muted mb-0">Panduan rinci per menu, alur kerja operasional, dan ringkasan akses per role.</p>
                    </div>
                    <div class="w-100" style="max-width: 360px;">
                        <label for="help-topic-search" class="small text-muted mb-1 d-block">Cari topik bantuan</label>
                        <input type="search" id="help-topic-search" class="form-control" placeholder="Contoh: OLT, invoice, WA, keuangan">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card border-info">
            <div class="card-header bg-info text-white py-2">
                <strong><i class="fas fa-user-shield mr-1"></i>Ringkasan akses untuk role Anda: {{ $currentRoleLabel }}</strong>
            </div>
            <div class="card-body py-3">
                @if($currentQuickAccess === [])
                    <p class="mb-0 text-muted">Role ini belum memiliki ringkasan khusus. Buka menu <strong>Panduan Per Role</strong> untuk detail akses.</p>
                @else
                    <div class="d-flex flex-wrap" style="gap: .45rem;">
                        @foreach($currentQuickAccess as $access)
                            <span class="badge badge-pill badge-light border px-3 py-2">{{ $access }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mb-2">
    <div class="col-12">
        <div class="card card-outline card-dark">
            <div class="card-body py-4">
                <div class="d-flex align-items-start">
                    <div class="mr-4 d-none d-md-block">
                        <div style="width:56px;height:56px;background:linear-gradient(135deg,#0a3e68,#0c8a8f);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-network-wired fa-lg text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="mb-1 font-weight-bold">Rafen — Radius &amp; Network Management</h5>
                        <p class="text-muted mb-2" style="max-width:720px;line-height:1.65;">
                            Rafen adalah platform manajemen ISP berbasis web yang mengintegrasikan <strong>FreeRADIUS</strong>, <strong>MikroTik</strong>, <strong>WireGuard VPN</strong>, dan <strong>GenieACS (TR-069)</strong> dalam satu dasbor terpusat.
                            Dirancang untuk operator internet skala kecil hingga menengah, Rafen memudahkan pengelolaan pelanggan PPPoE &amp; Hotspot, pembuatan tagihan otomatis, monitoring OLT/ONU via SNMP, notifikasi WhatsApp, hingga manajemen shift teknisi{{ $isSelfHostedApp ? ' dalam satu instance self-hosted yang terkontrol.' : ' — semua dengan model multi user yang aman.' }}
                        </p>
                        <div class="d-flex flex-wrap" style="gap:.4rem;">
                            <span class="badge badge-primary px-2 py-1">FreeRADIUS 3</span>
                            <span class="badge badge-secondary px-2 py-1">MikroTik RouterOS</span>
                            <span class="badge badge-info px-2 py-1">WireGuard VPN</span>
                            <span class="badge badge-success px-2 py-1">GenieACS TR-069</span>
                            <span class="badge badge-warning px-2 py-1">OLT / SNMP</span>
                            <span class="badge badge-danger px-2 py-1">WhatsApp Gateway</span>
                            <span class="badge badge-dark px-2 py-1">{{ $isSelfHostedApp ? 'Self-Hosted' : 'Multi-Tenant' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="panduan role akses super admin admin noc keuangan teknisi it support izin menu">
        <a href="{{ route('help.topic', 'panduan-role') }}" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-user-tag fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">Panduan Per Role</h5>
                    <p class="text-muted small mb-0">Penjelasan hak akses dan alur kerja untuk setiap role pengguna di RAFEN.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="fitur operasional menu dashboard pelanggan router olt invoice keuangan pengaturan">
        <a href="{{ route('help.topic', 'fitur-operasional') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-project-diagram fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">Peta Fitur Operasional</h5>
                    <p class="text-muted small mb-0">Daftar semua fitur utama RAFEN, fungsi bisnis, dan langkah penggunaan praktis.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="faq pertanyaan umum wa whatsapp wizard onboarding qr device blast multi device timeout snmp isolir invoice pembayaran session">
        <a href="{{ route('help.topic', 'faq') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-question-circle fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">FAQ Operasional</h5>
                    <p class="text-muted small mb-0">Jawaban cepat untuk pertanyaan yang paling sering ditanyakan tim operasional.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca FAQ <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="whatsapp gateway wa wizard onboarding device scan qr blast multi device template anti spam">
        <a href="{{ route('help.topic', 'whatsapp-gateway') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fab fa-whatsapp fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">WhatsApp Gateway</h5>
                    <p class="text-muted small mb-0">Panduan khusus setup wizard, manajemen device, scan QR, dan optimasi WA Blast.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="freeradius radius sql clients nas radcheck radreply simultaneous use">
        <a href="{{ route('help.topic', 'freeradius') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-broadcast-tower fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">FreeRADIUS</h5>
                    <p class="text-muted small mb-0">Konfigurasi SQL module, sinkronisasi klien, atribut radcheck &amp; radreply.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="hotspot profil user voucher shared users multi device radius">
        <a href="{{ route('help.topic', 'hotspot') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-broadcast-tower fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">Hotspot</h5>
                    <p class="text-muted small mb-0">Profil hotspot, shared users (multi-device), voucher, dan sinkronisasi RADIUS.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="pppoe ppp user profil paket rate limit session">
        <a href="{{ route('help.topic', 'pppoe') }}" class="text-decoration-none">
            <div class="card card-outline card-info h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-wifi fa-2x text-info mb-3"></i>
                    <h5 class="card-title mb-1">PPPoE</h5>
                    <p class="text-muted small mb-0">User PPP, sinkronisasi radcheck, rate limit, dan session aktif.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="wireguard vpn peer tunnel mikrotik nas">
        <a href="{{ route('help.topic', 'wireguard') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">WireGuard VPN</h5>
                    <p class="text-muted small mb-0">Konfigurasi VPN server, peer MikroTik, dan tunneling ke router.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="voucher batch print status expired sinkronisasi radius">
        <a href="{{ route('help.topic', 'voucher') }}" class="text-decoration-none">
            <div class="card card-outline card-secondary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-ticket-alt fa-2x text-secondary mb-3"></i>
                    <h5 class="card-title mb-1">Voucher</h5>
                    <p class="text-muted small mb-0">Pembuatan batch voucher, cetak, penggunaan, dan masa berlaku.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="profil paket bandwidth profile group hotspot ppp pool">
        <a href="{{ route('help.topic', 'profil-paket') }}" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-box fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">Profil Paket</h5>
                    <p class="text-muted small mb-0">Bandwidth, profil group (IP pool), profil hotspot &amp; PPP.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="invoice tagihan due date pembayaran wa jatuh tempo isolir">
        <a href="{{ route('help.topic', 'invoice') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">Tagihan (Invoice)</h5>
                    <p class="text-muted small mb-0">Generate invoice otomatis, alur pembayaran, dan mekanisme jatuh tempo.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="session pppoe hotspot monitoring refresh sinkronisasi router">
        <a href="{{ route('help.topic', 'session') }}" class="text-decoration-none">
            <div class="card card-outline card-info h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-signal fa-2x text-info mb-3"></i>
                    <h5 class="card-title mb-1">Session User</h5>
                    <p class="text-muted small mb-0">Monitoring session aktif PPPoE &amp; Hotspot, auto-sync MikroTik.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="troubleshoot error gagal timeout radius sync export session log">
        <a href="{{ route('help.topic', 'troubleshoot') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-bug fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">Troubleshooting</h5>
                    <p class="text-muted small mb-0">Masalah umum: login gagal, session kosong, RADIUS tidak merespon, dll.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="pelanggan infrastruktur peta pelanggan odp koordinat map odc alamat coverage">
        <a href="{{ route('help.topic', 'pelanggan-infrastruktur') }}" class="text-decoration-none">
            <div class="card card-outline card-info h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-map-marked-alt fa-2x text-info mb-3"></i>
                    <h5 class="card-title mb-1">Pelanggan & ODP</h5>
                    <p class="text-muted small mb-0">Panduan peta pelanggan, data ODP, koordinat, dan pemetaan infrastruktur lapangan.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="cpe genieacs tr069 modem onu wifi reboot pppoe provisioning unlinked">
        <a href="{{ route('help.topic', 'cpe-genieacs') }}" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-network-wired fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">CPE & GenieACS</h5>
                    <p class="text-muted small mb-0">Kelola modem/CPE, proses link perangkat, reboot, WiFi, dan integrasi GenieACS.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="chat wa whatsapp inbox tiket pengaduan assign teknisi cs noc reply resolve">
        <a href="{{ route('help.topic', 'chat-wa-ticketing') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-comments fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">Chat WA & Tiket</h5>
                    <p class="text-muted small mb-0">Alur inbox pelanggan, tiket pengaduan, assignment teknisi, dan eskalasi tim.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="gangguan jaringan outage insiden blast terdampak assign teknisi status publik">
        <a href="{{ route('help.topic', 'gangguan-jaringan') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-broadcast-tower fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">Gangguan Jaringan</h5>
                    <p class="text-muted small mb-0">Catat insiden, preview pelanggan terdampak, blast notifikasi, dan tindak lanjut teknisi.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="shift jadwal reminder tukar shift swap teknisi noc cs admin">
        <a href="{{ route('help.topic', 'jadwal-shift') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-calendar-alt fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">Jadwal Shift</h5>
                    <p class="text-muted small mb-0">Kelola jadwal, definisi shift, reminder, dan review permintaan tukar shift.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    @if(! $isSelfHostedApp)
    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="wallet saldo withdrawal penarikan bank account tenant platform gateway settlement">
        <a href="{{ route('help.topic', 'wallet-withdrawal') }}" class="text-decoration-none">
            <div class="card card-outline card-secondary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-wallet fa-2x text-secondary mb-3"></i>
                    <h5 class="card-title mb-1">Wallet & Withdrawal</h5>
                    <p class="text-muted small mb-0">Panduan saldo wallet tenant, histori transaksi, dan proses penarikan dana.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>
    @endif

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="tool sistem audit backup restore import export log genieacs radius wa activity login">
        <a href="{{ route('help.topic', 'tool-sistem-audit') }}" class="text-decoration-none">
            <div class="card card-outline card-dark h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-tools fa-2x text-dark mb-3"></i>
                    <h5 class="card-title mb-1">Tool Sistem & Audit</h5>
                    <p class="text-muted small mb-0">Cek pemakaian, impor/ekspor, backup, serta panduan membaca log aplikasi penting.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="{{ $isSelfHostedApp ? 'super admin self hosted lisensi server health terminal pengaturan sistem' : 'super admin tenant payment gateway saldo penarikan server health terminal email device request platform' }}">
        <a href="{{ route('help.topic', $isSelfHostedApp ? 'panduan-role' : 'super-admin-platform') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-crown fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">{{ $isSelfHostedApp ? 'Panduan Admin Sistem' : 'Operasional Super Admin' }}</h5>
                    <p class="text-muted small mb-0">{{ $isSelfHostedApp ? 'Ringkasan tugas admin utama self-hosted: lisensi, kesehatan server, terminal, dan pengaturan instance.' : 'Kelola tenant, platform payment, server health, terminal, dan approval device platform.' }}</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="ketentuan layanan terms of service legal aturan penggunaan syarat">
        <a href="{{ route('terms-of-service') }}" target="_blank" rel="noopener" class="text-decoration-none">
            <div class="card card-outline card-secondary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-contract fa-2x text-secondary mb-3"></i>
                    <h5 class="card-title mb-1">Ketentuan Layanan</h5>
                    <p class="text-muted small mb-0">Syarat dan ketentuan penggunaan layanan RAFEN.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Buka halaman <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="kebijakan privasi privacy policy data pribadi perlindungan data">
        <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-user-shield fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">Kebijakan Privasi</h5>
                    <p class="text-muted small mb-0">Penjelasan penggunaan dan perlindungan data pribadi pengguna.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Buka halaman <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-12 d-none" id="help-empty-state">
        <div class="alert alert-warning">
            <i class="fas fa-search mr-1"></i>Tidak ada topik yang cocok. Coba kata kunci lain, misalnya <code>invoice</code>, <code>OLT</code>, <code>WA</code>, atau <code>keuangan</code>.
        </div>
    </div>
</div>

<style>
.help-card { transition: transform .15s, box-shadow .15s; }
.help-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('help-topic-search');
    const topicCards = Array.from(document.querySelectorAll('.help-topic-card[data-help-topic]'));
    const emptyState = document.getElementById('help-empty-state');

    if (!searchInput || topicCards.length === 0) {
        return;
    }

    const normalize = (value) => (value || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const synonymMap = {
        wa: ['whatsapp'],
        whatsapp: ['wa'],
        invoice: ['tagihan', 'billing'],
        tagihan: ['invoice', 'billing'],
        pembayaran: ['bayar', 'payment'],
        bayar: ['pembayaran', 'payment'],
        olt: ['onu', 'snmp'],
        onu: ['olt', 'snmp'],
        radius: ['freeradius'],
        freeradius: ['radius'],
    };

    const indexedCards = topicCards.map((card, index) => {
        const title = normalize(card.querySelector('.card-title')?.textContent || '');
        const description = normalize(card.querySelector('.card-body p')?.textContent || '');
        const keywords = normalize(card.dataset.helpKeywords || '');
        const haystack = [title, description, keywords].filter(Boolean).join(' ');
        const keywordSet = new Set(keywords.split(' ').filter(Boolean));

        return {
            card,
            index,
            title,
            description,
            keywords,
            haystack,
            keywordSet,
        };
    });

    const expandToken = (token) => {
        const aliases = synonymMap[token] || [];
        return [token].concat(aliases);
    };

    const scoreEntry = (entry, tokens, wholeQuery) => {
        let score = 0;

        if (wholeQuery !== '' && entry.haystack.includes(wholeQuery)) {
            score += 8;
        }

        for (const token of tokens) {
            const variants = expandToken(token);
            let matched = false;

            for (const variant of variants) {
                if (entry.keywordSet.has(variant)) {
                    score += 6;
                    matched = true;
                    break;
                }
                if (entry.title.includes(variant)) {
                    score += 5;
                    matched = true;
                    break;
                }
                if (entry.description.includes(variant)) {
                    score += 3;
                    matched = true;
                    break;
                }
                if (entry.haystack.includes(variant)) {
                    score += 1;
                    matched = true;
                    break;
                }
            }

            // Semua token harus punya kecocokan agar hasil tetap relevan
            if (!matched) {
                return -1;
            }
        }

        return score;
    };

    const applyFilter = () => {
        const wholeQuery = normalize(searchInput.value);
        const tokens = wholeQuery.split(' ').filter((token) => token.length >= 2);

        // Tanpa keyword: tampilkan semua sesuai urutan awal
        if (tokens.length === 0) {
            indexedCards.forEach((entry) => {
                entry.card.style.display = '';
                entry.card.style.order = String(entry.index);
            });
            if (emptyState) {
                emptyState.classList.add('d-none');
            }
            return;
        }

        const ranked = indexedCards
            .map((entry) => ({
                entry,
                score: scoreEntry(entry, tokens, wholeQuery),
            }))
            .filter((item) => item.score >= 0)
            .sort((a, b) => {
                if (b.score === a.score) {
                    return a.entry.index - b.entry.index;
                }
                return b.score - a.score;
            });

        indexedCards.forEach((entry) => {
            entry.card.style.display = 'none';
        });

        ranked.forEach((item, position) => {
            item.entry.card.style.display = '';
            item.entry.card.style.order = String(position);
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', ranked.length !== 0);
        }
    };

    searchInput.addEventListener('input', applyFilter);
    applyFilter();
});
</script>
@endsection
