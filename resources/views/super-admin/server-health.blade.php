@extends('layouts.admin')

@section('title', 'Server Health')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-server mr-2 text-primary"></i>Server Health</h4>
            <small class="text-muted">Status layanan dan sumber daya server</small>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
        </div>
    </div>

    {{-- Queue Stats --}}
    <div class="row">
        <div class="col-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon {{ $pendingJobs > 100 ? 'bg-warning' : 'bg-info' }}">
                    <i class="fas fa-tasks"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Antrian Pending</span>
                    <span class="info-box-number">{{ number_format($pendingJobs) }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon {{ $failedJobs > 0 ? 'bg-danger' : 'bg-success' }}">
                    <i class="fas fa-exclamation-triangle"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Failed Jobs</span>
                    <span class="info-box-number">{{ number_format($failedJobs) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Services Status + Restart --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cogs mr-1"></i> Status Layanan</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Layanan</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $svc)
                            @php
                                $isActive = $svc['status'] === 'active';
                                $badgeClass = $isActive ? 'badge-success' : 'badge-danger';
                                $icon = $isActive ? 'fa-check-circle' : 'fa-times-circle';
                                $statusLabel = $isActive ? 'Running' : strtoupper($svc['status'] ?: 'Unknown');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $svc['name'] }}</strong><br>
                                    <code class="small text-muted">{{ $svc['unit'] }}</code>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $badgeClass }}" data-badge-unit="{{ $svc['unit'] }}">
                                        <i class="fas {{ $icon }} mr-1"></i>{{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-warning btn-restart"
                                        data-unit="{{ $svc['unit'] }}"
                                        data-name="{{ $svc['name'] }}"
                                        title="Restart {{ $svc['name'] }}">
                                        <i class="fas fa-redo-alt"></i> Restart
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Resource Usage --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-microchip mr-1"></i> Penggunaan Sumber Daya</h5>
                </div>
                <div class="card-body">
                    {{-- Disk --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="fas fa-hdd mr-1 text-info"></i> <strong>Disk (/)</strong></span>
                            <span class="text-muted">
                                {{ $diskInfo['used_h'] }} / {{ $diskInfo['total_h'] }}
                                &nbsp;
                                <strong class="{{ $diskInfo['percent'] >= 90 ? 'text-danger' : ($diskInfo['percent'] >= 75 ? 'text-warning' : 'text-success') }}">
                                    {{ $diskInfo['percent'] }}%
                                </strong>
                            </span>
                        </div>
                        <div class="progress" style="height: 18px;">
                            @php $diskColor = $diskInfo['percent'] >= 90 ? 'bg-danger' : ($diskInfo['percent'] >= 75 ? 'bg-warning' : 'bg-info'); @endphp
                            <div class="progress-bar {{ $diskColor }}" role="progressbar"
                                style="width: {{ $diskInfo['percent'] }}%">
                                {{ $diskInfo['percent'] }}%
                            </div>
                        </div>
                    </div>

                    {{-- Memory --}}
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="fas fa-memory mr-1 text-primary"></i> <strong>Memory (RAM)</strong></span>
                            <span class="text-muted" id="memUsageText">
                                {{ $memInfo['used_h'] }} / {{ $memInfo['total_h'] }}
                                &nbsp;
                                <strong id="memPercent" class="{{ $memInfo['percent'] >= 90 ? 'text-danger' : ($memInfo['percent'] >= 75 ? 'text-warning' : 'text-success') }}">
                                    {{ $memInfo['percent'] }}%
                                </strong>
                            </span>
                        </div>
                        <div class="progress" style="height: 18px;">
                            @php $memColor = $memInfo['percent'] >= 90 ? 'bg-danger' : ($memInfo['percent'] >= 75 ? 'bg-warning' : 'bg-primary'); @endphp
                            <div class="progress-bar {{ $memColor }}" id="memProgressBar" role="progressbar"
                                style="width: {{ $memInfo['percent'] }}%">
                                {{ $memInfo['percent'] }}%
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" id="btnClearRam" class="btn btn-sm btn-warning">
                        <i class="fas fa-broom mr-1"></i> Clear RAM (Drop Page Cache)
                    </button>
                    <small class="text-muted ml-2">Membersihkan cache OS yang tidak terkait Rafen</small>
                </div>
            </div>

            @if($failedJobs > 0)
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Terdapat <strong>{{ $failedJobs }}</strong> failed job.
                Periksa log dan hapus job yang tidak relevan via <code>php artisan queue:failed</code>.
            </div>
            @endif
        </div>
    </div>

    <p class="text-muted small text-right">
        <i class="fas fa-clock mr-1"></i> Data diambil pada: {{ now()->format('d/m/Y H:i:s') }}
    </p>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
const restartUrlTemplate = "{{ route('super-admin.server-health.restart', ':svc') }}";
const clearRamUrl = "{{ route('super-admin.server-health.clear-ram') }}";

function updateServiceBadge(unit, status) {
    const badge = document.querySelector('[data-badge-unit="' + unit + '"]');
    if (!badge) return;
    const isActive = status === 'active';
    badge.className = 'badge ' + (isActive ? 'badge-success' : 'badge-danger');
    badge.setAttribute('data-badge-unit', unit);
    badge.innerHTML = '<i class="fas ' + (isActive ? 'fa-check-circle' : 'fa-times-circle') + ' mr-1"></i>'
        + (isActive ? 'Running' : (status.toUpperCase() || 'Unknown'));
}

// Restart service
$(document).on('click', '.btn-restart', function () {
    const btn = $(this);
    const unit = btn.data('unit');
    const name = btn.data('name');

    if (!confirm('Restart ' + name + '? Layanan akan berhenti sebentar.')) return;

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Restarting...');

    const url = restartUrlTemplate.replace(':svc', encodeURIComponent(unit));

    $.ajax({
        url: url,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        success: function (res) {
            window.AppAjax.showToast(res.message, res.success ? 'success' : 'danger');
            updateServiceBadge(unit, res.status);
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message ?? 'Gagal melakukan restart.';
            window.AppAjax.showToast(msg, 'danger');
        },
        complete: function () {
            btn.prop('disabled', false).html('<i class="fas fa-redo-alt"></i> Restart');
        }
    });
});

// Clear RAM
$('#btnClearRam').on('click', function () {
    if (!confirm('Drop page cache RAM? Proses aman — tidak mempengaruhi aplikasi yang sedang berjalan.')) return;

    const btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Membersihkan...');

    $.ajax({
        url: clearRamUrl,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        success: function (res) {
            window.AppAjax.showToast(res.message + ' RAM sekarang: ' + res.mem.used_h + ' / ' + res.mem.total_h + ' (' + res.mem.percent + '%)', 'success');

            // Update progress bar
            const pct = res.mem.percent;
            const color = pct >= 90 ? 'bg-danger' : (pct >= 75 ? 'bg-warning' : 'bg-primary');
            const textColor = pct >= 90 ? 'text-danger' : (pct >= 75 ? 'text-warning' : 'text-success');
            $('#memProgressBar')
                .removeClass('bg-danger bg-warning bg-primary bg-info')
                .addClass(color)
                .css('width', pct + '%')
                .text(pct + '%');
            $('#memPercent')
                .removeClass('text-danger text-warning text-success')
                .addClass(textColor)
                .text(pct + '%');
        },
        error: function () {
            window.AppAjax.showToast('Gagal membersihkan RAM.', 'danger');
        },
        complete: function () {
            btn.prop('disabled', false).html('<i class="fas fa-broom mr-1"></i> Clear RAM (Drop Page Cache)');
        }
    });
});
</script>
@endpush

