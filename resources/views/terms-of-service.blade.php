<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ketentuan Layanan Rafen — {{ config('app.name', 'RAFEN Manager') }}</title>
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
            Kembali
        </a>

        <div class="card">
            <h1>Ketentuan Layanan Rafen</h1>
            <p class="meta">Berlaku efektif: {{ now()->format('d M Y') }}</p>

            <p>
                Dengan menggunakan layanan Rafen, Anda menyetujui ketentuan penggunaan berikut.
                Jika Anda tidak setuju dengan ketentuan ini, harap tidak menggunakan layanan kami.
            </p>

            <h2>1. Cakupan Layanan</h2>
            <p>
                Rafen menyediakan platform manajemen operasional jaringan dan pelanggan untuk kebutuhan bisnis ISP.
                Fitur dapat berubah, ditambah, atau disesuaikan dari waktu ke waktu.
            </p>

            <h2>2. Tanggung Jawab Pengguna</h2>
            <ul>
                <li>Menjaga kerahasiaan akun, kredensial, dan akses sistem.</li>
                <li>Menggunakan layanan sesuai hukum dan peraturan yang berlaku.</li>
                <li>Tidak melakukan aktivitas yang mengganggu keamanan atau stabilitas layanan.</li>
            </ul>

            <h2>3. Ketersediaan Layanan</h2>
            <p>
                Kami berupaya menjaga layanan tetap tersedia, namun tidak menjamin bebas gangguan sepenuhnya.
                Pemeliharaan sistem atau kondisi di luar kendali dapat memengaruhi ketersediaan layanan.
            </p>

            <h2>4. Pembatasan Tanggung Jawab</h2>
            <p>
                Rafen tidak bertanggung jawab atas kerugian tidak langsung, insidental, atau konsekuensial
                yang timbul dari penggunaan layanan, sejauh diizinkan oleh hukum yang berlaku.
            </p>

            <h2>5. Penghentian Layanan</h2>
            <p>
                Kami berhak menangguhkan atau menghentikan akses layanan jika ditemukan pelanggaran ketentuan
                atau penggunaan yang membahayakan sistem dan pengguna lain.
            </p>

            <h2>6. Perubahan Ketentuan</h2>
            <p>
                Ketentuan layanan dapat diperbarui sewaktu-waktu. Perubahan akan berlaku sejak dipublikasikan
                pada halaman ini.
            </p>

            <h2>7. Kebijakan Privasi</h2>
            <p>
                Penggunaan data pribadi Anda tunduk pada
                <a href="{{ route('privacy-policy') }}">Kebijakan Privasi Rafen</a>.
            </p>

            <div class="note">
                Untuk pertanyaan hukum atau operasional layanan, hubungi kami melalui
                <a href="{{ route('contact') }}">Kontak Support</a>.
            </div>
        </div>
    </div>
</body>
</html>
