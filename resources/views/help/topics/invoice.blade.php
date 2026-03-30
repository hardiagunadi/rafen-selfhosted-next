@extends('layouts.admin')

@section('title', 'Bantuan: Tagihan (Invoice)')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-warning">
        <h4 class="card-title mb-0"><i class="fas fa-file-invoice-dollar mr-2"></i>Tagihan (Invoice) — Panduan Lengkap</h4>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 help-toc">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#cara-kerja">Cara Kerja Sistem Invoice</a></li>
                <li><a href="#generate-otomatis">Generate Invoice Otomatis (14 Hari)</a></li>
                <li><a href="#artisan-cmd">Perintah Artisan: invoice:generate-upcoming</a></li>
                <li><a href="#status">Status Invoice</a></li>
                <li><a href="#pembayaran">Alur Pembayaran</a></li>
                <li><a href="#overdue">Aksi Jatuh Tempo</a></li>
            </ol>
        </div>

        {{-- 1 --}}
        <h5 id="cara-kerja" class="border-bottom pb-2"><i class="fas fa-sitemap mr-1"></i>1. Cara Kerja Sistem Invoice</h5>
        <p>Invoice di Rafen dibuat otomatis untuk setiap User PPP yang memiliki <code>status_bayar = 'belum_bayar'</code>. Invoice menyimpan informasi tagihan berdasarkan profil paket yang aktif saat invoice dibuat.</p>
        <div class="bg-light border rounded p-3 mb-4">
            <code>User PPP (belum_bayar) → Invoice dibuat → User bayar → Invoice lunas → jatuh_tempo diperpanjang</code>
        </div>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Field Invoice</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><strong>invoice_number</strong></td><td>Nomor unik format <code>INV-XXXXXXX</code></td></tr>
                <tr><td><strong>due_date</strong></td><td>Tanggal jatuh tempo invoice (diambil dari <code>jatuh_tempo</code> user)</td></tr>
                <tr><td><strong>status</strong></td><td><code>unpaid</code> atau <code>paid</code></td></tr>
                <tr><td><strong>total</strong></td><td>Harga dasar + PPN</td></tr>
                <tr><td><strong>promo_applied</strong></td><td>Apakah harga promo digunakan saat invoice dibuat</td></tr>
            </tbody>
        </table>

        {{-- 2 --}}
        <h5 id="generate-otomatis" class="border-bottom pb-2"><i class="fas fa-clock mr-1"></i>2. Generate Invoice Otomatis (14 Hari)</h5>
        <p>Sistem Rafen secara otomatis men-generate invoice untuk user yang <strong>jatuh temponya dalam 14 hari ke depan</strong>. Terdapat dua mekanisme:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Mekanisme</th><th>Kapan Berjalan</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>On-demand (halaman PPP Users)</strong></td>
                    <td>Setiap kali halaman List User PPP dibuka</td>
                    <td>Cek setiap user yang tampil di halaman — jika dalam 14 hari ke depan &amp; belum ada invoice, langsung dibuat</td>
                </tr>
                <tr>
                    <td><strong>Scheduler Harian (07:00)</strong></td>
                    <td>Setiap hari pukul 07:00</td>
                    <td>Cek <strong>semua</strong> user PPP secara massal, tidak bergantung pada admin membuka halaman</td>
                </tr>
            </tbody>
        </table>
        <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i><strong>Rekomendasi:</strong> Pastikan cron Laravel aktif agar scheduler berjalan. Cek dengan perintah <code>crontab -l</code> — harus ada baris <code>* * * * * php artisan schedule:run</code>.</div>

        {{-- 3 --}}
        <h5 id="artisan-cmd" class="border-bottom pb-2"><i class="fas fa-terminal mr-1"></i>3. Perintah Artisan: <code>invoice:generate-upcoming</code></h5>
        <p>Command ini memindai semua user PPP dengan <code>status_bayar = 'belum_bayar'</code> dan <code>jatuh_tempo</code> yang jatuh dalam N hari ke depan, lalu membuat invoice untuk yang belum punya.</p>

        <div class="row">
            <div class="col-md-6">
                <div class="card border-primary mb-3">
                    <div class="card-header bg-primary text-white py-2"><i class="fas fa-play mr-1"></i>Jalankan Normal</div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-white p-3 mb-0 rounded-bottom"><code>php artisan invoice:generate-upcoming</code></pre>
                    </div>
                    <div class="card-footer text-muted small py-2">Generate invoice untuk semua user yang jatuh tempo dalam <strong>14 hari</strong> ke depan.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-info mb-3">
                    <div class="card-header bg-info text-white py-2"><i class="fas fa-eye mr-1"></i>Preview Tanpa Menulis Data</div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-white p-3 mb-0 rounded-bottom"><code>php artisan invoice:generate-upcoming --dry-run</code></pre>
                    </div>
                    <div class="card-footer text-muted small py-2">Tampilkan daftar user yang <em>akan</em> dibuat invoice-nya, tanpa benar-benar menyimpan ke database. Aman untuk dicoba kapan saja.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-warning mb-3">
                    <div class="card-header bg-warning py-2"><i class="fas fa-sliders-h mr-1"></i>Ubah Window Hari</div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-white p-3 mb-0 rounded-bottom"><code>php artisan invoice:generate-upcoming --days=7</code></pre>
                    </div>
                    <div class="card-footer text-muted small py-2">Generate invoice untuk user yang jatuh tempo dalam <strong>7 hari</strong> ke depan. Bisa diisi angka berapa saja.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-secondary mb-3">
                    <div class="card-header bg-secondary text-white py-2"><i class="fas fa-combine mr-1"></i>Kombinasi</div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-white p-3 mb-0 rounded-bottom"><code>php artisan invoice:generate-upcoming --days=30 --dry-run</code></pre>
                    </div>
                    <div class="card-footer text-muted small py-2">Preview user yang jatuh tempo dalam 30 hari, tanpa membuat invoice.</div>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-1"></i><strong>Output command:</strong> Setiap user yang diproses akan ditampilkan dengan keterangan <code>[OK]</code> (invoice dibuat) atau <code>[skip]</code> (sudah ada invoice / tidak ada profil). Di akhir ditampilkan ringkasan total <em>Generated</em> dan <em>Skipped</em>.
        </div>

        {{-- 4 --}}
        <h5 id="status" class="border-bottom pb-2"><i class="fas fa-info-circle mr-1"></i>4. Status Invoice</h5>
        <table class="table table-sm table-bordered mb-4">
            <thead class="thead-light"><tr><th>Status</th><th>Keterangan</th><th>Aksi Tersedia</th></tr></thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-warning">unpaid</span></td>
                    <td>Belum dibayar</td>
                    <td>Bayar, Perpanjang (Renew), Hapus</td>
                </tr>
                <tr>
                    <td><span class="badge badge-success">paid</span></td>
                    <td>Sudah lunas</td>
                    <td>Lihat detail saja</td>
                </tr>
                <tr>
                    <td><span class="badge badge-danger">overdue</span></td>
                    <td>Belum bayar dan sudah lewat due_date</td>
                    <td>Bayar, Perpanjang</td>
                </tr>
            </tbody>
        </table>

        {{-- 5 --}}
        <h5 id="pembayaran" class="border-bottom pb-2"><i class="fas fa-credit-card mr-1"></i>5. Alur Pembayaran</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="card border-success mb-3">
                    <div class="card-header bg-success text-white py-2">Pembayaran via Gateway (Tripay)</div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Klik tombol <strong>Bayar</strong> di invoice</li>
                            <li>Pilih metode pembayaran (QRIS, VA, dll)</li>
                            <li>Sistem membuat transaksi di Tripay</li>
                            <li>User membayar via channel yang dipilih</li>
                            <li>Callback otomatis → invoice lunas → <code>jatuh_tempo</code> diperpanjang 1 bulan</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-info mb-3">
                    <div class="card-header bg-info text-white py-2">Pembayaran Manual</div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>User upload bukti transfer</li>
                            <li>Admin/owner konfirmasi pembayaran</li>
                            <li>Invoice lunas → <code>jatuh_tempo</code> diperpanjang 1 bulan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        {{-- 6 --}}
        <h5 id="overdue" class="border-bottom pb-2"><i class="fas fa-exclamation-triangle mr-1 text-danger"></i>6. Aksi Jatuh Tempo</h5>
        <p>Setiap user PPP bisa dikonfigurasi untuk diambil tindakan otomatis saat melewati tanggal jatuh tempo:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light"><tr><th>Pengaturan <code>aksi_jatuh_tempo</code></th><th>Efek</th></tr></thead>
            <tbody>
                <tr><td><code>isolir</code></td><td>Status akun berubah ke <code>isolir</code> secara otomatis. Akun di-sync ke RADIUS → user tidak bisa login.</td></tr>
                <tr><td><em>(kosong)</em></td><td>Tidak ada aksi otomatis. User tetap bisa login.</td></tr>
            </tbody>
        </table>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Pengecekan <code>aksi_jatuh_tempo</code> berjalan setiap kali halaman List User PPP dibuka. Untuk enforcement otomatis tanpa admin membuka halaman, pertimbangkan menambahkan scheduled command.</div>

    </div>
</div>
@endsection
