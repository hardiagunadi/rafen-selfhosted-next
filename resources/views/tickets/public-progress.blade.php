<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if(!in_array($ticket->status, ['resolved', 'closed']))
    <meta http-equiv="refresh" content="120">
    @endif
    <title>Progres Tiket #{{ $ticket->id }} – {{ $settings?->business_name ?? 'ISP' }}</title>
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
        .status-open        { background: #2563eb; color: #fff; }
        .status-in_progress { background: #d97706; color: #fff; }
        .status-resolved    { background: #16a34a; color: #fff; }
        .status-closed      { background: #475569; color: #fff; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: currentColor; animation: pulse 1.5s infinite; }
        .status-resolved .dot, .status-closed .dot { animation: none; }
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
        .timeline { position: relative; padding-left: 28px; }
        .timeline::before { content:''; position:absolute; left:8px; top:4px; bottom:4px; width:2px; background:rgba(255,255,255,0.1); }
        .tl-item { position: relative; margin-bottom: 20px; }
        .tl-dot {
            position: absolute; left: -24px; top: 4px;
            width: 14px; height: 14px; border-radius: 50%;
            background: #334155; border: 2px solid #64748b;
        }
        .tl-dot.created      { border-color: #2563eb; background: #1e3a8a; }
        .tl-dot.status_change { border-color: #d97706; background: #78350f; }
        .tl-dot.assigned     { border-color: #a855f7; background: #581c87; }
        .tl-dot.note         { border-color: #16a34a; background: #14532d; }
        .tl-time { font-size: .75rem; color: #64748b; margin-bottom: 3px; }
        .tl-label { font-size: .8rem; font-weight: 600; color: #94a3b8; margin-bottom: 4px; }
        .tl-body { font-size: .88rem; color: #cbd5e1; white-space: pre-wrap; word-break: break-word; }
        .tl-status-arrow { color: #64748b; margin: 0 4px; }
        .contact-link { color: #22c55e; text-decoration: none; }
        .contact-link:hover { text-decoration: underline; }
        .footer { text-align: center; font-size: .75rem; color: #475569; margin-top: 24px; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header --}}
    <div class="header">
        <div class="isp-name">{{ $settings?->business_name ?? 'ISP' }}</div>
        @php
            $statusMap = [
                'open'        => ['label' => 'Tiket Diterima',    'class' => 'status-open'],
                'in_progress' => ['label' => 'Sedang Ditangani',  'class' => 'status-in_progress'],
                'resolved'    => ['label' => 'Selesai',           'class' => 'status-resolved'],
                'closed'      => ['label' => 'Ditutup',           'class' => 'status-closed'],
            ];
            $statusInfo = $statusMap[$ticket->status] ?? ['label' => $ticket->status, 'class' => 'status-closed'];
        @endphp
        <div class="status-badge {{ $statusInfo['class'] }}">
            <span class="dot"></span>
            {{ $statusInfo['label'] }}
        </div>
        <div style="font-size:1.2rem; font-weight:700; color:#f1f5f9;">{{ $ticket->title }}</div>
        <div style="font-size:.85rem; color:#64748b; margin-top:6px;">Tiket #{{ $ticket->id }}</div>
    </div>

    {{-- Info Tiket --}}
    <div class="card">
        <div class="card-title">Informasi Tiket</div>
        @php
            $typeMap = [
                'complaint'     => 'Komplain',
                'troubleshoot'  => 'Troubleshoot',
                'installation'  => 'Instalasi',
                'other'         => 'Lainnya',
            ];
        @endphp
        <div class="info-row">
            <span class="info-label">Jenis Pengaduan</span>
            <span class="info-value">{{ $typeMap[$ticket->type] ?? $ticket->type }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value">{{ $statusInfo['label'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Dibuat Pada</span>
            <span class="info-value">{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
        </div>
        @if($ticket->resolved_at)
        <div class="info-row">
            <span class="info-label">Diselesaikan</span>
            <span class="info-value">{{ $ticket->resolved_at->format('d/m/Y H:i') }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Ditangani Oleh</span>
            <span class="info-value">
                {{ $ticket->assignedTo?->nickname ?? $ticket->assignedTo?->name ?? 'Belum di-assign' }}
            </span>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="card">
        <div class="card-title">Timeline Pengerjaan</div>
        @if($ticket->notes->isEmpty())
            <div style="color:#64748b; font-size:.88rem;">Belum ada aktivitas.</div>
        @else
        <div class="timeline">
            @foreach($ticket->notes as $note)
            <div class="tl-item">
                <div class="tl-dot {{ $note->type }}"></div>
                <div class="tl-time">{{ $note->created_at->format('d/m/Y H:i') }}</div>
                @if($note->type === 'created')
                    <div class="tl-label">Tiket Dibuat</div>
                    @if($note->meta)
                    <div class="tl-body">{{ $note->meta }}</div>
                    @endif
                @elseif($note->type === 'status_change')
                    @php
                        // meta disimpan sebagai string: "old → new"
                        $parts    = explode(' → ', $note->meta ?? '', 2);
                        $oldRaw   = trim($parts[0] ?? '');
                        $newRaw   = trim($parts[1] ?? '');
                        $oldLabel = $statusMap[$oldRaw]['label'] ?? $oldRaw;
                        $newLabel = $statusMap[$newRaw]['label'] ?? $newRaw;
                    @endphp
                    <div class="tl-label">Perubahan Status</div>
                    <div class="tl-body">
                        {{ $oldLabel }}<span class="tl-status-arrow"> → </span>{{ $newLabel }}
                    </div>
                @elseif($note->type === 'assigned')
                    <div class="tl-label">Penugasan</div>
                    @if($note->meta)
                    <div class="tl-body">{{ $note->meta }}</div>
                    @endif
                @elseif($note->type === 'note')
                    <div class="tl-label">Update dari Tim Kami</div>
                    @if($note->note)
                    <div class="tl-body">{{ $note->note }}</div>
                    @endif
                    @if($note->image_path)
                    <div style="margin-top:8px;">
                        <img src="{{ asset('storage/'.$note->image_path) }}"
                             alt="Foto update"
                             style="max-width:100%; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                    </div>
                    @endif
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Kontak --}}
    @if($settings?->business_phone || $settings?->business_name)
    <div class="card">
        <div class="card-title">Butuh Bantuan?</div>
        <div style="font-size:.9rem; color:#94a3b8;">
            Hubungi kami jika ada pertanyaan lebih lanjut.
        </div>
        @if($settings?->business_phone)
        <div style="margin-top:12px;">
            <a href="https://wa.me/{{ phone_to_wa($settings->business_phone) }}"
               target="_blank" class="contact-link">
                &#9658; Chat WhatsApp {{ $settings->business_name ?? '' }}
            </a>
        </div>
        @endif
    </div>
    @endif

    <div class="footer">
        Halaman ini diperbarui otomatis setiap 2 menit.
        &nbsp;·&nbsp; {{ $settings?->business_name ?? 'ISP' }}
    </div>

</div>
</body>
</html>
