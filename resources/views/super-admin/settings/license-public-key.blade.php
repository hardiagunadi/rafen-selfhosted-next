@extends('layouts.admin')

@section('title', 'Public Key Lisensi')

@section('content')
@php
    $formatAccessMode = static function (?string $value): string {
        return match ($value) {
            'fingerprint_only' => 'Fingerprint Only',
            'ip_based' => 'IP-Based',
            'domain_based' => 'Domain-Based',
            'hybrid' => 'Hybrid',
            null, '' => 'Belum Dicatat',
            default => ucwords(str_replace('_', ' ', $value)),
        };
    };
@endphp
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-key mr-2 text-primary"></i>Public Key Lisensi</h4>
            <small class="text-muted">Kelola public key vendor yang dibagikan ke deployment self-hosted untuk verifikasi lisensi.</small>
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
                <div class="card-header"><i class="fas fa-info-circle mr-1"></i> Status Public Key</div>
                <div class="card-body">
                    <div class="mb-3">
                        @if($snapshot['has_public_key'])
                            <span class="badge badge-success px-3 py-2">Tersimpan</span>
                        @else
                            <span class="badge badge-danger px-3 py-2">Belum Diatur</span>
                        @endif
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Mode Deploy</th>
                                <td>SaaS</td>
                            </tr>
                            <tr>
                                <th>Penggunaan</th>
                                <td>Verifikasi signature file lisensi pada instance self-hosted.</td>
                            </tr>
                            <tr>
                                <th>Nilai Saat Ini</th>
                                <td>
                                    @if($snapshot['has_public_key'])
                                        <code>Tersimpan di environment aplikasi</code>
                                    @else
                                        <span class="text-muted">Belum diatur</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-certificate mr-1"></i> Issuer Lisensi Self-Hosted</div>
                <div class="card-body">
                    <div class="mb-3">
                        @if($issuer['is_ready'])
                            <span class="badge badge-success px-3 py-2">Issuer Siap</span>
                        @else
                            <span class="badge badge-danger px-3 py-2">Issuer Belum Siap</span>
                        @endif
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="w-25">Private Key</th>
                                <td>
                                    @if($issuer['has_private_key'])
                                        <code>{{ $issuer['private_key_path'] }}</code>
                                    @else
                                        <span class="text-muted">Belum tersedia</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Public Key</th>
                                <td>
                                    @if($snapshot['has_public_key'])
                                        <code>Siap dipakai verifikasi</code>
                                    @else
                                        <span class="text-muted">Belum diatur</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if($issuer['error'])
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Masalah issuer:</strong> {{ $issuer['error'] }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="fas fa-save mr-1"></i> Update Public Key</div>
                <div class="card-body">
                    @if($snapshot['is_editable'])
                        <form method="POST" action="{{ route('super-admin.settings.license-public-key.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label for="licensePublicKeyInput">Public Key Verifikasi</label>
                                <textarea
                                    name="license_public_key"
                                    id="licensePublicKeyInput"
                                    rows="5"
                                    class="form-control @error('license_public_key') is-invalid @enderror"
                                    placeholder="Tempel public key base64 dari vendor"
                                    style="font-family:monospace;font-size:12px;"
                                    required
                                >{{ old('license_public_key', $snapshot['public_key']) }}</textarea>
                                @error('license_public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text text-muted">Nilai ini akan disimpan ke <code>LICENSE_PUBLIC_KEY</code> pada environment aplikasi SaaS.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Simpan Public Key
                            </button>
                        </form>
                    @else
                        <div class="alert alert-info mb-3">
                            Public key lisensi dikelola melalui environment aplikasi.
                        </div>

                        <div class="form-group mb-0">
                            <label for="licensePublicKeyReadOnly">Public Key Verifikasi</label>
                            <textarea
                                id="licensePublicKeyReadOnly"
                                rows="5"
                                class="form-control"
                                style="font-family:monospace;font-size:12px;"
                                readonly
                            >{{ $snapshot['public_key'] }}</textarea>
                            <small class="form-text text-muted">
                                Atur <code>LICENSE_PUBLIC_KEY</code> dan biarkan <code>LICENSE_PUBLIC_KEY_EDITABLE=false</code> untuk mode production.
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-file-signature mr-1"></i> Issue Lisensi Self-Hosted</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.settings.license-public-key.issue') }}">
                        @csrf
                        <div class="form-group">
                            <label for="licensePresetInput">Preset Lisensi</label>
                            <select name="license_preset" id="licensePresetInput" class="form-control @error('license_preset') is-invalid @enderror">
                                <option value="">Manual / Custom</option>
                                @foreach($issuer['presets'] as $presetKey => $preset)
                                    <option value="{{ $presetKey }}" {{ old('license_preset') === $presetKey ? 'selected' : '' }}>
                                        {{ $preset['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('license_preset')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="form-text text-muted">Pilih preset untuk mengisi modul, limit, dan grace days lebih cepat. Nilai setelahnya tetap bisa disesuaikan manual.</small>
                        </div>

                        <div class="mb-3">
                            @foreach($issuer['presets'] as $presetKey => $preset)
                                <div class="border rounded px-3 py-2 mb-2" data-license-preset-card="{{ $presetKey }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>{{ $preset['label'] }}</strong>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-license-preset-trigger="{{ $presetKey }}">Pakai Preset</button>
                                    </div>
                                    <small class="text-muted d-block mt-1">{{ $preset['description'] }}</small>
                                    <small class="text-muted d-block mt-1">
                                        Modul: {{ implode(', ', $preset['modules']) }} | Limit: max_mikrotik={{ $preset['limits']['max_mikrotik'] }}, max_ppp_users={{ $preset['limits']['max_ppp_users'] }}
                                    </small>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-group">
                            <label for="accessModeInput">Mode Akses Instance</label>
                            <select name="access_mode" id="accessModeInput" class="form-control @error('access_mode') is-invalid @enderror">
                                <option value="fingerprint_only" {{ old('access_mode', 'fingerprint_only') === 'fingerprint_only' ? 'selected' : '' }}>Fingerprint Only</option>
                                <option value="ip_based" {{ old('access_mode') === 'ip_based' ? 'selected' : '' }}>IP-Based</option>
                                <option value="domain_based" {{ old('access_mode') === 'domain_based' ? 'selected' : '' }}>Domain-Based</option>
                                <option value="hybrid" {{ old('access_mode') === 'hybrid' ? 'selected' : '' }}>Hybrid</option>
                            </select>
                            @error('access_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="form-text text-muted">Fingerprint Only dan IP-Based paling cocok untuk instalasi self-hosted baru saat domain final belum siap.</small>
                        </div>

                        <div class="form-group">
                            <label for="customerNameInput">Nama Customer</label>
                            <input type="text" name="customer_name" id="customerNameInput" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" placeholder="PT Contoh ISP" required>
                            @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="instanceNameInput">Nama Instance</label>
                            <input type="text" name="instance_name" id="instanceNameInput" class="form-control @error('instance_name') is-invalid @enderror" value="{{ old('instance_name', 'production') }}" placeholder="production" required>
                            @error('instance_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="fingerprintInput">Fingerprint Server Target</label>
                            <textarea name="fingerprint" id="fingerprintInput" rows="3" class="form-control @error('fingerprint') is-invalid @enderror" placeholder="Tempel fingerprint dari activation request self-hosted" style="font-family:monospace;font-size:12px;" required>{{ old('fingerprint') }}</textarea>
                            @error('fingerprint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="expiresAtInput">Berlaku Sampai</label>
                                <input type="date" name="expires_at" id="expiresAtInput" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at') }}" required>
                                @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group col-md-6">
                                <label for="supportUntilInput">Support Sampai</label>
                                <input type="date" name="support_until" id="supportUntilInput" class="form-control @error('support_until') is-invalid @enderror" value="{{ old('support_until') }}">
                                @error('support_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="graceDaysInput">Grace Days</label>
                            <input type="number" name="grace_days" id="graceDaysInput" min="0" class="form-control @error('grace_days') is-invalid @enderror" value="{{ old('grace_days', 21) }}">
                            @error('grace_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label>Modul Aktif</label>
                            <div class="d-flex flex-wrap" style="gap:.5rem;">
                                @foreach(['core', 'mikrotik', 'radius', 'vpn', 'wa', 'olt', 'genieacs'] as $module)
                                    <label class="mb-0 border rounded px-2 py-1">
                                        <input type="checkbox" name="modules[]" value="{{ $module }}" data-license-module="{{ $module }}" {{ in_array($module, old('modules', ['core']), true) ? 'checked' : '' }}>
                                        <span class="ml-1">{{ strtoupper($module) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('modules')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error('modules.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="allowedHostsInput">Host/IP Opsional</label>
                            <textarea name="allowed_hosts_text" id="allowedHostsInput" rows="3" class="form-control @error('allowed_hosts_text') is-invalid @enderror" placeholder="Satu host per baris. Bisa domain, IP publik, atau IP lokal. Kosongkan jika domain akan diisi belakangan.">{{ old('allowed_hosts_text', old('domains_text')) }}</textarea>
                            @error('allowed_hosts_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="form-text text-muted">Field ini menerima domain dan IP. Boleh dikosongkan jika lisensi ingin diikat ke fingerprint server lebih dulu, lalu host final dicatat belakangan.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="maxMikrotikInput">Limit MikroTik</label>
                                <input type="number" name="max_mikrotik" id="maxMikrotikInput" min="1" class="form-control @error('max_mikrotik') is-invalid @enderror" value="{{ old('max_mikrotik') }}">
                                @error('max_mikrotik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group col-md-6">
                                <label for="maxPppUsersInput">Limit PPP Users</label>
                                <input type="number" name="max_ppp_users" id="maxPppUsersInput" min="1" class="form-control @error('max_ppp_users') is-invalid @enderror" value="{{ old('max_ppp_users') }}">
                                @error('max_ppp_users')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="additionalLimitsInput">Limit Tambahan (JSON)</label>
                            <textarea name="additional_limits" id="additionalLimitsInput" rows="3" class="form-control @error('additional_limits') is-invalid @enderror" placeholder='{"max_radius_clients": 20}' style="font-family:monospace;font-size:12px;">{{ old('additional_limits') }}</textarea>
                            @error('additional_limits')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex flex-wrap" style="gap:.75rem;">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                {{ $issuer['is_ready'] ? '' : 'disabled' }}
                                title="{{ $issuer['is_ready'] ? 'Issuer siap, lisensi bisa dibuat dan diunduh.' : 'Issuer belum siap. Periksa status issuer lisensi di panel sebelah kiri.' }}"
                            >
                                <i class="fas fa-download mr-1"></i> Buat & Unduh Lisensi
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="copyLicenseIssueSummaryBtn"
                            >
                                <i class="fas fa-copy mr-1"></i> Copy License Issue Summary
                            </button>
                        </div>
                        <small class="form-text text-muted mt-2">Ringkasan ini cocok untuk tim vendor atau operasional internal sebelum file lisensi diunduh.</small>
                        @if($issuer['is_ready'])
                        <small class="form-text text-success mt-2">
                            Tombol aktif karena issuer lisensi di server SaaS sudah siap.
                        </small>
                        @else
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>Tombol masih nonaktif.</strong> Mengisi form issue lisensi saja belum cukup.
                            Issuer harus siap lebih dulu, yaitu private key issuer tersedia dan cocok dengan <code>LICENSE_PUBLIC_KEY</code>.
                            @if($issuer['error'])
                            <div class="mt-2 mb-0"><strong>Detail:</strong> {{ $issuer['error'] }}</div>
                            @endif
                        </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const licensePresets = @json($issuer['presets']);
const licensePresetInput = document.getElementById('licensePresetInput');
const graceDaysInput = document.getElementById('graceDaysInput');
const maxMikrotikInput = document.getElementById('maxMikrotikInput');
const maxPppUsersInput = document.getElementById('maxPppUsersInput');

function applyLicensePreset(presetKey) {
    const preset = licensePresets[presetKey];

    if (!preset) {
        return;
    }

    if (licensePresetInput) {
        licensePresetInput.value = presetKey;
    }

    if (graceDaysInput && preset.grace_days !== undefined) {
        graceDaysInput.value = preset.grace_days;
    }

    if (maxMikrotikInput && preset.limits && preset.limits.max_mikrotik !== undefined) {
        maxMikrotikInput.value = preset.limits.max_mikrotik;
    }

    if (maxPppUsersInput && preset.limits && preset.limits.max_ppp_users !== undefined) {
        maxPppUsersInput.value = preset.limits.max_ppp_users;
    }

    document.querySelectorAll('[data-license-module]').forEach((checkbox) => {
        checkbox.checked = Array.isArray(preset.modules) && preset.modules.includes(checkbox.value);
    });
}

document.querySelectorAll('[data-license-preset-trigger]').forEach((button) => {
    button.addEventListener('click', function () {
        applyLicensePreset(this.dataset.licensePresetTrigger);
    });
});

licensePresetInput?.addEventListener('change', function () {
    if (this.value !== '') {
        applyLicensePreset(this.value);
    }
});

document.getElementById('copyLicenseIssueSummaryBtn')?.addEventListener('click', async function () {
    const originalHtml = this.innerHTML;
    const customerName = document.getElementById('customerNameInput')?.value?.trim() || '-';
    const instanceName = document.getElementById('instanceNameInput')?.value?.trim() || '-';
    const fingerprint = document.getElementById('fingerprintInput')?.value?.trim() || '-';
    const expiresAt = document.getElementById('expiresAtInput')?.value?.trim() || '-';
    const supportUntil = document.getElementById('supportUntilInput')?.value?.trim() || '-';
    const graceDays = document.getElementById('graceDaysInput')?.value?.trim() || '-';
    const accessMode = document.getElementById('accessModeInput')?.value?.trim() || '';
    const accessModeLabel = ({
        fingerprint_only: 'Fingerprint Only',
        ip_based: 'IP-Based',
        domain_based: 'Domain-Based',
        hybrid: 'Hybrid',
    })[accessMode] || accessMode || '-';
    const allowedHosts = document.getElementById('allowedHostsInput')?.value
        ?.split(/\r\n|\r|\n/)
        .map((item) => item.trim())
        .filter(Boolean) || [];
    const modules = Array.from(document.querySelectorAll('[data-license-module]:checked')).map((item) => item.value.toUpperCase());

    const summary = [
        'RAFEN Self-Hosted License Issue Summary',
        'Customer: ' + customerName,
        'Instance: ' + instanceName,
        'Access Mode: ' + accessModeLabel,
        'Allowed Hosts: ' + (allowedHosts.length ? allowedHosts.join(', ') : '-'),
        'Expires At: ' + expiresAt,
        'Support Until: ' + supportUntil,
        'Grace Days: ' + graceDays,
        'Modules: ' + (modules.length ? modules.join(', ') : '-'),
        'Fingerprint: ' + fingerprint,
    ].join('\n');

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(summary);
        } else {
            const tempInput = document.createElement('textarea');
            tempInput.value = summary;
            tempInput.setAttribute('readonly', 'readonly');
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-9999px';
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }

        this.innerHTML = '<i class="fas fa-check mr-1"></i> Tersalin';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-success');
    } catch (error) {
        this.innerHTML = '<i class="fas fa-times mr-1"></i> Gagal Copy';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-danger');
    }

    setTimeout(() => {
        this.innerHTML = originalHtml;
        this.classList.remove('btn-success', 'btn-danger');
        this.classList.add('btn-outline-secondary');
    }, 2000);
});
</script>
@endpush
