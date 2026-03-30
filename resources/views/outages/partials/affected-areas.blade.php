@foreach($outage->affectedAreas as $area)
    @if($area->area_type === 'odp')
        <span class="badge badge-primary mr-1 mb-1">
            <i class="fas fa-dot-circle mr-1"></i>{{ $area->display_label }}
        </span>
    @elseif($area->area_type === 'nas')
        <span class="badge badge-warning mr-1 mb-1" style="color:#fff;">
            <i class="fas fa-server mr-1"></i>{{ $area->display_label }}
        </span>
    @else
        <span class="badge badge-secondary mr-1 mb-1">
            <i class="fas fa-map-marker-alt mr-1"></i>{{ $area->label }}
        </span>
    @endif
@endforeach
@if($outage->affectedAreas->isEmpty())
    <span class="text-muted">–</span>
@endif
