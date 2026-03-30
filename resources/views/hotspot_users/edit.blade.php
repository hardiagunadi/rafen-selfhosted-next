@extends('layouts.admin')

@section('title', 'Edit User Hotspot')

@section('content')
<div class="mf-page">

    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit <span class="mf-dim">User Hotspot</span></div>
                <div class="mf-page-sub">{{ $hotspotUser->customer_name }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('hotspot-users.index') }}" class="mf-btn-back"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>

    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
        <div><strong>Data belum valid:</strong><ul class="mb-0 mt-1 pl-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
    @endif

    <form action="{{ route('hotspot-users.update', $hotspotUser) }}" method="POST" novalidate>
    @csrf
    @method('PUT')
    <div class="mf-grid">

        {{-- Data Layanan --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-wifi"></i></div>
                <div class="mf-section-title">Data Layanan</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Owner Data <span class="mf-req">*</span></label>
                        <select name="owner_id" class="mf-input @error('owner_id') mf-input-error @enderror" required>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id', $hotspotUser->owner_id) == $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Profil Hotspot <span class="mf-req">*</span></label>
                        <select name="hotspot_profile_id" class="mf-input @error('hotspot_profile_id') mf-input-error @enderror" required>
                            <option value="" disabled>- pilih profil -</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}" @selected(old('hotspot_profile_id', $hotspotUser->hotspot_profile_id) == $profile->id)>
                                    {{ $profile->name }} - Rp {{ number_format((float) $profile->harga_jual, 0, ',', '.') }} - {{ $profile->masa_aktif_value ? ((int) $profile->masa_aktif_value.' '.$profile->masa_aktif_unit) : '-' }}
                                </option>
                            @endforeach
                        </select>
                        @error('hotspot_profile_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Status Registrasi <span class="mf-req">*</span></label>
                        <div style="display:flex;gap:1.2rem;align-items:center;flex-wrap:wrap;padding-top:.35rem;">
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                                <input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', $hotspotUser->status_registrasi) === 'aktif') required> AKTIF
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                                <input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi', $hotspotUser->status_registrasi) === 'on_process')> ON PROCESS
                            </label>
                        </div>
                        @error('status_registrasi')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Tipe Pembayaran <span class="mf-req">*</span></label>
                        <select name="tipe_pembayaran" class="mf-input @error('tipe_pembayaran') mf-input-error @enderror" required>
                            <option value="prepaid" @selected(old('tipe_pembayaran', $hotspotUser->tipe_pembayaran) === 'prepaid')>PREPAID</option>
                            <option value="postpaid" @selected(old('tipe_pembayaran', $hotspotUser->tipe_pembayaran) === 'postpaid')>POSTPAID</option>
                        </select>
                        @error('tipe_pembayaran')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Status Bayar <span class="mf-req">*</span></label>
                        <select name="status_bayar" class="mf-input @error('status_bayar') mf-input-error @enderror" required>
                            <option value="sudah_bayar" @selected(old('status_bayar', $hotspotUser->status_bayar) === 'sudah_bayar')>SUDAH BAYAR</option>
                            <option value="belum_bayar" @selected(old('status_bayar', $hotspotUser->status_bayar) === 'belum_bayar')>BELUM BAYAR</option>
                        </select>
                        @error('status_bayar')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Status Akun <span class="mf-req">*</span></label>
                        <select name="status_akun" class="mf-input @error('status_akun') mf-input-error @enderror" required>
                            <option value="enable" @selected(old('status_akun', $hotspotUser->status_akun) === 'enable')>ENABLE</option>
                            <option value="disable" @selected(old('status_akun', $hotspotUser->status_akun) === 'disable')>DISABLE</option>
                            <option value="isolir" @selected(old('status_akun', $hotspotUser->status_akun) === 'isolir')>ISOLIR</option>
                        </select>
                        @error('status_akun')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Aksi Jatuh Tempo <span class="mf-req">*</span></label>
                        <select name="aksi_jatuh_tempo" class="mf-input @error('aksi_jatuh_tempo') mf-input-error @enderror" required>
                            <option value="isolir" @selected(old('aksi_jatuh_tempo', $hotspotUser->aksi_jatuh_tempo) === 'isolir')>ISOLIR</option>
                            <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo', $hotspotUser->aksi_jatuh_tempo) === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                        </select>
                        @error('aksi_jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Jatuh Tempo</label>
                        <input type="date" name="jatuh_tempo" class="mf-input @error('jatuh_tempo') mf-input-error @enderror"
                            value="{{ old('jatuh_tempo', $hotspotUser->jatuh_tempo?->format('Y-m-d')) }}">
                        @error('jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Informasi Pelanggan --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);"><i class="fas fa-user"></i></div>
                <div class="mf-section-title">Informasi Pelanggan</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama Lengkap <span class="mf-req">*</span></label>
                        <input type="text" name="customer_name" class="mf-input @error('customer_name') mf-input-error @enderror"
                            value="{{ old('customer_name', $hotspotUser->customer_name) }}" required>
                        @error('customer_name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">ID Pelanggan</label>
                        <input type="text" name="customer_id" class="mf-input @error('customer_id') mf-input-error @enderror"
                            value="{{ old('customer_id', $hotspotUser->customer_id) }}">
                        @error('customer_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">No. HP</label>
                        <input type="text" name="nomor_hp" class="mf-input @error('nomor_hp') mf-input-error @enderror"
                            value="{{ old('nomor_hp', $hotspotUser->nomor_hp) }}">
                        @error('nomor_hp')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Email</label>
                        <input type="email" name="email" class="mf-input @error('email') mf-input-error @enderror"
                            value="{{ old('email', $hotspotUser->email) }}">
                        @error('email')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">NIK</label>
                        <input type="text" name="nik" class="mf-input @error('nik') mf-input-error @enderror"
                            value="{{ old('nik', $hotspotUser->nik) }}">
                        @error('nik')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mf-field">
                    <label class="mf-label">Alamat</label>
                    <textarea name="alamat" class="mf-input @error('alamat') mf-input-error @enderror" rows="2">{{ old('alamat', $hotspotUser->alamat) }}</textarea>
                    @error('alamat')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Kredensial Hotspot --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-key"></i></div>
                <div class="mf-section-title">Kredensial Hotspot</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-field">
                    <label class="mf-label">Metode Login <span class="mf-req">*</span></label>
                    <select name="metode_login" id="metode-login-select" class="mf-input @error('metode_login') mf-input-error @enderror" required>
                        <option value="username_equals_password" @selected(old('metode_login', $hotspotUser->metode_login ?? 'username_equals_password') === 'username_equals_password')>HANYA USERNAME ( PASSWORD = USERNAME )</option>
                        <option value="username_password" @selected(old('metode_login', $hotspotUser->metode_login) === 'username_password')>USERNAME &amp; PASSWORD</option>
                    </select>
                    @error('metode_login')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Username <span class="mf-req">*</span></label>
                        <input type="text" name="username" class="mf-input @error('username') mf-input-error @enderror"
                            value="{{ old('username', $hotspotUser->username) }}" required placeholder="Username">
                        @error('username')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field" id="password-field-row">
                        <label class="mf-label">Password Hotspot <span class="mf-req">*</span></label>
                        <input type="text" name="hotspot_password" id="hotspot-password-input" class="mf-input @error('hotspot_password') mf-input-error @enderror"
                            value="{{ old('hotspot_password', $hotspotUser->masked_password) }}" placeholder="Password">
                        @error('hotspot_password')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mf-field">
                    <label class="mf-label">Catatan</label>
                    <textarea name="catatan" class="mf-input @error('catatan') mf-input-error @enderror" rows="2">{{ old('catatan', $hotspotUser->catatan) }}</textarea>
                    @error('catatan')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Teknisi --}}
        @if(!auth()->user()->isTeknisi())
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#059669,#34d399);"><i class="fas fa-hard-hat"></i></div>
                <div class="mf-section-title">Penugasan Teknisi</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-field">
                    <label class="mf-label">Teknisi yang Ditugaskan</label>
                    <select name="assigned_teknisi_id" class="mf-input @error('assigned_teknisi_id') mf-input-error @enderror">
                        <option value="">-- Tidak ada / Semua teknisi bisa akses --</option>
                        @foreach($teknisiList as $teknisi)
                            <option value="{{ $teknisi->id }}" {{ old('assigned_teknisi_id', $hotspotUser->assigned_teknisi_id) == $teknisi->id ? 'selected' : '' }}>
                                {{ $teknisi->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_teknisi_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    <div class="mf-hint">Jika diisi, hanya teknisi yang dipilih yang dapat mengelola pelanggan ini. Teknisi lain hanya bisa melihat data.</div>
                </div>
            </div>
        </div>
        @endif

    </div>

    <div class="mf-footer">
        <a href="{{ route('hotspot-users.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
    </div>
    </form>
</div>

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

@push('scripts')
<script>
$(function() {
    var $metodeSelect = $('#metode-login-select');
    var $passwordRow = $('#password-field-row');
    var $passwordInput = $('#hotspot-password-input');

    function togglePasswordField() {
        if ($metodeSelect.val() === 'username_equals_password') {
            $passwordRow.hide();
            $passwordInput.prop('required', false).val('');
        } else {
            $passwordRow.show();
            $passwordInput.prop('required', true);
        }
    }

    $metodeSelect.on('change', togglePasswordField);
    togglePasswordField();
});
</script>
@endpush
@endsection
