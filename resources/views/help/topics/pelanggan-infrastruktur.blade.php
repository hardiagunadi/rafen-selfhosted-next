@extends('layouts.admin')

@section('title', 'Bantuan: Pelanggan & ODP')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h4 class="card-title mb-0"><i class="fas fa-map-marked-alt mr-2"></i>Pelanggan, Peta, dan ODP</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#fungsi">Fungsi Menu</a></li>
                <li><a href="#peta">Peta Pelanggan</a></li>
                <li><a href="#odp">Data ODP</a></li>
                <li><a href="#alur">Alur Kerja Disarankan</a></li>
                <li><a href="#tips">Tips Kualitas Data</a></li>
            </ol>
        </div>

        <h5 id="fungsi" class="border-bottom pb-2"><i class="fas fa-layer-group mr-1"></i>1. Fungsi Menu</h5>
        <p>Menu ini membantu tim memetakan pelanggan ke lokasi nyata di lapangan. Biasanya dipakai Admin, CS, NOC, dan teknisi untuk melihat sebaran pelanggan, ODP terdekat, serta area yang terdampak saat ada gangguan.</p>

        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Submenu</th>
                    <th>Fungsi</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>Peta Pelanggan</strong></td><td>Menampilkan pelanggan dan ODP pada peta untuk validasi coverage dan penanganan lapangan.</td></tr>
                <tr><td><strong>Data ODP</strong></td><td>Menyimpan kode, nama, titik koordinat, dan catatan lokasi distribusi fiber.</td></tr>
                <tr><td><strong>List Pelanggan</strong></td><td>Menjadi sumber data utama alamat, koordinat, dan relasi pelanggan ke ODP.</td></tr>
            </tbody>
        </table>

        <h5 id="peta" class="border-bottom pb-2"><i class="fas fa-map mr-1"></i>2. Peta Pelanggan</h5>
        <ul>
            <li>Gunakan peta untuk melihat apakah koordinat pelanggan sudah valid dan tidak menumpuk di satu titik yang tidak masuk akal.</li>
            <li>Jika cache peta aktif, halaman akan lebih ringan saat dibuka berulang kali.</li>
            <li>Saat ada gangguan wilayah, peta membantu memperkirakan pelanggan mana yang paling mungkin terdampak.</li>
        </ul>

        <h5 id="odp" class="border-bottom pb-2 mt-4"><i class="fas fa-network-wired mr-1"></i>3. Data ODP</h5>
        <ul>
            <li>Gunakan kode ODP yang konsisten, misalnya per area atau jalur distribusi.</li>
            <li>Pastikan titik koordinat ODP diisi agar peta dan analisis gangguan lebih akurat.</li>
            <li>Simpan catatan lokasi fisik seperti patokan bangunan, tiang, atau RT/RW untuk membantu teknisi lapangan.</li>
        </ul>

        <h5 id="alur" class="border-bottom pb-2 mt-4"><i class="fas fa-route mr-1"></i>4. Alur Kerja Disarankan</h5>
        <ol>
            <li>Tambahkan atau rapikan data ODP lebih dulu.</li>
            <li>Saat input pelanggan baru, isi alamat dan koordinat seakurat mungkin.</li>
            <li>Hubungkan pelanggan ke ODP yang benar agar analisis wilayah tetap rapi.</li>
            <li>Gunakan peta saat audit coverage, penugasan teknisi, atau penanganan gangguan massal.</li>
        </ol>

        <h5 id="tips" class="border-bottom pb-2 mt-4"><i class="fas fa-lightbulb mr-1"></i>5. Tips Kualitas Data</h5>
        <div class="alert alert-warning mb-0">
            <ul class="mb-0">
                <li>Jangan biarkan koordinat pelanggan kosong terlalu lama.</li>
                <li>Gunakan format penamaan ODP yang stabil agar mudah dicari di autocomplete.</li>
                <li>Jika satu area sering bermasalah, cek kembali apakah relasi pelanggan ke ODP sudah benar.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
