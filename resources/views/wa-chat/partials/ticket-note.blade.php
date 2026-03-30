@php
$iconMap = [
    'created'       => ['fa-plus-circle',  'created'],
    'assigned'      => ['fa-user-tag',     'assigned'],
    'reassigned'    => ['fa-random',       'reassigned'],
    'status_change' => ['fa-exchange-alt', 'status_change'],
    'note'          => ['fa-comment',      'note'],
];
$badgeMap = [
    'created' => ['Dibuat', 'created'],
    'assigned' => ['Assign Baru', 'assigned'],
    'reassigned' => ['Assign Ulang', 'reassigned'],
    'status_change' => ['Status', 'status_change'],
    'note' => ['Catatan', 'note'],
];
[$icon, $cls] = $iconMap[$note->type] ?? ['fa-circle', 'note'];
$badge = $badgeMap[$note->type] ?? ['Aktivitas', 'note'];
$userName = $note->user ? ($note->user->nickname ?? $note->user->name) : '-';
@endphp
<div class="timeline-item">
    <div class="tl-icon {{ $cls }}">
        <i class="fas {{ $icon }}"></i>
    </div>
    <div class="tl-body">
        <div class="tl-header">
            <strong>{{ $userName }}</strong>
            <span class="tl-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
        </div>
        @if($note->note)
        <div class="tl-note">{{ $note->note }}</div>
        @endif
        @if($note->image_path)
        <img src="{{ asset('storage/' . $note->image_path) }}" class="tl-img ticket-lightbox-img" alt="foto bukti">
        @endif
        <div class="tl-meta">
            @if($note->meta)<span>{{ $note->meta }}</span> · @endif
            {{ $note->created_at->format('d/m/Y H:i') }}
        </div>
    </div>
</div>
