@extends('layouts.admin')

@section('title', 'App Update')

@section('content')
@php
    $lastCheckStatus = $snapshot['last_check_status'] ?? 'never';
    $statusClass = match ($lastCheckStatus) {
        'ok' => 'success',
        'error' => 'danger',
        'not_configured', 'storage_unavailable' => 'warning',
        default => 'secondary',
    };
    $lastApplyStatus = $snapshot['last_apply_status'] ?? 'never';
    $applyStatusClass = match ($lastApplyStatus) {
        'success' => 'success',
        'failed' => 'danger',
        'dry_run' => 'info',
        default => 'secondary',
    };
    $heartbeatStatus = $heartbeatSummary['last_status'] ?? 'never';
    $heartbeatStatusClass = match ($heartbeatStatus) {
        'success' => 'success',
        'failed' => 'danger',
        'not_configured' => 'warning',
        default => 'secondary',
    };
    $heartbeatHealthClass = ($heartbeatSummary['is_stale'] ?? false)
        ? 'danger'
        : (($heartbeatSummary['is_configured'] ?? false) ? 'success' : 'warning');
@endphp
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-cloud-download-alt mr-2 text-primary"></i>App Update</h4>
            <small class="text-muted">Pantau release terbaru, jalankan simulasi apply, lalu terapkan update aktual lewat CLI saat maintenance window siap.</small>
        </div>
        <div class="col-auto">
            <div class="d-flex flex-wrap justify-content-end" style="gap:.5rem;">
                <form method="POST" action="{{ route('super-admin.settings.app-update.refresh-status') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark">
                        <i class="fas fa-redo-alt mr-1"></i> Refresh Status
                    </button>
                </form>
                <form method="POST" action="{{ route('super-admin.settings.app-update.check') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync-alt mr-1"></i> Cek Update Sekarang
                    </button>
                </form>
                <form method="POST" action="{{ route('super-admin.settings.app-update.check-and-heartbeat') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-broadcast-tower mr-1"></i> Cek Update + Heartbeat
                    </button>
                </form>
                <form method="POST" action="{{ route('super-admin.settings.app-update.preflight') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-info">
                        <i class="fas fa-vial mr-1"></i> Simulasi Apply
                    </button>
                </form>
                <form method="POST" action="{{ route('super-admin.settings.app-update.heartbeat') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="fas fa-heartbeat mr-1"></i> Kirim Heartbeat Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-server mr-1"></i> Status Instance Saat Ini</div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge badge-{{ ($snapshot['update_available'] ?? false) ? 'warning' : 'success' }} px-3 py-2">
                            {{ ($snapshot['update_available'] ?? false) ? 'Update Tersedia' : 'Up To Date / Belum Ada Selisih' }}
                        </span>
                        <span class="badge badge-{{ $statusClass }} px-3 py-2">Check: {{ strtoupper((string) $lastCheckStatus) }}</span>
                        <span class="badge badge-{{ $applyStatusClass }} px-3 py-2">Apply: {{ strtoupper((string) $lastApplyStatus) }}</span>
                        <span class="badge badge-{{ $heartbeatStatusClass }} px-3 py-2">Heartbeat: {{ strtoupper((string) $heartbeatStatus) }}</span>
                        <span class="badge badge-{{ $heartbeatHealthClass }} px-3 py-2">
                            Sync: {{ ($heartbeatSummary['is_stale'] ?? false) ? 'STALE' : 'HEALTHY' }}
                        </span>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Channel</th>
                                <td>{{ $snapshot['channel'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Current Version</th>
                                <td><code>{{ $snapshot['current_version'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Current Commit</th>
                                <td><code>{{ $snapshot['current_commit'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Current Ref</th>
                                <td><code>{{ $snapshot['current_ref'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Manifest URL</th>
                                <td>
                                    @if(!empty($snapshot['manifest_url']))
                                        <code>{{ $snapshot['manifest_url'] }}</code>
                                    @else
                                        <span class="text-muted">Belum dikonfigurasi</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Check Terakhir</th>
                                <td>{{ ($snapshot['last_checked_at'] ?? null)?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Pesan</th>
                                <td>{{ $snapshot['last_check_message'] ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-box-open mr-1"></i> Release Terbaru Dari Manifest</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Latest Version</th>
                                <td><code>{{ $snapshot['latest_version'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Latest Commit</th>
                                <td><code>{{ $snapshot['latest_commit'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Latest Ref</th>
                                <td><code>{{ $snapshot['latest_ref'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Published At</th>
                                <td>{{ ($snapshot['latest_published_at'] ?? null)?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Manifest Terakhir</th>
                                <td>
                                    @if(!empty($snapshot['latest_manifest_url']))
                                        <code>{{ $snapshot['latest_manifest_url'] }}</code>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Release Notes</th>
                                <td>
                                    @if(!empty($snapshot['latest_release_notes_url']))
                                        <a href="{{ $snapshot['latest_release_notes_url'] }}" target="_blank" rel="noopener noreferrer">
                                            Buka Release Notes
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @php($manifestPayload = $snapshot['manifest_payload'] ?? null)
                    @if(is_array($manifestPayload) && $manifestPayload !== [])
                        <div class="border-top pt-3 mt-3">
                            <div class="small text-muted mb-2">Flag release</div>
                            <div class="d-flex flex-wrap" style="gap:.5rem;">
                                <span class="badge badge-{{ !empty($manifestPayload['requires_maintenance']) ? 'warning' : 'secondary' }}">
                                    Maintenance {{ !empty($manifestPayload['requires_maintenance']) ? 'Required' : 'Not Required' }}
                                </span>
                                <span class="badge badge-{{ !empty($manifestPayload['requires_backup']) ? 'warning' : 'secondary' }}">
                                    Backup {{ !empty($manifestPayload['requires_backup']) ? 'Required' : 'Not Required' }}
                                </span>
                                <span class="badge badge-{{ !empty($manifestPayload['requires_migration']) ? 'warning' : 'secondary' }}">
                                    Migration {{ !empty($manifestPayload['requires_migration']) ? 'Required' : 'Not Required' }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-satellite-dish mr-1"></i> Sinkronisasi SaaS</div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge badge-{{ ($heartbeatSummary['is_configured'] ?? false) ? 'info' : 'warning' }} px-3 py-2">
                            {{ ($heartbeatSummary['is_configured'] ?? false) ? 'Heartbeat Terkonfigurasi' : 'Heartbeat Belum Terkonfigurasi' }}
                        </span>
                        <span class="badge badge-{{ $heartbeatStatusClass }} px-3 py-2">Status: {{ strtoupper((string) $heartbeatStatus) }}</span>
                        <span class="badge badge-{{ $heartbeatHealthClass }} px-3 py-2">
                            {{ ($heartbeatSummary['is_stale'] ?? false) ? 'Heartbeat Stale' : 'Heartbeat Healthy' }}
                        </span>
                    </div>

                    @if($heartbeatSummary['is_stale'] ?? false)
                    <div class="alert alert-danger">
                        <div class="font-weight-bold mb-1">Perlu perhatian</div>
                        {{ $heartbeatSummary['stale_reason'] ?? 'Heartbeat ke SaaS sudah stale.' }}
                    </div>
                    @endif

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Endpoint</th>
                                <td>
                                    @if(!empty($heartbeatSummary['endpoint']))
                                        <code>{{ $heartbeatSummary['endpoint'] }}</code>
                                    @else
                                        <span class="text-muted">Belum bisa diturunkan dari registry URL</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Heartbeat Terakhir</th>
                                <td>{{ ($heartbeatSummary['last_sent_at'] ?? null)?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Heartbeat Sukses Terakhir</th>
                                <td>{{ ($heartbeatSummary['last_successful_sent_at'] ?? null)?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>SaaS Status ID</th>
                                <td><code>{{ $heartbeatSummary['last_status_id'] ?? '-' }}</code></td>
                            </tr>
                            <tr>
                                <th>Pesan</th>
                                <td>{{ $heartbeatSummary['last_message'] ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="small text-muted mt-3 mb-0">
                        Heartbeat dikirim otomatis setelah cek/apply update dan oleh scheduler berkala setiap 30 menit.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-terminal mr-1"></i> Jalur Apply Yang Direkomendasikan</div>
                <div class="card-body">
                    <p class="text-muted mb-3">Apply aktual direkomendasikan lewat CLI supaya perubahan Git checkout, composer install, migration, dan maintenance mode tidak berjalan dari request web.</p>

                    <div class="mb-3">
                        <div class="small text-muted mb-1">Dry-run</div>
                        <pre class="bg-light border rounded p-3 mb-0"><code>php artisan self-hosted:update:apply --dry-run</code></pre>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted mb-1">Apply aktual</div>
                        <pre class="bg-light border rounded p-3 mb-0"><code>php artisan self-hosted:update:apply --yes</code></pre>
                    </div>

                    @if(!empty($snapshot['rollback_ref']))
                    <div class="alert alert-warning mb-0">
                        <div class="font-weight-bold mb-1">Rollback Ref Tersimpan</div>
                        <code>{{ $snapshot['rollback_ref'] }}</code>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6 mt-3">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-clipboard-check mr-1"></i> Apply Terakhir</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Status</th>
                                <td><span class="badge badge-{{ $applyStatusClass }}">{{ strtoupper((string) $lastApplyStatus) }}</span></td>
                            </tr>
                            <tr>
                                <th>Waktu</th>
                                <td>{{ ($snapshot['last_applied_at'] ?? null)?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Pesan</th>
                                <td>{{ $snapshot['last_apply_message'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Rollback Ref</th>
                                <td><code>{{ $snapshot['rollback_ref'] ?? '-' }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="fas fa-history mr-1"></i> Riwayat Run Update</div>
        <div class="card-body p-0">
            @if(($recentRuns ?? collect())->isEmpty())
                <div class="p-3 text-muted">Belum ada riwayat apply atau simulasi update.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Aksi</th>
                                <th>Status</th>
                                <th>Target</th>
                                <th>Rollback Ref</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentRuns as $run)
                                <tr>
                                    <td>{{ $run->id }}</td>
                                    <td>{{ strtoupper($run->action) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $run->status === 'success' ? 'success' : ($run->status === 'failed' ? 'danger' : ($run->status === 'dry_run' ? 'info' : 'secondary')) }}">
                                            {{ strtoupper($run->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div><code>{{ $run->target_version ?? '-' }}</code></div>
                                        <div class="small text-muted"><code>{{ $run->target_ref ?? '-' }}</code></div>
                                    </td>
                                    <td><code>{{ $run->rollback_ref ?? '-' }}</code></td>
                                    <td>{{ $run->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td>{{ $run->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
