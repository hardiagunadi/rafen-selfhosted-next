@extends('layouts.admin')

@section('title', 'Edit Profil Hotspot')

@section('content')
@php
    $profileType = old('profile_type', $hotspotProfile->profile_type);
    $limitType = old('limit_type', $hotspotProfile->limit_type ?? 'time');
@endphp
<div class="mf-page">

    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-wifi"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit <span class="mf-dim">Profil Hotspot</span></div>
                <div class="mf-page-sub">{{ $hotspotProfile->name }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('hotspot-profiles.index') }}" class="mf-btn-back"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>

    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
        <div><strong>Data belum valid:</strong><ul class="mb-0 mt-1 pl-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
    @endif

    <form action="{{ route('hotspot-profiles.update', $hotspotProfile) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="mf-grid">

        {{-- Informasi Dasar --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-info-circle"></i></div>
                <div class="mf-section-title">Informasi Dasar</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama <span class="mf-req">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $hotspotProfile->name) }}" class="mf-input @error('name') mf-input-error @enderror" required>
                        @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Owner Data <span class="mf-req">*</span></label>
                        <select name="owner_id" class="mf-input @error('owner_id') mf-input-error @enderror" required>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id', $hotspotProfile->owner_id) == $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Harga --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#059669,#34d399);"><i class="fas fa-tag"></i></div>
                <div class="mf-section-title">Harga</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Harga Jual</label>
                        <input type="number" step="0.01" name="harga_jual" value="{{ old('harga_jual', $hotspotProfile->harga_jual) }}" class="mf-input @error('harga_jual') mf-input-error @enderror">
                        @error('harga_jual')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Harga Promo</label>
                        <input type="number" step="0.01" name="harga_promo" value="{{ old('harga_promo', $hotspotProfile->harga_promo) }}" class="mf-input @error('harga_promo') mf-input-error @enderror">
                        @error('harga_promo')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">PPN (%)</label>
                        <input type="number" step="0.01" name="ppn" value="{{ old('ppn', $hotspotProfile->ppn) }}" class="mf-input @error('ppn') mf-input-error @enderror">
                        @error('ppn')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Bandwidth & Group --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#7c3aed,#a78bfa);"><i class="fas fa-tachometer-alt"></i></div>
                <div class="mf-section-title">Bandwidth & Group</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Bandwidth</label>
                        <select name="bandwidth_profile_id" class="mf-input @error('bandwidth_profile_id') mf-input-error @enderror">
                            <option value="">- pilih -</option>
                            @foreach($bandwidths as $bw)
                                <option value="{{ $bw->id }}" @selected(old('bandwidth_profile_id', $hotspotProfile->bandwidth_profile_id) == $bw->id)>{{ $bw->name }}</option>
                            @endforeach
                        </select>
                        @error('bandwidth_profile_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Profil Group</label>
                        <select name="profile_group_id" class="mf-input @error('profile_group_id') mf-input-error @enderror">
                            <option value="">- pilih -</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('profile_group_id', $hotspotProfile->profile_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('profile_group_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Tipe Profil & Limit --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-sliders-h"></i></div>
                <div class="mf-section-title">Tipe Profil & Limit</div>
            </div>
            <div class="mf-section-body">

                <div class="mf-field">
                    <label class="mf-label">Tipe Profil</label>
                    <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                        <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                            <input type="radio" name="profile_type" id="profile-type-unlimited" value="unlimited" @checked(old('profile_type', $hotspotProfile->profile_type) === 'unlimited')> Unlimited
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                            <input type="radio" name="profile_type" id="profile-type-limited" value="limited" @checked(old('profile_type', $hotspotProfile->profile_type) === 'limited')> Limited
                        </label>
                    </div>
                    @error('profile_type')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>

                <div id="limit-type-section" style="{{ $profileType === 'limited' ? '' : 'display:none;' }}">
                    <div class="mf-field">
                        <label class="mf-label">Tipe Limit</label>
                        <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                                <input type="radio" name="limit_type" id="limit-type-time" value="time" @checked(old('limit_type', $hotspotProfile->limit_type ?? 'time') === 'time')> TimeBase
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600;cursor:pointer;">
                                <input type="radio" name="limit_type" id="limit-type-quota" value="quota" @checked(old('limit_type', $hotspotProfile->limit_type) === 'quota')> QuotaBase
                            </label>
                        </div>
                        @error('limit_type')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="time-limit-section" class="mf-row" style="{{ $profileType === 'limited' && $limitType === 'time' ? '' : 'display:none;' }}">
                    <div class="mf-field">
                        <label class="mf-label">TimeBase</label>
                        <input type="number" min="1" name="time_limit_value" value="{{ old('time_limit_value', $hotspotProfile->time_limit_value) }}" class="mf-input @error('time_limit_value') mf-input-error @enderror">
                        @error('time_limit_value')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Satuan</label>
                        <select name="time_limit_unit" class="mf-input @error('time_limit_unit') mf-input-error @enderror">
                            <option value="menit" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'menit')>Menit</option>
                            <option value="jam" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'jam')>Jam</option>
                            <option value="hari" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'hari')>Hari</option>
                            <option value="bulan" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'bulan')>Bulan</option>
                        </select>
                        @error('time_limit_unit')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="quota-limit-section" class="mf-row" style="{{ $profileType === 'limited' && $limitType === 'quota' ? '' : 'display:none;' }}">
                    <div class="mf-field">
                        <label class="mf-label">QuotaBase</label>
                        <input type="number" min="1" step="0.01" name="quota_limit_value" value="{{ old('quota_limit_value', $hotspotProfile->quota_limit_value) }}" class="mf-input @error('quota_limit_value') mf-input-error @enderror">
                        @error('quota_limit_value')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Satuan</label>
                        <select name="quota_limit_unit" class="mf-input @error('quota_limit_unit') mf-input-error @enderror">
                            <option value="mb" @selected(old('quota_limit_unit', $hotspotProfile->quota_limit_unit) === 'mb')>MB</option>
                            <option value="gb" @selected(old('quota_limit_unit', $hotspotProfile->quota_limit_unit) === 'gb')>GB</option>
                        </select>
                        @error('quota_limit_unit')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="masa-aktif-section" class="mf-row" style="{{ $profileType === 'unlimited' ? '' : 'display:none;' }}">
                    <div class="mf-field">
                        <label class="mf-label">Masa Aktif</label>
                        <input type="number" min="1" name="masa_aktif_value" value="{{ old('masa_aktif_value', $hotspotProfile->masa_aktif_value) }}" class="mf-input @error('masa_aktif_value') mf-input-error @enderror">
                        @error('masa_aktif_value')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Satuan</label>
                        <select name="masa_aktif_unit" class="mf-input @error('masa_aktif_unit') mf-input-error @enderror">
                            <option value="menit" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'menit')>Menit</option>
                            <option value="jam" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'jam')>Jam</option>
                            <option value="hari" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'hari')>Hari</option>
                            <option value="bulan" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'bulan')>Bulan</option>
                        </select>
                        @error('masa_aktif_unit')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

            </div>
        </div>

        {{-- Queue & Lainnya --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-network-wired"></i></div>
                <div class="mf-section-title">Queue & Lainnya</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Parent Queue <span class="mf-opt">opsional, override per-profil</span></label>
                        <select name="parent_queue" id="parent_queue_select" class="mf-input @error('parent_queue') mf-input-error @enderror">
                            <option value="">- dari ProfileGroup / tidak ada -</option>
                        </select>
                        @error('parent_queue')<div class="mf-feedback">{{ $message }}</div>@enderror
                        <div class="mf-hint" id="queue-fetch-status"></div>
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Shared Users</label>
                        <input type="number" min="1" name="shared_users" value="{{ old('shared_users', $hotspotProfile->shared_users) }}" class="mf-input @error('shared_users') mf-input-error @enderror">
                        @error('shared_users')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Prioritas</label>
                        <select name="prioritas" class="mf-input @error('prioritas') mf-input-error @enderror">
                            <option value="default" @selected(old('prioritas', $hotspotProfile->prioritas) === 'default')>Default</option>
                            @for($i = 1; $i <= 8; $i++)
                                <option value="prioritas{{ $i }}" @selected(old('prioritas', $hotspotProfile->prioritas) === 'prioritas'.$i)>Prioritas {{ $i }}</option>
                            @endfor
                        </select>
                        @error('prioritas')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="mf-footer">
        <a href="{{ route('hotspot-profiles.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit"><i class="fas fa-save mr-1"></i> Update</button>
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

<script>
    (function () {
        const profileTypeRadios = document.querySelectorAll('input[name="profile_type"]');
        const limitTypeRadios = document.querySelectorAll('input[name="limit_type"]');
        const limitTypeSection = document.getElementById('limit-type-section');
        const timeSection = document.getElementById('time-limit-section');
        const quotaSection = document.getElementById('quota-limit-section');
        const masaAktifSection = document.getElementById('masa-aktif-section');

        const syncVisibility = () => {
            const profileType = document.querySelector('input[name="profile_type"]:checked')?.value;
            const limitType = document.querySelector('input[name="limit_type"]:checked')?.value;

            if (profileType === 'limited') {
                limitTypeSection.style.display = '';
                masaAktifSection.style.display = 'none';
                timeSection.style.display = limitType === 'time' ? '' : 'none';
                quotaSection.style.display = limitType === 'quota' ? '' : 'none';
            } else {
                limitTypeSection.style.display = 'none';
                timeSection.style.display = 'none';
                quotaSection.style.display = 'none';
                masaAktifSection.style.display = '';
            }
        };

        profileTypeRadios.forEach(radio => radio.addEventListener('change', syncVisibility));
        limitTypeRadios.forEach(radio => radio.addEventListener('change', syncVisibility));
        syncVisibility();
    })();

    (function fetchParentQueues() {
        var sel = document.getElementById('parent_queue_select');
        var status = document.getElementById('queue-fetch-status');
        var current = '{{ old('parent_queue', $hotspotProfile->parent_queue) }}';
        fetch('{{ route("profile-groups.mikrotik-queues") }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { status.textContent = data.error; return; }
            (data.queues || []).forEach(function (q) {
                var opt = document.createElement('option');
                opt.value = q; opt.textContent = q;
                if (q === current) opt.selected = true;
                sel.appendChild(opt);
            });
            status.textContent = 'Kosongkan untuk menggunakan parent queue dari ProfileGroup.';
        })
        .catch(function () { status.textContent = 'Gagal mengambil queue dari Mikrotik.'; });
    })();
</script>
@endsection
