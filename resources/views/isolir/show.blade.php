<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: {{ $bgColor }};
            --accent: {{ $accentColor }};
        }

        body {
            background: var(--bg);
            color: #f0f0f0;
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            padding: 40px 36px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            border: 2px solid var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-wrap svg {
            width: 40px;
            height: 40px;
            fill: var(--accent);
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .body-text {
            font-size: 0.95rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.78);
            margin-bottom: 28px;
            white-space: pre-line;
        }

        .divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 0 auto 24px;
        }

        .contact-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 10px;
        }

        .contact-info {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
        }

        .business-name {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.35);
            margin-top: 28px;
        }

        @if(isset($isPreview) && $isPreview)
        .preview-badge {
            position: fixed;
            top: 12px;
            right: 12px;
            background: #f39c12;
            color: #000;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 1px;
        }
        @endif
    </style>
</head>
<body>
    @php
        $serverLoadTimeMs = defined('LARAVEL_START')
            ? (microtime(true) - LARAVEL_START) * 1000
            : null;
    @endphp

    @if(isset($isPreview) && $isPreview)
        <div class="preview-badge">PREVIEW</div>
    @endif

    <div class="card">
        <div class="icon-wrap">
            {{-- Icon WiFi Off --}}
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M2.41 2L1 3.41l2.27 2.27A14.97 14.97 0 0 0 1 8.94l1.97 1.97C4.15 9.47 5.45 8.52 6.91 7.89l1.49 1.49A9.97 9.97 0 0 0 5 12.93l1.97 1.97C8.1 13.63 9.44 12.9 11 12.45V12c0-.74.1-1.46.27-2.15L8.49 7.07A12 12 0 0 0 3 9.61L1.03 7.64A14 14 0 0 1 5.65 4.23L4.2 2.78 2.41 2zM23 8.94a14.97 14.97 0 0 0-5.93-4.04l-1.42 1.42A12 12 0 0 1 21.03 9.6L19 11.64A9.97 9.97 0 0 0 13 7.76V8c0 .11-.01.22-.01.33l2.88 2.88C16.83 11.14 18 12 19 12.94L21 11c0-.02-.01-.04-.01-.06zm-5 4L12 7l-.01-.01L6 1 4.59 2.41 21.59 19.41 23 18l-5-5.06zM12 21c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
            </svg>
        </div>

        <h1>{{ $title }}</h1>

        <p class="body-text">{{ $body }}</p>

        @if($contact)
            <div class="divider"></div>
            <p class="contact-label">Hubungi Kami</p>
            <p class="contact-info">{{ $contact }}</p>
        @endif

        @if($settings->business_name)
            <p class="business-name">{{ $settings->business_name }}</p>
        @endif

        @if($serverLoadTimeMs !== null)
            <p class="business-name">Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</p>
        @endif
    </div>

</body>
</html>
