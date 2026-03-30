<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi Rafen — {{ config('app.name', 'RAFEN Manager') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/rafen-favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: #f9f9f8;
            color: #1b1b18;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
        }
        .container { max-width: 820px; width: 100%; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            color: #706f6c;
            text-decoration: none;
            margin-bottom: 1.5rem;
        }
        .back-link:hover { color: #1b1b18; }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: inset 0 0 0 1px rgba(26,26,0,0.16);
            padding: 2rem;
        }
        h1 { margin: 0 0 .5rem 0; font-size: 1.5rem; }
        .meta { margin: 0 0 1.5rem 0; color: #706f6c; font-size: .875rem; }
        h2 { margin: 1.5rem 0 .5rem 0; font-size: 1.0625rem; }
        p, li { line-height: 1.7; color: #2d2d2a; font-size: .95rem; }
        ul { margin: .5rem 0 0 1.25rem; padding: 0; }
        .note {
            margin-top: 1.5rem;
            background: #f4f4f1;
            border-radius: 8px;
            padding: .875rem 1rem;
            color: #595954;
            font-size: .875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ url('/') }}" class="back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Kembali ke Beranda
        </a>

        <div class="card">
            <h1>Kebijakan Privasi Rafen</h1>
            <p class="meta">Berlaku efektif: {{ now()->format('d M Y') }}</p>

            <p>
                Rafen berkomitmen melindungi data pribadi pengguna saat menggunakan layanan kami di situs
                <strong>rafen.ID</strong> maupun produk terkait. Halaman ini menjelaskan data yang kami kumpulkan,
                tujuan penggunaan, dan hak Anda atas data tersebut.
            </p>

            <h2>1. Data yang Kami Kumpulkan</h2>
            <ul>
                <li>Data identitas akun, seperti nama, email, dan nomor telepon.</li>
                <li>Data operasional layanan, seperti log akses, aktivitas penggunaan, dan metadata teknis.</li>
                <li>Data komunikasi saat Anda menghubungi dukungan pelanggan.</li>
            </ul>

            <h2>2. Tujuan Penggunaan Data</h2>
            <ul>
                <li>Menyediakan, memelihara, dan meningkatkan layanan Rafen.</li>
                <li>Verifikasi akun, keamanan, dan pencegahan penyalahgunaan.</li>
                <li>Komunikasi terkait layanan, dukungan, dan informasi penting akun.</li>
            </ul>

            <h2>3. Perlindungan dan Penyimpanan Data</h2>
            <p>
                Kami menerapkan langkah teknis dan administratif yang wajar untuk melindungi data dari akses tidak sah,
                perubahan, pengungkapan, atau penghancuran yang tidak sah.
            </p>

            <h2>4. Pembagian Data</h2>
            <p>
                Kami tidak menjual data pribadi Anda. Data hanya dibagikan jika diperlukan untuk operasional layanan,
                kepatuhan hukum, atau atas persetujuan Anda.
            </p>

            <h2>5. Hak Pengguna</h2>
            <ul>
                <li>Meminta akses, koreksi, atau pembaruan data pribadi.</li>
                <li>Meminta penghapusan data sepanjang tidak bertentangan dengan kewajiban hukum.</li>
                <li>Menarik persetujuan pemrosesan data pada kondisi tertentu.</li>
            </ul>

            <h2>6. Kontak Privasi</h2>
            <p>
                Jika Anda memiliki pertanyaan mengenai kebijakan privasi ini, silakan hubungi kami melalui halaman
                <a href="{{ route('contact') }}">Kontak Support</a>.
            </p>

            <div class="note">
                Kebijakan ini dapat diperbarui dari waktu ke waktu untuk menyesuaikan perubahan layanan atau regulasi.
            </div>
        </div>
    </div>
</body>
</html>
