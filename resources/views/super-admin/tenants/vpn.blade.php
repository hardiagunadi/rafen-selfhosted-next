@extends('layouts.admin')

@section('title', 'VPN Settings — ' . $tenant->name)

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-network-wired mr-2 text-info"></i>Pengaturan VPN Tenant</h4>
            <small class="text-muted">{{ $tenant->name }} ({{ $tenant->email }})</small>
        </div>
        <div class="col-auto">
            <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Detail Tenant
            </a>
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

    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        VPN digunakan untuk menghubungkan router MikroTik tenant ke server Rafen melalui WireGuard.
        Kredensial yang dibuat di sini disimpan ke database dan digunakan sebagai referensi konfigurasi.
    </div>

    <div class="row">
        {{-- Status Card --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-signal mr-1"></i> Status VPN</h5>
                </div>
                <div class="card-body text-center py-4">
                    @if($tenant->vpn_enabled)
                        <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                        <h5 class="text-success">VPN Aktif</h5>
                    @else
                        <i class="fas fa-times-circle fa-3x text-secondary mb-3 d-block"></i>
                        <h5 class="text-secondary">VPN Tidak Aktif</h5>
                    @endif

                    @if($tenant->vpn_ip)
                        <div class="mt-3">
                            <small class="text-muted d-block">IP VPN</small>
                            <code class="h6">{{ $tenant->vpn_ip }}</code>
                        </div>
                    @endif
                </div>
                @if($tenant->vpn_username)
                <div class="card-footer p-0">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Username</td>
                            <td><code>{{ $tenant->vpn_username }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Password</td>
                            <td>
                                <code id="vpnPasswordDisplay" class="d-none">{{ $tenant->vpn_password }}</code>
                                <span id="vpnPasswordMask">••••••••</span>
                                <button type="button" class="btn btn-link btn-sm p-0 ml-1"
                                    onclick="$('#vpnPasswordDisplay').toggleClass('d-none'); $('#vpnPasswordMask').toggleClass('d-none');">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                @endif
            </div>

            {{-- Generate Credentials --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key mr-1"></i> Generate Kredensial</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Generate username dan password VPN secara otomatis untuk tenant ini.
                        Kredensial yang ada akan ditimpa.
                    </p>
                    <form method="POST" action="{{ route('super-admin.tenants.vpn.generate', $tenant) }}"
                        onsubmit="return confirm('Generate kredensial baru? Kredensial lama akan ditimpa.')">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-sync-alt mr-1"></i> Generate Kredensial Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Settings Form --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog mr-1"></i> Konfigurasi VPN</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.tenants.vpn.update', $tenant) }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label>Status VPN</label>
                            <div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="vpn_enabled_yes" name="vpn_enabled" value="1"
                                        class="custom-control-input"
                                        {{ $tenant->vpn_enabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="vpn_enabled_yes">Aktif</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="vpn_enabled_no" name="vpn_enabled" value="0"
                                        class="custom-control-input"
                                        {{ !$tenant->vpn_enabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="vpn_enabled_no">Nonaktif</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Username VPN</label>
                                    <input type="text" name="vpn_username"
                                        class="form-control @error('vpn_username') is-invalid @enderror"
                                        value="{{ old('vpn_username', $tenant->vpn_username) }}"
                                        placeholder="tenant_{{ $tenant->id }}">
                                    @error('vpn_username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password VPN</label>
                                    <input type="text" name="vpn_password"
                                        class="form-control @error('vpn_password') is-invalid @enderror"
                                        value="{{ old('vpn_password', $tenant->vpn_password) }}"
                                        placeholder="Masukkan password VPN">
                                    @error('vpn_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>IP VPN</label>
                            <input type="text" name="vpn_ip"
                                class="form-control @error('vpn_ip') is-invalid @enderror"
                                value="{{ old('vpn_ip', $tenant->vpn_ip) }}"
                                placeholder="10.0.0.x">
                            <small class="form-text text-muted">IP address tenant di jaringan WireGuard VPN.</small>
                            @error('vpn_ip')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Simpan Pengaturan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
