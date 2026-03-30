@extends('layouts.admin')

@section('title', 'Bantuan: Gangguan Jaringan')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h4 class="card-title mb-0"><i class="fas fa-broadcast-tower mr-2"></i>Gangguan Jaringan</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-danger">
            <strong>Role utama:</strong> Admin, NOC, dan IT Support mengelola insiden. CS dan teknisi tetap dapat memberi update sesuai penugasan.
        </div>

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#buat">Membuat Insiden</a></li>
                <li><a href="#terdampak">Preview Pelanggan Terdampak</a></li>
                <li><a href="#blast">Blast Notifikasi</a></li>
                <li><a href="#penugasan">Assignment Teknisi</a></li>
                <li><a href="#resolve">Penyelesaian</a></li>
            </ol>
        </div>

        <h5 id="buat" class="border-bottom pb-2"><i class="fas fa-plus-circle mr-1"></i>1. Membuat Insiden</h5>
        <ul>
            <li>Isi judul gangguan, severity, waktu mulai, area terdampak, dan catatan awal.</li>
            <li>Pilih ODP, NAS, atau cakupan yang paling mendekati lokasi masalah.</li>
            <li>Gunakan severity yang konsisten agar tim mudah memprioritaskan respons.</li>
        </ul>

        <h5 id="terdampak" class="border-bottom pb-2 mt-4"><i class="fas fa-users mr-1"></i>2. Preview Pelanggan Terdampak</h5>
        <p>Sebelum kirim notifikasi, lakukan preview pelanggan terdampak agar blast hanya menyasar nomor yang relevan. Ini penting supaya pelanggan di area lain tidak menerima pesan yang membingungkan.</p>

        <h5 id="blast" class="border-bottom pb-2 mt-4"><i class="fas fa-paper-plane mr-1"></i>3. Blast Notifikasi</h5>
        <ol>
            <li>Pastikan judul dan isi ringkasan gangguan sudah jelas.</li>
            <li>Gunakan blast awal untuk pemberitahuan insiden.</li>
            <li>Gunakan blast lanjutan hanya jika ada perubahan ETA atau status penyelesaian.</li>
        </ol>

        <h5 id="penugasan" class="border-bottom pb-2 mt-4"><i class="fas fa-user-cog mr-1"></i>4. Assignment Teknisi</h5>
        <ul>
            <li>Assign teknisi yang bertanggung jawab agar update lapangan tidak tercecer.</li>
            <li>Teknisi yang di-assign bisa memberi update progres langsung dari detail gangguan.</li>
            <li>Jika gangguan meluas, NOC/Admin sebaiknya tetap memegang koordinasi pusat.</li>
        </ul>

        <h5 id="resolve" class="border-bottom pb-2 mt-4"><i class="fas fa-check-double mr-1"></i>5. Penyelesaian</h5>
        <div class="alert alert-light border mb-0">
            Setelah masalah selesai, tambahkan update akhir, ubah status ke selesai, lalu kirim notifikasi penutupan jika diperlukan. Hindari menutup insiden tanpa catatan akhir agar histori audit tetap jelas.
        </div>
    </div>
</div>
@endsection
