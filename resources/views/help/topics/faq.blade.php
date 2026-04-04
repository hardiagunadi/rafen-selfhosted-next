@extends('layouts.admin')

@section('title', 'Bantuan: FAQ Operasional')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-warning">
        <h4 class="card-title mb-0"><i class="fas fa-question-circle mr-2"></i>FAQ Operasional RAFEN</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4 help-toc">
            <strong>Tujuan halaman ini:</strong> menjawab pertanyaan yang paling sering muncul di operasional harian, tanpa harus cek source code atau log satu per satu.
        </div>

        <div class="accordion" id="help-faq-accordion">
            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0" type="button" data-toggle="collapse" data-target="#faq-1">
                        1) Kenapa data session PPPoE/Hotspot tidak muncul?
                    </button>
                </div>
                <div id="faq-1" class="collapse show" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Cek koneksi API MikroTik (host, username, password, port), lalu buka halaman Session agar auto-sync berjalan.
                        Jika masih kosong, lakukan ping dari menu Router (NAS) dan validasi service API di MikroTik.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-2">
                        2) SNMP OLT sering timeout, bagaimana supaya tidak mengganggu dashboard?
                    </button>
                </div>
                <div id="faq-2" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Gunakan polling bertahap (queue/background) dan tampilkan progress polling per tahap.
                        Kurangi OID yang dipanggil sekaligus, gunakan retry seperlunya, dan simpan cache hasil polling terakhir agar tabel tetap tampil meski ada timeout parsial.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-3">
                        3) Kenapa nomor pengirim WA kosong di dashboard gateway?
                    </button>
                </div>
                <div id="faq-3" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Umumnya karena payload webhook/pengiriman tidak menyertakan field pengirim sesuai format gateway.
                        Pastikan endpoint webhook aktif, token valid, dan field pengirim diambil dari response session device gateway (bukan hardcoded kosong).
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-4">
                        4) Pelanggan sudah lewat jatuh tempo tapi belum terisolir, penyebabnya?
                    </button>
                </div>
                <div id="faq-4" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Periksa status tagihan dan pengaturan aksi jatuh tempo pelanggan. Jika aksi isolir belum aktif atau job/scheduler tidak berjalan,
                        pelanggan tetap bisa online. Pastikan mekanisme isolir aktif dan route halaman isolir publik dapat diakses.
                        Jika instalasi memakai IP tanpa domain, validasi dulu akses HTTP ke <code>APP_URL</code>; HTTPS by IP tidak selalu siap tanpa konfigurasi TLS tambahan.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-5">
                        5) Apa arti status "Aktif - Belum Bayar"?
                    </button>
                </div>
                <div id="faq-5" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Status ini berarti layanan sengaja diperpanjang tanpa pembayaran sementara.
                        Pelanggan tetap aktif internet, notifikasi tagihan WA tetap bisa dikirim, dan invoice baru menunggu dilunasi.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-6">
                        6) Kapan tombol "Tandai Lunas" muncul di Rekap Tagihan?
                    </button>
                </div>
                <div id="faq-6" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Tombol <strong>Tandai Lunas</strong> muncul pada skenario perpanjangan tanpa bayar.
                        Untuk kondisi normal, aksi yang tampil tetap <strong>Bayar</strong> dan <strong>Perpanjang</strong>.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-7">
                        7) Kenapa menu Data Keuangan tidak tampil?
                    </button>
                </div>
                <div id="faq-7" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Menu Data Keuangan muncul untuk role <strong>Admin</strong>, <strong>Keuangan</strong>, dan <strong>Teknisi</strong> (serta Super Admin).
                        Pada role teknisi, laporan yang tampil dibatasi ke transaksi milik teknisi tersebut. Jika login sebagai NOC/IT Support/CS, menu ini memang disembunyikan.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-8">
                        8) Kenapa teknisi tidak bisa lihat tombol hapus invoice/nota?
                    </button>
                </div>
                <div id="faq-8" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Itu pembatasan akses role untuk mencegah aksi finansial yang sensitif.
                        Role teknisi tetap bisa melakukan proses lapangan, tetapi tidak diberi hak hapus invoice/nota.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-9">
                        9) Apakah aman mengubah queue connection dari database ke sync?
                    </button>
                </div>
                <div id="faq-9" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Aman untuk task ringan, tetapi job akan dieksekusi langsung saat request berjalan.
                        Jika workload berat (blast WA, polling massal, sinkronisasi besar), sebaiknya gunakan queue async agar UI tidak lambat.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-10">
                        10) Kenapa website timeout (ERR_TIMED_OUT)?
                    </button>
                </div>
                <div id="faq-10" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Biasanya karena beban request menumpuk, PHP-FPM worker penuh, atau endpoint captive-check terlalu ramai.
                        Terapkan hardening Apache (limit/routing endpoint ringan) serta tuning MaxRequestWorkers dan pool PHP-FPM.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-11">
                        11) Bagaimana memastikan polling OLT selesai tanpa reload halaman?
                    </button>
                </div>
                <div id="faq-11" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Gunakan endpoint status polling (progress-based) dan lakukan auto-refresh tabel ketika status selesai.
                        Dengan cara ini operator bisa melihat status proses dan hasil update realtime.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-12">
                        12) Kenapa parent queue pelanggan tidak otomatis terisi?
                    </button>
                </div>
                <div id="faq-12" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Pastikan profil group dan mapping queue parent di router sudah konsisten.
                        Jika nama queue parent di aplikasi tidak sama dengan router, assign parent queue akan gagal atau jatuh ke default.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-13">
                        13) Bagaimana format dropdown paket agar mudah dicari saat data banyak?
                    </button>
                </div>
                <div id="faq-13" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Gunakan format label <code>Nama Paket - Harga - Durasi</code> dengan dropdown searchable (Select2),
                        sehingga operator bisa ketik kata kunci tanpa scroll panjang.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-14">
                        14) Apa yang harus dicek sebelum menghapus tenant?
                    </button>
                </div>
                <div id="faq-14" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Verifikasi tidak ada pelanggan aktif di tenant tersebut, lalu tampilkan warning konfirmasi final.
                        Praktik ini mencegah kehilangan data operasional yang masih berjalan.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-15">
                        15) Bagaimana strategi pesan WA agar terasa natural, bukan bot?
                    </button>
                </div>
                <div id="faq-15" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Pakai rotasi template, variasi sapaan, jeda pengiriman antar pelanggan, dan antrean bertahap.
                        Hindari blast serentak agar delivery lebih stabil dan percakapan lebih manusiawi.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary mb-2">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-16">
                        16) Apa alur cepat setup fitur WhatsApp terbaru?
                    </button>
                </div>
                <div id="faq-16" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        Buka <strong>Pengaturan → WhatsApp → Gateway &amp; Template</strong>, lalu ikuti Wizard Onboarding:
                        (1) test koneksi, (2) tambah device, (3) scan QR di modal popup, (4) aktifkan otomasi/blast, (5) test template dan simpan.
                        Pada tab <strong>Manajemen Device</strong>, status tiap device ditampilkan real-time: <em>Connected</em>, <em>Belum Scan</em>, <em>Proses Login</em>, atau <em>Disconnected</em>.
                    </div>
                </div>
            </div>

            @if(auth()->user()?->isSuperAdmin())
            <div class="card card-outline card-danger mb-0">
                <div class="card-header py-2">
                    <button class="btn btn-link text-left text-danger p-0 collapsed" type="button" data-toggle="collapse" data-target="#faq-17">
                        17) <span class="badge badge-danger mr-1">Super Admin</span> Status pelanggan tetap "connected" padahal sudah offline — penyebab &amp; solusi?
                    </button>
                </div>
                <div id="faq-17" class="collapse" data-parent="#help-faq-accordion">
                    <div class="card-body">
                        <p><strong>Penyebab:</strong> Sistem sync sesi berjalan via scheduler <code>sessions:sync</code> setiap 5 menit.
                        Jika scheduler menggunakan <code>withoutOverlapping()</code> dan run sebelumnya tidak selesai bersih,
                        <strong>mutex/cache lock bisa tertinggal</strong> sehingga command tidak pernah dijalankan oleh scheduler meski terlihat aktif.</p>

                        <p><strong>Cara identifikasi:</strong></p>
                        <ol>
                            <li>Cek log scheduler — jika <code>sessions:sync</code> tidak pernah muncul padahal <code>mikrotik:ping-once</code> dan <code>cpe:sync-genieacs</code> rutin jalan, ini tanda mutex macet.</li>
                            <li>Jalankan manual: <code>php artisan sessions:sync</code> — jika berhasil, berarti command-nya normal, hanya scheduler yang terblokir mutex.</li>
                        </ol>

                        <p><strong>Solusi:</strong></p>
                        <ol>
                            <li>Bersihkan cache/mutex yang tertinggal:
                                <pre class="bg-light p-2 rounded mb-2"><code>php artisan cache:clear</code></pre>
                            </li>
                            <li>Tunggu 5 menit, lalu verifikasi <code>sessions:sync</code> muncul di log scheduler:
                                <pre class="bg-light p-2 rounded mb-2"><code>sudo journalctl -u rafen-schedule.service --since "10 minutes ago" | grep sessions</code></pre>
                            </li>
                            <li>Jika masih belum muncul, restart scheduler:
                                <pre class="bg-light p-2 rounded mb-0"><code>sudo systemctl restart rafen-schedule.timer</code></pre>
                            </li>
                        </ol>

                        <div class="alert alert-warning mb-0 mt-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Catatan:</strong> Status "sync: X jam yang lalu" di kotak <em>Waktu Online</em> pelanggan adalah indikator utama — jika lebih dari 10 menit, berarti sync bermasalah.
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="alert alert-info mt-4 mb-0">
            Jika pertanyaan belum tercakup, tambahkan kasus ke tim admin agar FAQ diperbarui berkala berdasarkan masalah operasional terbaru.
        </div>
    </div>
</div>
@endsection
