@extends('layouts.admin')

@section('title', 'Bantuan: Tool Sistem & Audit')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h4 class="card-title mb-0"><i class="fas fa-tools mr-2"></i>Tool Sistem & Audit Log</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#tools">Tool Sistem</a></li>
                <li><a href="#backup">Backup dan Reset</a></li>
                <li><a href="#log">Log Aplikasi</a></li>
                <li><a href="#kapan">Kapan Dipakai</a></li>
            </ol>
        </div>

        <h5 id="tools" class="border-bottom pb-2"><i class="fas fa-wrench mr-1"></i>1. Tool Sistem</h5>
        <ul>
            <li><strong>Cek Pemakaian</strong>: audit penggunaan pelanggan atau sesi.</li>
            <li><strong>Impor User</strong>: untuk onboarding data massal.</li>
            <li><strong>Ekspor User / Transaksi</strong>: untuk audit, migrasi, atau pelaporan eksternal.</li>
        </ul>

        <h5 id="backup" class="border-bottom pb-2 mt-4"><i class="fas fa-database mr-1"></i>2. Backup dan Reset</h5>
        <p>Menu backup, reset laporan, dan reset database termasuk fitur sensitif. Gunakan hanya untuk kebutuhan insiden terkontrol dan pastikan scope tenant/data sudah benar sebelum mengeksekusi aksi.</p>

        <h5 id="log" class="border-bottom pb-2 mt-4"><i class="fas fa-clipboard-list mr-1"></i>3. Log Aplikasi</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Log</th>
                    <th>Kegunaan</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>Log Login</strong></td><td>Audit akses akun.</td></tr>
                <tr><td><strong>Log Aktivitas</strong></td><td>Menelusuri perubahan dan aksi user.</td></tr>
                <tr><td><strong>Log GenieACS</strong></td><td>Memeriksa task dan sinkronisasi perangkat CPE.</td></tr>
                <tr><td><strong>Log Auth Radius</strong></td><td>Menganalisis autentikasi PPPoE/Hotspot.</td></tr>
                <tr><td><strong>Log Pengiriman WA</strong></td><td>Menelusuri status pengiriman notifikasi.</td></tr>
            </tbody>
        </table>

        <h5 id="kapan" class="border-bottom pb-2 mt-4"><i class="fas fa-question-circle mr-1"></i>4. Kapan Dipakai</h5>
        <div class="alert alert-warning mb-0">
            Gunakan tool dan log ini saat ada kebutuhan audit, migrasi, troubleshooting, atau validasi hasil proses massal. Hindari menjalankan fitur sensitif hanya untuk percobaan di jam operasional sibuk.
        </div>
    </div>
</div>
@endsection
