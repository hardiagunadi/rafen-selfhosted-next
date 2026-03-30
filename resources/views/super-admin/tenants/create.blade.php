@extends('layouts.admin')

@section('title', 'Tambah Tenant Baru')

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

<form action="{{ route('super-admin.tenants.store') }}" method="POST">
@csrf
<div class="mf-page">

    {{-- Page Header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#3730a3,#6366f1);">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div class="mf-page-title">Tambah Tenant Baru</div>
                <div class="mf-page-sub">Daftarkan ISP/tenant baru ke platform</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('super-admin.tenants') }}" class="mf-btn-back">
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
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#3730a3,#6366f1);">
                    <i class="fas fa-user"></i>
                </div>
                <span class="mf-section-title">Data Akun</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama Lengkap <span class="mf-req">*</span></label>
                        <input type="text" name="name" class="mf-input @error('name') mf-input-error @enderror" value="{{ old('name') }}" required>
                        @error('name')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Email <span class="mf-req">*</span></label>
                        <input type="email" name="email" class="mf-input @error('email') mf-input-error @enderror" value="{{ old('email') }}" required>
                        @error('email')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Password <span class="mf-req">*</span></label>
                        <input type="password" name="password" class="mf-input @error('password') mf-input-error @enderror" required>
                        @error('password')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Konfirmasi Password <span class="mf-req">*</span></label>
                        <input type="password" name="password_confirmation" class="mf-input" required>
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Telepon <span class="mf-opt">opsional</span></label>
                        <input type="text" name="phone" class="mf-input" value="{{ old('phone') }}">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Nama Perusahaan <span class="mf-opt">opsional</span></label>
                        <input type="text" name="company_name" id="company_name" class="mf-input" value="{{ old('company_name') }}">
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Subdomain <span class="mf-req">*</span></label>
                        <div class="input-group">
                            <input type="text" name="admin_subdomain" id="admin_subdomain"
                                class="mf-input @error('admin_subdomain') mf-input-error @enderror"
                                value="{{ old('admin_subdomain') }}"
                                placeholder="nama-isp"
                                pattern="[a-z0-9\-]+" maxlength="63" required>
                            <div class="input-group-append">
                                <span class="input-group-text text-muted" style="font-size:.82rem;">.{{ config('app.main_domain') }}</span>
                            </div>
                        </div>
                        @error('admin_subdomain')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Hanya huruf kecil, angka, dan tanda hubung (-). Tidak bisa diubah setelah dibuat.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pengaturan Langganan --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#3730a3,#6366f1);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <span class="mf-section-title">Pengaturan Langganan</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label" id="subscription_plan_label">Paket Langganan</label>
                        <select name="subscription_plan_id" id="subscription_plan_id" class="mf-input">
                            <option value="">- Tidak ada (Trial) -</option>
                            @foreach($plans as $plan)
                            <option value="{{ $plan->id }}"
                                data-plan-name="{{ $plan->name }}"
                                data-plan-price="{{ number_format($plan->price, 0, ',', '.') }}"
                                data-plan-duration="{{ $plan->duration_days }}"
                                {{ (string) old('subscription_plan_id') === (string) $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $plan->duration_days }} hari
                            </option>
                            @endforeach
                        </select>
                        <span class="mf-hint">Daftar paket lisensi mengambil data dari menu Kelola Paket Langganan.</span>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Metode Langganan</label>
                        <select name="subscription_method" id="subscription_method" class="mf-input @error('subscription_method') mf-input-error @enderror">
                            <option value="monthly" {{ old('subscription_method', 'monthly') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                            <option value="license" {{ old('subscription_method') === 'license' ? 'selected' : '' }}>Lisensi (Tahunan)</option>
                        </select>
                        @error('subscription_method')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Lisensi otomatis berlaku 1 tahun.</span>
                    </div>
                </div>

                <div id="license_limit_fields" class="mf-row d-none">
                    <div class="mf-field">
                        <label class="mf-label">Limit Mikrotik (Lisensi)</label>
                        <input type="number" name="license_max_mikrotik" class="mf-input @error('license_max_mikrotik') mf-input-error @enderror" value="{{ old('license_max_mikrotik') }}" min="-1">
                        @error('license_max_mikrotik')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Isi <code>-1</code> untuk tanpa batas.</span>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Limit PPP Users (Lisensi)</label>
                        <input type="number" name="license_max_ppp_users" class="mf-input @error('license_max_ppp_users') mf-input-error @enderror" value="{{ old('license_max_ppp_users') }}" min="-1">
                        @error('license_max_ppp_users')
                            <span class="mf-feedback">{{ $message }}</span>
                        @enderror
                        <span class="mf-hint">Isi <code>-1</code> untuk tanpa batas.</span>
                    </div>
                </div>

                <div class="mf-row" id="trial_days_field">
                    <div class="mf-field">
                        <label class="mf-label">Masa Trial (hari)</label>
                        <input type="number" name="trial_days" class="mf-input" value="{{ old('trial_days', 14) }}" min="0" max="90">
                        <span class="mf-hint">Berlaku jika tidak memilih paket</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Footer --}}
    <div class="mf-footer">
        <a href="{{ route('super-admin.tenants') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit">
            <i class="fas fa-save" style="margin-right:.45rem;"></i> Simpan
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
    var trialDaysField = document.getElementById('trial_days_field');
    var trialDaysInput = document.querySelector('input[name="trial_days"]');
    if (!method || !wrapper) {
        return;
    }

    if (planSelect) {
        var isLicensePlan = method.value === 'license';
        var emptyOption = planSelect.querySelector('option[value=""]');
        if (emptyOption) {
            emptyOption.textContent = isLicensePlan
                ? '- Tidak ada paket lisensi -'
                : '- Tidak ada (Trial) -';
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
        if (trialDaysField) {
            trialDaysField.classList.remove('d-none');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var method = document.getElementById('subscription_method');
    if (method) {
        method.addEventListener('change', toggleLicenseLimitFields);
        toggleLicenseLimitFields();
    }

    // Auto-generate subdomain dari nama perusahaan (hanya jika field subdomain masih kosong)
    var companyInput = document.getElementById('company_name');
    var subdomainInput = document.getElementById('admin_subdomain');
    if (companyInput && subdomainInput) {
        companyInput.addEventListener('input', function () {
            if (subdomainInput.dataset.manuallyEdited) return;
            var slug = companyInput.value
                .toLowerCase()
                .replace(/[^a-z0-9\s\-]/g, '')
                .trim()
                .replace(/[\s]+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 63);
            subdomainInput.value = slug;
        });
        subdomainInput.addEventListener('input', function () {
            subdomainInput.dataset.manuallyEdited = '1';
        });
    }
});
</script>
@endpush
