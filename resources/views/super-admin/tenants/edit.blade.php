@extends('layouts.admin')

@section('title', 'Edit Tenant: ' . $tenant->name)

@section('content')
<style>
.mf-page { display:flex;flex-direction:column;gap:1.1rem; }
.mf-page-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
.mf-page-header-left { display:flex;align-items:center;gap:.85rem; }
.mf-page-icon { width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(0,0,0,.15); }
.mf-page-title { font-size:1.15rem;font-weight:700;color:var(--app-text,#0f172a);line-height:1.2; }
.mf-dim { color:var(--app-text-soft,#5b6b83);font-weight:500; }
.mf-page-sub { font-size:.8rem;color:var(--app-text-soft,#5b6b83);margin-top:.15rem; }
.mf-header-actions { display:flex;gap:.5rem;align-items:center;flex-wrap:wrap; }
.mf-btn-back { display:inline-flex;align-items:center;padding:.4rem .95rem;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);background:#fff;color:var(--app-text,#0f172a);font-size:.82rem;font-weight:600;text-decoration:none;transition:background 140ms,transform 140ms; }
.mf-btn-back:hover { background:#f1f5ff;transform:translateY(-1px);color:var(--app-text,#0f172a);text-decoration:none; }
.mf-alert { display:flex;align-items:flex-start;gap:.5rem;padding:.85rem 1rem;border-radius:12px;font-size:.84rem; }
.mf-alert-danger { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
.mf-row-3 { grid-template-columns:repeat(3,1fr); }
@media (max-width:767px) { .mf-row,.mf-row-3 { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }
.mf-label { font-size:.77rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);display:flex;align-items:center;gap:.4rem; }
.mf-req { color:#ef4444;font-size:.85em; }
.mf-opt { font-size:.7rem;font-weight:600;border-radius:20px;padding:.1rem .45rem;background:rgba(100,116,139,.1);color:#64748b;text-transform:none;letter-spacing:0; }
.mf-hint { font-size:.73rem;color:var(--app-text-soft,#5b6b83); }
.mf-input { height:38px;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);padding:0 .75rem;font-size:.85rem;color:var(--app-text,#0f172a);background:#fff;outline:none;width:100%;transition:border-color 150ms,box-shadow 150ms; }
select.mf-input { appearance:auto; }
textarea.mf-input { height:auto;padding:.5rem .75rem;resize:vertical; }
.mf-input:focus { border-color:#8fb5df;box-shadow:0 0 0 3px rgba(19,103,164,.12); }
.mf-input-error { border-color:#f43f5e !important; }
.mf-feedback { font-size:.76rem;color:#dc2626;margin-top:.1rem; }
.mf-switch { display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none; }
.mf-switch input { display:none; }
.mf-switch-track { width:42px;height:24px;border-radius:99px;flex-shrink:0;background:#d1d5db;transition:background 200ms;position:relative; }
.mf-switch-track::after { content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform 200ms; }
.mf-switch input:checked ~ .mf-switch-track { background:linear-gradient(140deg,#0369a1,#0ea5e9); }
.mf-switch input:checked ~ .mf-switch-track::after { transform:translateX(18px); }
.mf-switch-label { font-size:.84rem;font-weight:600;color:var(--app-text,#0f172a); }
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
</style>

<form action="{{ route('super-admin.tenants.update', $tenant) }}" method="POST">
@csrf
@method('PUT')
<div class="mf-page">

    {{-- Page Header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Data Tenant</div>
                <div class="mf-page-sub"><span class="mf-dim">{{ $tenant->name }}</span></div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="mf-btn-back">
                <i class="fas fa-arrow-left" style="margin-right:.4rem;font-size:.75rem;"></i> Kembali
            </a>
        </div>
    </div>

    @if($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle" style="margin-top:.1rem;flex-shrink:0;"></i>
        <div>
            <strong>Terdapat kesalahan pada form:</strong>
            <ul style="margin:.25rem 0 0 1rem;padding:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="mf-grid">

        {{-- Data Akun --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                    <i class="fas fa-user"></i>
                </div>
                <span class="mf-section-title">Data Akun</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama Lengkap <span class="mf-req">*</span></label>
                        <input type="text" name="name" class="mf-input @error('name') mf-input-error @enderror" value="{{ old('name', $tenant->name) }}" required>
                        @error('name')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Email <span class="mf-req">*</span></label>
                        <input type="email" name="email" class="mf-input @error('email') mf-input-error @enderror" value="{{ old('email', $tenant->email) }}" required>
                        @error('email')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Telepon <span class="mf-opt">opsional</span></label>
                        <input type="text" name="phone" class="mf-input" value="{{ old('phone', $tenant->phone) }}">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Nama Perusahaan <span class="mf-opt">opsional</span></label>
                        <input type="text" name="company_name" class="mf-input" value="{{ old('company_name', $tenant->company_name) }}">
                    </div>
                </div>
                <div class="mf-field">
                    <label class="mf-label">Alamat <span class="mf-opt">opsional</span></label>
                    <textarea name="address" class="mf-input" rows="2">{{ old('address', $tenant->address) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Pengaturan Langganan --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <span class="mf-section-title">Pengaturan Langganan</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Status Langganan</label>
                        <select name="subscription_status" id="subscription_status" class="mf-input">
                            <option value="trial" {{ old('subscription_status', $tenant->subscription_status) === 'trial' ? 'selected' : '' }}>Trial</option>
                            <option value="active" {{ old('subscription_status', $tenant->subscription_status) === 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="expired" {{ old('subscription_status', $tenant->subscription_status) === 'expired' ? 'selected' : '' }}>Berakhir</option>
                            <option value="suspended" {{ old('subscription_status', $tenant->subscription_status) === 'suspended' ? 'selected' : '' }}>Suspend</option>
                        </select>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Metode Langganan</label>
                        <select name="subscription_method" id="subscription_method" class="mf-input @error('subscription_method') mf-input-error @enderror">
                            <option value="monthly" {{ old('subscription_method', $tenant->subscription_method ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                            <option value="license" {{ old('subscription_method', $tenant->subscription_method) === 'license' ? 'selected' : '' }}>Lisensi (Tahunan)</option>
                        </select>
                        @error('subscription_method')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div id="license_limit_fields" class="mf-row d-none">
                    <div class="mf-field">
                        <label class="mf-label">Limit Mikrotik (Lisensi)</label>
                        <input type="number" name="license_max_mikrotik" class="mf-input @error('license_max_mikrotik') mf-input-error @enderror" value="{{ old('license_max_mikrotik', $tenant->license_max_mikrotik) }}" min="-1">
                        @error('license_max_mikrotik')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Isi <code>-1</code> untuk tanpa batas.</span>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Limit PPP Users (Lisensi)</label>
                        <input type="number" name="license_max_ppp_users" class="mf-input @error('license_max_ppp_users') mf-input-error @enderror" value="{{ old('license_max_ppp_users', $tenant->license_max_ppp_users) }}" min="-1">
                        @error('license_max_ppp_users')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Isi <code>-1</code> untuk tanpa batas.</span>
                    </div>
                </div>

                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label" id="subscription_plan_label">Paket Langganan</label>
                        <select name="subscription_plan_id" id="subscription_plan_id" class="mf-input">
                            <option value="">- Tidak ada -</option>
                            @foreach($plans as $plan)
                            <option value="{{ $plan->id }}"
                                data-plan-name="{{ $plan->name }}"
                                data-plan-price="{{ number_format($plan->price, 0, ',', '.') }}"
                                data-plan-duration="{{ $plan->duration_days }}"
                                {{ (string) old('subscription_plan_id', $tenant->subscription_plan_id) === (string) $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $plan->duration_days }} hari
                            </option>
                            @endforeach
                        </select>
                        <span class="mf-hint">Daftar paket lisensi mengambil data dari menu Kelola Paket Langganan.</span>
                    </div>
                </div>

                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Tanggal Berakhir <span class="mf-opt">opsional</span></label>
                        <input type="date" name="subscription_expires_at" class="mf-input" value="{{ old('subscription_expires_at', $tenant->subscription_expires_at?->format('Y-m-d')) }}">
                    </div>
                    <div class="mf-field" id="trial_days_remaining_field">
                        <label class="mf-label">Sisa Hari Trial <span class="mf-opt">opsional</span></label>
                        <input type="number" name="trial_days_remaining" class="mf-input" value="{{ old('trial_days_remaining', $tenant->trial_days_remaining) }}" min="0">
                    </div>
                </div>
            </div>
        </div>

        {{-- Pengaturan VPN --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span class="mf-section-title">Pengaturan VPN</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-field">
                    <label class="mf-switch">
                        <input type="checkbox" id="vpn_enabled" name="vpn_enabled" value="1" {{ $tenant->vpn_enabled ? 'checked' : '' }}>
                        <span class="mf-switch-track"></span>
                        <span class="mf-switch-label">VPN Aktif</span>
                    </label>
                </div>
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">VPN Username <span class="mf-opt">opsional</span></label>
                        <input type="text" name="vpn_username" class="mf-input" value="{{ old('vpn_username', $tenant->vpn_username) }}">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">VPN Password <span class="mf-opt">opsional</span></label>
                        <input type="text" name="vpn_password" class="mf-input" value="{{ old('vpn_password', $tenant->vpn_password) }}">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">VPN IP <span class="mf-opt">opsional</span></label>
                        <input type="text" name="vpn_ip" class="mf-input" value="{{ old('vpn_ip', $tenant->vpn_ip) }}">
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Footer --}}
    <div class="mf-footer">
        <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit">
            <i class="fas fa-save" style="margin-right:.45rem;"></i> Simpan Perubahan
        </button>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script>
function toggleLicenseLimitFields() {
    var method = document.getElementById('subscription_method');
    var wrapper = document.getElementById('license_limit_fields');
    var planLabel = document.getElementById('subscription_plan_label');
    var planSelect = document.getElementById('subscription_plan_id');
    var status = document.getElementById('subscription_status');
    var trialOption = status ? status.querySelector('option[value="trial"]') : null;
    var trialDaysField = document.getElementById('trial_days_remaining_field');
    var trialDaysInput = document.querySelector('input[name="trial_days_remaining"]');
    if (!method || !wrapper) {
        return;
    }

    if (planSelect) {
        var isLicensePlan = method.value === 'license';
        var emptyOption = planSelect.querySelector('option[value=""]');
        if (emptyOption) {
            emptyOption.textContent = isLicensePlan
                ? '- Tidak ada paket lisensi -'
                : '- Tidak ada -';
        }
        Array.from(planSelect.options).forEach(function (option) {
            var planName = option.getAttribute('data-plan-name');
            if (!planName) {
                return;
            }
            var planPrice = option.getAttribute('data-plan-price') || '0';
            var planDuration = option.getAttribute('data-plan-duration') || '30';
            var durationLabel = isLicensePlan
                ? '{{ \App\Models\User::LICENSE_DURATION_DAYS }} hari (Lisensi)'
                : planDuration + ' hari';
            option.textContent = planName + ' - Rp ' + planPrice + ' - ' + durationLabel;
        });
    }

    if (method.value === 'license') {
        if (planLabel) {
            planLabel.textContent = 'Paket Lisensi';
        }
        wrapper.classList.remove('d-none');
        if (trialOption) {
            trialOption.disabled = true;
        }
        if (status && status.value === 'trial') {
            status.value = 'active';
        }
        if (trialDaysField) {
            trialDaysField.classList.add('d-none');
        }
        if (trialDaysInput) {
            trialDaysInput.value = '0';
        }
    } else {
        if (planLabel) {
            planLabel.textContent = 'Paket Langganan';
        }
        wrapper.classList.add('d-none');
        if (trialOption) {
            trialOption.disabled = false;
        }
        if (trialDaysField) {
            trialDaysField.classList.remove('d-none');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var method = document.getElementById('subscription_method');
    if (!method) {
        return;
    }
    method.addEventListener('change', toggleLicenseLimitFields);
    toggleLicenseLimitFields();
});
</script>
@endpush
