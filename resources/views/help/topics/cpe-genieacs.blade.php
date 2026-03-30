@extends('layouts.admin')

@section('title', 'Bantuan: CPE & GenieACS')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-network-wired mr-2"></i>CPE Management & GenieACS</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Menu ini umumnya dipakai oleh <strong>Admin</strong>, <strong>NOC</strong>, dan <strong>IT Support</strong> untuk mengelola modem/CPE pelanggan via integrasi GenieACS.
        </div>

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#prasyarat">Prasyarat</a></li>
                <li><a href="#unlinked">Perangkat Belum Tertaut</a></li>
                <li><a href="#aksi">Aksi di Perangkat Tertaut</a></li>
                <li><a href="#olt">Kaitkan ke OLT/ONU</a></li>
                <li><a href="#troubleshoot">Troubleshooting Ringkas</a></li>
            </ol>
        </div>

        <h5 id="prasyarat" class="border-bottom pb-2"><i class="fas fa-check-circle mr-1"></i>1. Prasyarat</h5>
        <ul>
            <li>Tenant harus sudah memiliki konfigurasi GenieACS yang aktif.</li>
            <li>Pelanggan PPP harus sudah ada agar CPE bisa ditautkan ke akun yang benar.</li>
            <li>Jika menu tidak bisa dibuka, biasanya tenant belum menyiapkan integrasi GenieACS.</li>
        </ul>

        <h5 id="unlinked" class="border-bottom pb-2 mt-4"><i class="fas fa-unlink mr-1"></i>2. Perangkat Belum Tertaut</h5>
        <ol>
            <li>Buka daftar perangkat yang belum tertaut.</li>
            <li>Cek serial number, model, MAC, dan parameter dasar perangkat.</li>
            <li>Hubungkan perangkat ke pelanggan PPP yang tepat.</li>
            <li>Jika data perangkat belum lengkap, gunakan aksi refresh parameter terlebih dahulu.</li>
        </ol>

        <h5 id="aksi" class="border-bottom pb-2 mt-4"><i class="fas fa-sliders-h mr-1"></i>3. Aksi di Perangkat Tertaut</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Aksi</th>
                    <th>Fungsi</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>Sync / Refresh</strong></td><td>Mengambil ulang parameter dari perangkat.</td></tr>
                <tr><td><strong>Reboot</strong></td><td>Meminta CPE restart dari dashboard.</td></tr>
                <tr><td><strong>Update WiFi</strong></td><td>Mengubah SSID atau password WiFi perangkat.</td></tr>
                <tr><td><strong>Update PPPoE</strong></td><td>Menyamakan credential PPPoE dengan data pelanggan.</td></tr>
                <tr><td><strong>Traffic / WAN Info</strong></td><td>Melihat status dasar koneksi perangkat.</td></tr>
            </tbody>
        </table>

        <h5 id="olt" class="border-bottom pb-2 mt-4"><i class="fas fa-project-diagram mr-1"></i>4. Kaitkan ke OLT/ONU</h5>
        <p>Jika data OLT tersedia, CPE bisa dikaitkan ke ONU agar tim memiliki jejak yang lebih utuh dari pelanggan sampai perangkat akses. Ini membantu saat audit MAC, troubleshooting optic, atau pelacakan port.</p>

        <h5 id="troubleshoot" class="border-bottom pb-2 mt-4"><i class="fas fa-tools mr-1"></i>5. Troubleshooting Ringkas</h5>
        <ul class="mb-0">
            <li>Jika perangkat tidak muncul, cek apakah GenieACS sudah mengirim data ke tenant yang benar.</li>
            <li>Jika update WiFi/PPPoE gagal, lakukan refresh parameter lalu ulangi aksi.</li>
            <li>Jika link ke pelanggan salah, perbaiki relasinya sebelum teknisi melakukan tindakan lapangan.</li>
        </ul>
    </div>
</div>
@endsection
