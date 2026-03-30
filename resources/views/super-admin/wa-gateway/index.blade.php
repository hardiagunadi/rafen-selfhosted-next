@extends('layouts.admin')

@section('title', 'WA Gateway Management')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fab fa-whatsapp mr-2 text-success"></i>WA Gateway Management</h4>
            <small class="text-muted">Monitor dan kelola versi Baileys library untuk WhatsApp Gateway</small>
        </div>
        <div class="col-auto">
            <form action="{{ route('super-admin.wa-gateway.check-update') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt mr-1"></i> Cek Update Sekarang
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle mr-1"></i>{{ session('warning') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle mr-1"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Info boxes --}}
    <div class="row">
        <div class="col-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon {{ $gatewayStatus['running'] ? 'bg-success' : 'bg-danger' }}">
                    <i class="fab fa-whatsapp"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Status Gateway</span>
                    <span class="info-box-number">
                        {{ $gatewayStatus['running'] ? 'Online' : 'Offline' }}
                    </span>
                    <span class="progress-description text-muted small">{{ $gatewayStatus['pm2_status'] ?? '-' }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fab fa-node-js"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Versi Baileys</span>
                    <span class="info-box-number">{{ $currentBaileys }}</span>
                    <span class="progress-description text-muted small">wa-multi-session v{{ $gatewayVersion }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            @php
                $hasUpdate = $updateInfo['has_update'] ?? false;
                $latestVersion = $updateInfo['latest_version'] ?? null;
            @endphp
            <div class="info-box">
                <span class="info-box-icon {{ $hasUpdate ? 'bg-warning' : 'bg-success' }}">
                    <i class="fas {{ $hasUpdate ? 'fa-arrow-circle-up' : 'fa-check-circle' }}"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Versi Terbaru</span>
                    <span class="info-box-number">{{ $latestVersion ?? 'Belum dicek' }}</span>
                    <span class="progress-description text-muted small">
                        @if($updateInfo)
                            Dicek: {{ \Carbon\Carbon::parse($updateInfo['checked_at'])->diffForHumans() }}
                        @else
                            Belum pernah dicek
                        @endif
                    </span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-mobile-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Session Aktif</span>
                    <span class="info-box-number">{{ $totalSessions }}</span>
                    <span class="progress-description text-muted small">Tersimpan di database</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Panel Status & Aksi --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cogs mr-1"></i> Status & Kontrol Gateway</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-3">
                        <tr>
                            <td class="text-muted" width="40%">Status</td>
                            <td>
                                <span class="badge {{ $gatewayStatus['running'] ? 'badge-success' : 'badge-danger' }}">
                                    <i class="fas {{ $gatewayStatus['running'] ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                    {{ $gatewayStatus['running'] ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">PM2 Name</td>
                            <td><code>{{ $gatewayStatus['name'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Host:Port</td>
                            <td><code>{{ $gatewayStatus['host'] }}:{{ $gatewayStatus['port'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">PID</td>
                            <td><code>{{ $gatewayStatus['pm2_pid'] ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Baileys</td>
                            <td><code>{{ $currentBaileys }}</code></td>
                        </tr>
                    </table>

                    <form action="{{ route('super-admin.wa-gateway.restart') }}" method="POST"
                          onsubmit="return confirm('Restart WA Gateway? Session akan auto-reconnect dalam ~10 detik.')">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-redo mr-1"></i> Restart Gateway
                        </button>
                    </form>
                </div>
            </div>

            {{-- Panel Upgrade --}}
            <div class="card {{ $hasUpdate ? 'border-warning' : '' }}">
                <div class="card-header {{ $hasUpdate ? 'bg-warning' : '' }}">
                    <h5 class="mb-0">
                        <i class="fas fa-arrow-circle-up mr-1"></i> Upgrade Baileys
                        @if($hasUpdate)
                            <span class="badge badge-danger ml-1">Update Tersedia!</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($hasUpdate)
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Update tersedia: <strong>{{ $currentBaileys }}</strong> → <strong>{{ $latestVersion }}</strong>
                        </div>
                    @elseif($latestVersion)
                        <div class="alert alert-success py-2">
                            <i class="fas fa-check-circle mr-1"></i>
                            Baileys sudah versi terbaru (<strong>{{ $currentBaileys }}</strong>)
                        </div>
                    @endif

                    <form action="{{ route('super-admin.wa-gateway.upgrade') }}" method="POST"
                          onsubmit="return confirmUpgrade(this)">
                        @csrf
                        <div class="form-group">
                            <label>Versi Target</label>
                            <input type="text" name="version"
                                   class="form-control @error('version') is-invalid @enderror"
                                   value="{{ old('version', $latestVersion ?? $currentBaileys) }}"
                                   placeholder="contoh: 7.0.0-rc.9">
                            @error('version')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">
                                Versi saat ini: <strong>{{ $currentBaileys }}</strong>
                                @if($latestVersion && $latestVersion !== $currentBaileys)
                                    | Terbaru: <strong>{{ $latestVersion }}</strong>
                                @endif
                            </small>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle mr-1"></i>
                            Gateway akan di-restart otomatis setelah upgrade. Session WhatsApp akan reconnect tanpa scan ulang (~10 detik downtime).
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-arrow-circle-up mr-1"></i> Upgrade Baileys
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Panel Riwayat Upgrade --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history mr-1"></i> Riwayat Upgrade</h5>
                </div>
                <div class="card-body p-0">
                    @if(count($upgradeHistory) > 0)
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Versi</th>
                                    <th>Waktu</th>
                                    <th>Oleh</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($upgradeHistory as $h)
                                <tr>
                                    <td><code>{{ $h['version'] }}</code></td>
                                    <td class="small text-muted">{{ $h['upgraded_at'] }}</td>
                                    <td class="small">{{ $h['upgraded_by'] }}</td>
                                    <td class="text-center">
                                        @if($h['success'])
                                            <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Sukses</span>
                                        @else
                                            <span class="badge badge-danger"><i class="fas fa-times mr-1"></i>Gagal</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-history fa-2x mb-2 d-block"></i>
                            Belum ada riwayat upgrade
                        </div>
                    @endif
                </div>
            </div>

            {{-- Info versi npm --}}
            @if($updateInfo)
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fab fa-npm mr-1 text-danger"></i> Info npm Registry</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" width="45%">Versi terpasang</td>
                            <td><code>{{ $updateInfo['current_version'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Versi terbaru (7.x)</td>
                            <td><code>{{ $updateInfo['latest_version'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Latest stable</td>
                            <td><code>{{ $updateInfo['latest_stable'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ada update?</td>
                            <td>
                                @if($updateInfo['has_update'])
                                    <span class="badge badge-warning">Ya</span>
                                @else
                                    <span class="badge badge-success">Tidak</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Terakhir dicek</td>
                            <td class="small">{{ \Carbon\Carbon::parse($updateInfo['checked_at'])->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Pending Device Requests Alert --}}
    @if($pendingDeviceRequests > 0)
    <div class="alert alert-warning mt-2 d-flex align-items-center justify-content-between">
        <div>
            <i class="fas fa-bell mr-2"></i>
            Ada <strong>{{ $pendingDeviceRequests }}</strong> permintaan penggunaan device WA platform yang menunggu persetujuan.
        </div>
        <a href="{{ route('super-admin.wa-platform-device-requests.index') }}" class="btn btn-sm btn-warning ml-3">
            <i class="fas fa-eye mr-1"></i>Lihat Permintaan
        </a>
    </div>
    @endif

    {{-- Platform Device Management --}}
    <div class="card mt-2">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-shield-alt mr-1 text-primary"></i> Platform Notification Device
            </h5>
            <small class="text-muted d-block mt-1">
                Pilih device WA yang diizinkan mengirim notifikasi platform (registrasi tenant, subscription, dll).
                Hanya device yang ditandai <span class="badge badge-primary badge-sm">Platform</span> yang akan digunakan — tidak akan menggunakan session tenant secara acak.
            </small>
        </div>
        <div class="card-body p-0">
            @if($allDevices->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>
                    Belum ada device terdaftar
                </div>
            @else
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Device / Session</th>
                            <th>Nomor WA</th>
                            <th>Tenant</th>
                            <th>Status</th>
                            <th class="text-center">Platform</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allDevices as $device)
                        <tr class="{{ $device->is_platform_device ? 'table-primary' : '' }}">
                            <td>
                                <strong>{{ $device->device_name ?: $device->session_id }}</strong>
                                @if($device->is_default)
                                    <span class="badge badge-secondary badge-sm ml-1">Default</span>
                                @endif
                                <div class="small text-muted"><code>{{ $device->session_id }}</code></div>
                            </td>
                            <td class="small">{{ $device->wa_number ?: '-' }}</td>
                            <td class="small">
                                @if($device->user)
                                    {{ $device->user->name }}
                                    <div class="text-muted">{{ $device->user->email }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php $liveStatus = $device->live_status ?? 'unknown'; @endphp
                                @if(in_array($liveStatus, ['connected', 'idle']))
                                    <span class="badge badge-success">{{ ucfirst($liveStatus) }}</span>
                                @elseif($liveStatus === 'connecting')
                                    <span class="badge badge-warning">Connecting</span>
                                @elseif($liveStatus === 'disconnected')
                                    <span class="badge badge-danger">Disconnected</span>
                                @elseif($liveStatus === 'notStarted')
                                    <span class="badge badge-secondary">Not Started</span>
                                @else
                                    <span class="badge badge-light text-muted">{{ $liveStatus }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-sm {{ $device->is_platform_device ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    data-ajax-post="{{ route('super-admin.wa-gateway.devices.toggle-platform', $device) }}"
                                    data-loading-text='<i class="fas fa-spinner fa-spin"></i>'
                                    title="{{ $device->is_platform_device ? 'Hapus dari Platform Device' : 'Tandai sebagai Platform Device' }}">
                                    <i class="fas fa-shield-alt"></i>
                                    {{ $device->is_platform_device ? 'Platform' : 'Tandai' }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>
@endsection

@push('js')
<script>
function confirmUpgrade(form) {
    const version = form.querySelector('[name="version"]').value.trim();
    if (!version) return false;
    return confirm(`Yakin upgrade Baileys ke versi ${version}?\n\nGateway akan di-restart otomatis. Session WA akan reconnect dalam ~10 detik.`);
}
</script>
@endpush
