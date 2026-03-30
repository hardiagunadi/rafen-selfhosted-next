<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; font-size: 15px; color: #333; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
        .header { background: linear-gradient(90deg, #0a3e68, #0f6b95); padding: 28px 32px; text-align: center; }
        .header h1 { margin: 0; color: #fff; font-size: 22px; font-weight: 700; letter-spacing: .01em; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,.75); font-size: 13px; }
        .body { padding: 28px 32px; }
        .footer { background: #f8f9fc; padding: 18px 32px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #e9ecef; }
        .btn { display: inline-block; padding: 12px 28px; background: linear-gradient(90deg, #0f6b95, #0c8a8f); color: #fff !important; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; margin: 16px 0; }
        .btn:hover { opacity: .9; }
        .info-box { background: #f0f6fb; border-left: 4px solid #0f6b95; border-radius: 0 8px 8px 0; padding: 14px 18px; margin: 16px 0; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 4px 0; vertical-align: top; }
        .info-box td:first-child { color: #666; width: 160px; font-size: 13px; }
        .info-box td:last-child { font-weight: 600; color: #1a2e45; }
        .plan-card { border: 1px solid #d0dbe8; border-radius: 8px; padding: 14px 18px; margin: 8px 0; }
        .plan-card h4 { margin: 0 0 4px; color: #0a3e68; font-size: 15px; }
        .plan-card .price { font-size: 18px; font-weight: 700; color: #0f6b95; }
        .plan-card .desc { font-size: 13px; color: #666; margin-top: 4px; }
        .badge-warning { display: inline-block; background: #fff3cd; color: #856404; border-radius: 6px; padding: 4px 10px; font-size: 13px; font-weight: 600; }
        .badge-success { display: inline-block; background: #d1e7dd; color: #0f5132; border-radius: 6px; padding: 4px 10px; font-size: 13px; font-weight: 600; }
        .badge-danger  { display: inline-block; background: #f8d7da; color: #842029; border-radius: 6px; padding: 4px 10px; font-size: 13px; font-weight: 600; }
        h2 { color: #0a3e68; font-size: 18px; margin: 0 0 12px; }
        p { margin: 0 0 12px; line-height: 1.6; }
        hr { border: none; border-top: 1px solid #e9ecef; margin: 20px 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <p>Sistem Manajemen ISP</p>
    </div>
    <div class="body">
        @yield('content')
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; Email ini dikirim otomatis, mohon tidak membalas langsung.<br>
        <a href="{{ config('app.url') }}" style="color:#0f6b95;">{{ config('app.url') }}</a>
    </div>
</div>
</body>
</html>
