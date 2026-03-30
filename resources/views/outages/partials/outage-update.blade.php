@php
$typeConfig = [
    'created'       => ['icon' => 'fas fa-plus-circle',  'color' => 'text-success', 'bg' => 'bg-success'],
    'status_change' => ['icon' => 'fas fa-exchange-alt', 'color' => 'text-warning', 'bg' => 'bg-warning'],
    'note'          => ['icon' => 'fas fa-comment-dots', 'color' => 'text-info',    'bg' => 'bg-info'],
    'resolved'      => ['icon' => 'fas fa-check-circle', 'color' => 'text-success', 'bg' => 'bg-success'],
    'assigned'      => ['icon' => 'fas fa-user-check',   'color' => 'text-primary', 'bg' => 'bg-primary'],
];
$cfg      = $typeConfig[$update->type] ?? ['icon' => 'fas fa-info-circle', 'color' => 'text-muted', 'bg' => 'bg-secondary'];
$userName = $update->user ? ($update->user->nickname ?? $update->user->name) : 'Sistem';
@endphp
<div class="time-label ou-animate">
    <span class="ou-time-label">
        <i class="fas fa-clock"></i> {{ $update->created_at?->format('d/m/Y H:i') }}
    </span>
</div>
<div class="ou-animate">
    <i class="{{ $cfg['icon'] }} {{ $cfg['bg'] }}"></i>
    <div class="timeline-item">
        <div class="timeline-header d-flex align-items-center flex-wrap gap-1">
            <span class="ou-username {{ $cfg['color'] }}">{{ $userName }}</span>
            @if(!$update->is_public)
                <span class="ou-internal-badge"><i class="fas fa-eye-slash fa-xs"></i> internal</span>
            @endif
        </div>
        @if($update->meta)
            <div class="timeline-body ou-meta-row">
                <i class="fas fa-arrow-right fa-xs mr-1"></i>{{ $update->meta }}
            </div>
        @endif
        @if($update->body)
            <div class="timeline-body ou-body-text">{{ $update->body }}</div>
        @endif
        @if($update->image_path)
            <div class="timeline-body">
                <a href="{{ asset('storage/'.$update->image_path) }}" target="_blank">
                    <img src="{{ asset('storage/'.$update->image_path) }}" alt="Foto update" class="ou-img">
                </a>
            </div>
        @endif
    </div>
</div>
