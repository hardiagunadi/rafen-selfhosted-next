@extends('layouts.admin')

@section('title', 'Bantuan: Chat WA & Tiket')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <h4 class="card-title mb-0"><i class="fas fa-comments mr-2"></i>Chat WA & Tiket Pengaduan</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#akses">Role yang Mengakses</a></li>
                <li><a href="#inbox">Inbox Chat</a></li>
                <li><a href="#tiket">Siklus Tiket</a></li>
                <li><a href="#pembagian">Pembagian Tanggung Jawab</a></li>
                <li><a href="#tips">Tips Operasional</a></li>
            </ol>
        </div>

        <h5 id="akses" class="border-bottom pb-2"><i class="fas fa-user-lock mr-1"></i>1. Role yang Mengakses</h5>
        <p><strong>Admin</strong>, <strong>NOC</strong>, <strong>IT Support</strong>, dan <strong>CS</strong> dapat mengakses inbox chat. <strong>Teknisi</strong> fokus pada tiket yang di-assign ke dirinya.</p>

        <h5 id="inbox" class="border-bottom pb-2 mt-4"><i class="fab fa-whatsapp mr-1"></i>2. Inbox Chat</h5>
        <ul>
            <li>Inbox dipakai untuk membaca percakapan pelanggan, membalas pesan, memberi label penanganan, dan mengatur siapa yang bertanggung jawab.</li>
            <li>Jika bot sempat mengambil alih, operator dapat menggunakan aksi <strong>resume bot</strong> setelah percakapan selesai.</li>
            <li>Gunakan assignment jika percakapan perlu ditindaklanjuti oleh user tertentu.</li>
        </ul>

        <h5 id="tiket" class="border-bottom pb-2 mt-4"><i class="fas fa-ticket-alt mr-1"></i>3. Siklus Tiket</h5>
        <ol>
            <li>Buat atau buka tiket dari keluhan pelanggan.</li>
            <li>Tentukan pelanggan dan detail masalah.</li>
            <li>Assign tiket ke teknisi atau staf yang relevan.</li>
            <li>Tambahkan catatan progres sampai masalah selesai.</li>
            <li>Tutup tiket saat penyelesaian sudah dikonfirmasi.</li>
        </ol>

        <h5 id="pembagian" class="border-bottom pb-2 mt-4"><i class="fas fa-people-arrows mr-1"></i>4. Pembagian Tanggung Jawab</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Role</th>
                    <th>Fokus Utama</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>CS</strong></td><td>Menerima keluhan, membalas pelanggan, membuka tiket, dan memastikan follow-up.</td></tr>
                <tr><td><strong>NOC / IT Support</strong></td><td>Menganalisis gangguan teknis, memberi arahan, atau melakukan eskalasi.</td></tr>
                <tr><td><strong>Teknisi</strong></td><td>Mengerjakan tiket yang di-assign, memberi catatan lapangan, dan mengubah status tiket.</td></tr>
                <tr><td><strong>Admin</strong></td><td>Mengawasi antrean, assignment, dan kualitas respons tim.</td></tr>
            </tbody>
        </table>

        <h5 id="tips" class="border-bottom pb-2 mt-4"><i class="fas fa-lightbulb mr-1"></i>5. Tips Operasional</h5>
        <ul class="mb-0">
            <li>Gunakan tiket untuk kasus yang butuh pelacakan, bukan hanya chat biasa.</li>
            <li>Jika teknisi memberi update, CS/NOC sebaiknya membuka tiket agar notifikasi internal dianggap terbaca.</li>
            <li>Jaga ringkasan catatan singkat tetapi jelas: masalah, tindakan, hasil, dan kebutuhan lanjutan.</li>
        </ul>
    </div>
</div>
@endsection
