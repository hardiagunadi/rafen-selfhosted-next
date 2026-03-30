@extends('layouts.admin')

@section('title', 'Bantuan: Jadwal Shift')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-warning">
        <h4 class="card-title mb-0"><i class="fas fa-calendar-alt mr-2"></i>Jadwal Shift</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Menu shift hanya muncul jika modul shift tenant aktif. Admin mengelola jadwal, sedangkan role lain melihat jadwal masing-masing dan dapat terlibat dalam proses tukar shift.
        </div>

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#struktur">Struktur Menu</a></li>
                <li><a href="#definisi">Definisi Shift</a></li>
                <li><a href="#jadwal">Penjadwalan</a></li>
                <li><a href="#swap">Tukar Shift</a></li>
                <li><a href="#reminder">Reminder</a></li>
            </ol>
        </div>

        <h5 id="struktur" class="border-bottom pb-2"><i class="fas fa-sitemap mr-1"></i>1. Struktur Menu</h5>
        <ul>
            <li><strong>Kelola Jadwal</strong>: untuk admin/super admin menyusun jadwal dan definisi shift.</li>
            <li><strong>Jadwal Saya</strong>: untuk semua role yang diizinkan melihat penugasan mereka.</li>
        </ul>

        <h5 id="definisi" class="border-bottom pb-2 mt-4"><i class="fas fa-clock mr-1"></i>2. Definisi Shift</h5>
        <p>Definisi shift berisi nama shift, jam mulai, jam selesai, role sasaran, warna, dan status aktif. Buat definisi yang sedikit tetapi konsisten agar penjadwalan tidak membingungkan.</p>

        <h5 id="jadwal" class="border-bottom pb-2 mt-4"><i class="fas fa-calendar-check mr-1"></i>3. Penjadwalan</h5>
        <ol>
            <li>Pilih user yang relevan.</li>
            <li>Tentukan tanggal dan definisi shift.</li>
            <li>Gunakan bulk schedule jika menjadwalkan banyak hari sekaligus.</li>
            <li>Pastikan tidak ada bentrok sebelum reminder dikirim.</li>
        </ol>

        <h5 id="swap" class="border-bottom pb-2 mt-4"><i class="fas fa-exchange-alt mr-1"></i>4. Tukar Shift</h5>
        <ul>
            <li>Staf dapat mengajukan tukar shift melalui menu terkait.</li>
            <li>Permintaan perlu direview oleh pihak yang berwenang.</li>
            <li>Simpan alasan tukar agar histori operasional tetap jelas.</li>
        </ul>

        <h5 id="reminder" class="border-bottom pb-2 mt-4"><i class="fas fa-bell mr-1"></i>5. Reminder</h5>
        <div class="alert alert-light border mb-0">
            Gunakan reminder saat jadwal sudah final. Hindari mengirim reminder berkali-kali untuk perubahan kecil agar notifikasi internal tetap efektif.
        </div>
    </div>
</div>
@endsection
