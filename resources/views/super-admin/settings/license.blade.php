@extends('layouts.admin')

@section('title', 'Lisensi Sistem')

@section('content')
@php
    $license = $snapshot['license'];
    $statusClass = match ($license->status) {
        'active' => 'success',
        'grace' => 'warning',
        'restricted', 'invalid', 'missing' => 'danger',
        default => 'secondary',
    };
@endphp
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-certificate mr-2 text-primary"></i>Lisensi Sistem</h4>
            <small class="text-muted">Kelola lisensi instance untuk deployment self-hosted Rafen.</small>
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
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle mr-1"></i> Status Lisensi</div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge badge-{{ $statusClass }} px-3 py-2">{{ $snapshot['status_label'] }}</span>
                        @if($snapshot['is_enforced'])
                            <span class="badge badge-primary px-3 py-2">Enforcement Aktif</span>
                        @else
                            <span class="badge badge-secondary px-3 py-2">Enforcement Nonaktif</span>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="w-25">License ID</th>
                                    <td>{{ $license->license_id ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Customer</th>
                                    <td>{{ $license->customer_name ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Instance</th>
                                    <td>{{ $license->instance_name ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Berlaku Sampai</th>
                                    <td>{{ $license->expires_at?->format('Y-m-d') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Support Sampai</th>
                                    <td>{{ $license->support_until?->format('Y-m-d') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Grace Period</th>
                                    <td>{{ $license->grace_days }} hari</td>
                                </tr>
                                <tr>
                                    <th>Verifikasi Terakhir</th>
                                    <td>{{ $license->last_verified_at?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Lokasi File</th>
                                    <td><code>{{ $snapshot['license_path'] }}</code></td>
                                </tr>
                                <tr>
                                    <th>Public Key</th>
                                    <td>
                                        @if($snapshot['has_public_key'])
                                            <span class="badge badge-success">Tersimpan</span>
                                        @else
                                            <span class="badge badge-danger">Belum Diatur</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if($license->validation_error)
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Masalah lisensi:</strong> {{ $license->validation_error }}
                    </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-fingerprint mr-1"></i> Fingerprint Server</div>
                <div class="card-body">
                    <p class="text-muted mb-2">Kirim fingerprint ini ke vendor untuk penerbitan lisensi.</p>
                    <textarea class="form-control" rows="3" readonly>{{ $snapshot['expected_fingerprint'] }}</textarea>
                    <a href="{{ route('super-admin.settings.license.activation-request') }}" class="btn btn-outline-primary mt-3">
                        <i class="fas fa-download mr-1"></i> Unduh Activation Request
                    </a>
                    <button type="button" class="btn btn-outline-info mt-3 ml-2" data-toggle="modal" data-target="#upgradeRequestModal">
                        <i class="fas fa-arrow-up mr-1"></i> Request Upgrade Lisensi
                    </button>
                </div>
            </div>

            {{-- Danger Zone: Unregister Lisensi --}}
            @if($license->status !== 'missing' && $license->status !== 'disabled')
            <div class="card border-danger mt-3">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Danger Zone
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Unregister akan menghapus lisensi dari instance ini secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
                    <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#unregisterModal">
                        <i class="fas fa-trash-alt mr-1"></i> Unregister Lisensi
                    </button>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-upload mr-1"></i> Upload Lisensi</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.settings.license.update') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label>File Lisensi</label>
                            <div class="custom-file">
                                <input type="file" name="license_file" class="custom-file-input @error('license_file') is-invalid @enderror" id="licenseFileInput" required>
                                <label class="custom-file-label" for="licenseFileInput">Pilih file lisensi</label>
                                @error('license_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <small class="form-text text-muted">Format yang diterima: <code>.json</code>, <code>.txt</code>, atau <code>.lic</code>.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Unggah dan Verifikasi
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-layer-group mr-1"></i> Modul Lisensi</div>
                <div class="card-body">
                    @if(! empty($license->modules))
                        <div class="d-flex flex-wrap" style="gap:.5rem;">
                            @foreach($license->modules as $module)
                                <span class="badge badge-light border px-3 py-2">{{ $module }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">Belum ada data modul aktif.</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-sliders-h mr-1"></i> Limit Lisensi</div>
                <div class="card-body">
                    @if(! empty($license->limits))
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($license->limits as $limitKey => $limitValue)
                                    <tr>
                                        <th>{{ $limitKey }}</th>
                                        <td>{{ $limitValue }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted mb-0">Belum ada data limit lisensi.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- Modal: Konfirmasi Unregister --}}
@if($license->status !== 'missing' && $license->status !== 'disabled')
<div class="modal fade" id="unregisterModal" tabindex="-1" role="dialog" aria-labelledby="unregisterModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="unregisterModalLabel"><i class="fas fa-exclamation-triangle mr-1"></i> Konfirmasi Unregister Lisensi</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('super-admin.settings.license.unregister') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p>Anda akan menghapus lisensi berikut dari instance ini:</p>
                    <ul class="mb-3">
                        <li><strong>License ID:</strong> {{ $license->license_id ?: '-' }}</li>
                        <li><strong>Customer:</strong> {{ $license->customer_name ?: '-' }}</li>
                    </ul>
                    <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan.</strong> Instance akan kembali ke status lisensi <em>missing</em>.</p>
                    <div class="form-group mb-0">
                        <label for="unregisterReason">Alasan Unregister <small class="text-muted">(opsional)</small></label>
                        <textarea name="reason" id="unregisterReason" class="form-control" rows="2" placeholder="Contoh: pindah server, berganti kontrak..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt mr-1"></i> Ya, Unregister
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal: Request Upgrade Lisensi --}}
<div class="modal fade" id="upgradeRequestModal" tabindex="-1" role="dialog" aria-labelledby="upgradeRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="upgradeRequestModalLabel"><i class="fas fa-arrow-up mr-1"></i> Request Upgrade Lisensi</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('super-admin.settings.license.upgrade-request') }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Pilih modul dan limit yang diinginkan. File JSON yang di-generate dikirim ke vendor untuk penerbitan lisensi baru.</p>

                    <div class="form-group">
                        <label><strong>Modul yang Diminta</strong></label>
                        <div class="row">
                            @php
                                $allModules = ['core', 'mikrotik', 'radius', 'vpn', 'wa', 'olt', 'genieacs'];
                                $activeModules = $license->modules ?? [];
                            @endphp
                            @foreach($allModules as $mod)
                            <div class="col-6 col-md-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="mod_{{ $mod }}"
                                        name="modules[]" value="{{ $mod }}"
                                        {{ in_array($mod, $activeModules) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="mod_{{ $mod }}">{{ $mod }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label><strong>Limit yang Diminta</strong></label>
                        <small class="form-text text-muted mb-2">Isi <code>-1</code> untuk unlimited. Biarkan <code>0</code> jika tidak ingin mengubah.</small>
                        <div class="row">
                            @php
                                $currentLimits = $license->limits ?? [];
                            @endphp
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="maxMikrotik" class="small">Max Mikrotik</label>
                                    <input type="number" class="form-control form-control-sm" id="maxMikrotik"
                                        name="max_mikrotik" value="{{ $currentLimits['max_mikrotik'] ?? 0 }}" min="-1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="maxPppUsers" class="small">Max PPP Users</label>
                                    <input type="number" class="form-control form-control-sm" id="maxPppUsers"
                                        name="max_ppp_users" value="{{ $currentLimits['max_ppp_users'] ?? 0 }}" min="-1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="maxVpnPeers" class="small">Max VPN Peers</label>
                                    <input type="number" class="form-control form-control-sm" id="maxVpnPeers"
                                        name="max_vpn_peers" value="{{ $currentLimits['max_vpn_peers'] ?? 0 }}" min="-1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="upgradeNotes">Catatan ke Vendor <small class="text-muted">(opsional)</small></label>
                        <textarea name="notes" id="upgradeNotes" class="form-control" rows="2"
                            placeholder="Contoh: Butuh tambah modul radius dan VPN untuk layanan enterprise baru..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-download mr-1"></i> Download Upgrade Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('licenseFileInput')?.addEventListener('change', function () {
    const fileName = this.files?.[0]?.name || 'Pilih file lisensi';
    const label = this.nextElementSibling;
    if (label) {
        label.textContent = fileName;
    }
});
</script>
@endpush
