<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kontak Support — {{ config('app.name', 'RAFEN Manager') }}</title>
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
        .container { max-width: 600px; width: 100%; }
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
            padding: 2.5rem;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .logo img { height: 40px; width: auto; }
        .logo-text { font-size: 1.25rem; font-weight: 600; color: #1b1b18; }
        h1 { font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem 0; }
        .subtitle { color: #706f6c; font-size: 0.875rem; margin: 0 0 2rem 0; }
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 1rem 0;
            border-bottom: 1px solid #e3e3e0;
        }
        .contact-item:last-child { border-bottom: none; }
        .contact-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f4f4f1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-icon svg { width: 18px; height: 18px; }
        .contact-label { font-size: 0.75rem; color: #706f6c; margin-bottom: 2px; }
        .contact-value { font-size: 0.9375rem; font-weight: 500; color: #1b1b18; word-break: break-all; }
        .contact-value a { color: #1b1b18; text-decoration: none; }
        .contact-value a:hover { text-decoration: underline; }
        .hours {
            margin-top: 2rem;
            padding: 1rem;
            background: #f4f4f1;
            border-radius: 8px;
            font-size: 0.8125rem;
            color: #706f6c;
        }
        .hours strong { color: #1b1b18; }
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
            <div class="logo">
                @if (file_exists(public_path('branding/rafen-logo.svg')))
                    <img src="{{ asset('branding/rafen-logo.svg') }}" alt="RAFEN Logo">
                @else
                    <span class="logo-text">{{ config('app.name', 'RAFEN Manager') }}</span>
                @endif
            </div>

            <h1>Hubungi Support</h1>
            <p class="subtitle">Tim kami siap membantu Anda. Silakan hubungi kami melalui salah satu saluran berikut.</p>

            <div class="contact-item">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4Z" stroke="#1b1b18" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 6L12 13L2 6" stroke="#1b1b18" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div class="contact-label">Email</div>
                    <div class="contact-value">
                        <a href="mailto:hardiagunadi@gmail.com">hardiagunadi@gmail.com</a>
                    </div>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 16.92V19.92C22 20.48 21.76 21.02 21.33 21.4C20.91 21.78 20.34 21.97 19.77 21.92C16.56 21.59 13.48 20.53 10.74 18.85C8.2 17.31 6.05 15.17 4.51 12.62C2.82 9.87 1.77 6.78 1.44 3.55C1.39 2.98 1.58 2.42 1.95 2C2.33 1.59 2.86 1.34 3.42 1.34H6.42C7.4 1.33 8.24 2.02 8.42 3C8.57 3.84 8.82 4.67 9.15 5.46C9.42 6.16 9.23 6.96 8.68 7.47L7.39 8.76C8.81 11.39 10.97 13.55 13.6 14.97L14.89 13.68C15.4 13.13 16.2 12.94 16.9 13.21C17.69 13.54 18.52 13.79 19.36 13.94C20.35 14.12 21.05 14.97 21 15.97L22 16.92Z" stroke="#1b1b18" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div class="contact-label">Nomor Telepon / WhatsApp</div>
                    <div class="contact-value">
                        <a href="https://wa.me/6282220243698">+62 822-2024-3698</a>
                    </div>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 10C21 17 12 23 12 23C12 23 3 17 3 10C3 7.61 3.95 5.32 5.64 3.64C7.32 1.95 9.61 1 12 1C14.39 1 16.68 1.95 18.36 3.64C20.05 5.32 21 7.61 21 10Z" stroke="#1b1b18" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 13C13.66 13 15 11.66 15 10C15 8.34 13.66 7 12 7C10.34 7 9 8.34 9 10C9 11.66 10.34 13 12 13Z" stroke="#1b1b18" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div class="contact-label">Alamat Usaha</div>
                    <div class="contact-value" style="word-break: normal;">
                        Dusun Tanjungsari RT.031 RW.008,<br>
                        Desa Binangun, Kecamatan Watumalang,<br>
                        Wonosobo, Jawa Tengah
                    </div>
                </div>
            </div>

            <div class="hours">
                <strong>Jam Operasional:</strong> Senin – Sabtu, 08.00 – 17.00 WIB<br>
                Untuk keperluan darurat, silakan hubungi via WhatsApp.
            </div>
        </div>
    </div>
</body>
</html>
