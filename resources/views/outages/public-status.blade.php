<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Status Gangguan – {{ $settings?->business_name ?? 'ISP' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            padding: 24px 16px 40px;
        }
        .container { max-width: 680px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 28px; }
        .header .isp-name { font-size: 1rem; color: #94a3b8; margin-bottom: 8px; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border-radius: 999px;
            font-weight: 700; font-size: 1rem; margin-bottom: 12px;
        }
        .status-open        { background: #dc2626; color: #fff; }
        .status-in_progress { background: #d97706; color: #fff; }
        .status-resolved    { background: #16a34a; color: #fff; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: currentColor; animation: pulse 1.5s infinite; }
        .status-resolved .dot { animation: none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 24px; margin-bottom: 16px;
        }
        .card-title { font-weight: 700; font-size: 1.05rem; margin-bottom: 14px; color: #cbd5e1; }
        .info-row { display: flex; gap: 8px; margin-bottom: 8px; font-size: .9rem; }
        .info-label { color: #64748b; min-width: 140px; flex-shrink: 0; }
        .info-value { color: #e2e8f0; }
        .area-badge {
            display: inline-block; background: rgba(99,102,241,.3); border: 1px solid rgba(99,102,241,.5);
            color: #a5b4fc; border-radius: 6px; padding: 3px 10px; font-size: .82rem; margin: 3px 3px 3px 0;
        }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content:''; position:absolute; left:7px; top:0; bottom:0; width:2px; background: rgba(255,255,255,.1); }
        .tl-item { position: relative; margin-bottom: 20px; }
        .tl-dot {
            position: absolute; left: -24px; top: 4px;
            width: 14px; height: 14px; border-radius: 50%;
            background: #475569; border: 2px solid #64748b;
        }
        .tl-dot.resolved { background: #16a34a; border-color: #4ade80; }
        .tl-dot.created  { background: #2563eb; border-color: #60a5fa; }
        .tl-time { color: #64748b; font-size: .78rem; margin-bottom: 3px; }
        .tl-user { font-weight: 600; font-size: .88rem; color: #94a3b8; margin-bottom: 4px; }
        .tl-body { font-size: .9rem; line-height: 1.5; white-space: pre-wrap; }
        .tl-meta { font-size: .82rem; color: #64748b; font-style: italic; }
        .tl-img { max-width: 100%; border-radius: 10px; margin-top: 8px; max-height: 240px; object-fit: cover; }
        .footer { text-align: center; color: #475569; font-size: .8rem; margin-top: 24px; }
        .contact-link { color: #60a5fa; text-decoration: none; }
        .refresh-note { text-align: center; font-size: .78rem; color: #475569; margin-top: 8px; }
        h1 { font-size: 1.35rem; font-weight: 700; line-height: 1.3; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="isp-name">{{ $settings?->business_name ?? 'ISP' }}</div>
        @php
        $statusClass = ['open'=>'status-open','in_progress'=>'status-in_progress','resolved'=>'status-resolved'];
        $statusText  = ['open'=>'Gangguan Aktif','in_progress'=>'Sedang Diperbaiki','resolved'=>'Layanan Pulih'];
        $statusIcon  = ['open'=>'⚠️','in_progress'=>'🔧','resolved'=>'✅'];
        @endphp
        <div class="status-badge {{ $statusClass[$outage->status]??'status-open' }}">
            <span class="dot"></span>
            {{ $statusIcon[$outage->status]??'⚠️' }} {{ $statusText[$outage->status]??$outage->status }}
        </div>
        <h1>{{ $outage->title }}</h1>
    </div>

    <div class="card">
        <div class="card-title">Informasi Gangguan</div>
        <div class="info-row">
            <span class="info-label">Waktu mulai</span>
            <span class="info-value">{{ $outage->started_at->format('d M Y, H:i') }} WIB</span>
        </div>
        @if($outage->estimated_resolved_at)
        <div class="info-row">
            <span class="info-label">Estimasi selesai</span>
            <span class="info-value">{{ $outage->estimated_resolved_at->format('d M Y, H:i') }} WIB</span>
        </div>
        @endif
        @if($outage->resolved_at)
        <div class="info-row">
            <span class="info-label">Diselesaikan</span>
            <span class="info-value">{{ $outage->resolved_at->format('d M Y, H:i') }} WIB</span>
        </div>
        @endif
        @if($outage->description)
        <div class="info-row">
            <span class="info-label">Keterangan</span>
            <span class="info-value">{{ $outage->description }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Area terdampak</span>
            <span class="info-value">
                @foreach($outage->affectedAreas as $area)
                    <span class="area-badge">{{ $area->display_label }}</span>
                @endforeach
            </span>
        </div>
    </div>

    @if($outage->updates->isNotEmpty())
    <div class="card">
        <div class="card-title">📋 Riwayat Perbaikan</div>
        <div class="timeline">
            @foreach($outage->updates->sortByDesc('created_at') as $update)
            <div class="tl-item">
                <div class="tl-dot {{ $update->type === 'resolved' ? 'resolved' : ($update->type === 'created' ? 'created' : '') }}"></div>
                <div class="tl-time">{{ $update->created_at->format('d M Y, H:i') }}</div>
                @if($update->user)
                <div class="tl-user">{{ $update->user->nickname ?? $update->user->name }}</div>
                @endif
                @if($update->meta)
                <div class="tl-meta">{{ $update->meta }}</div>
                @endif
                @if($update->body)
                <div class="tl-body">{{ $update->body }}</div>
                @endif
                @if($update->image_path)
                <a href="{{ asset('storage/'.$update->image_path) }}" target="_blank">
                    <img src="{{ asset('storage/'.$update->image_path) }}" alt="Foto update" class="tl-img">
                </a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card" style="text-align:center">
        <p style="font-size:.9rem;color:#94a3b8;margin-bottom:10px">
            Tim kami bekerja secepatnya memulihkan layanan Anda.<br>Mohon maaf atas ketidaknyamanannya.
        </p>
        @if($settings?->business_phone)
        <p style="font-size:.88rem">
            Hubungi kami:
            <a class="contact-link" href="https://wa.me/{{ phone_to_wa($settings->business_phone) }}">
                📞 {{ $settings->business_phone }}
            </a>
        </p>
        @endif
    </div>

    <div class="refresh-note">Halaman ini diperbarui otomatis setiap 2 menit · Terakhir diperbarui: {{ now()->format('H:i:s') }}</div>
    <div class="footer" style="margin-top:12px">Powered by Rafen ISP Management</div>
</div>
</body>
</html>
