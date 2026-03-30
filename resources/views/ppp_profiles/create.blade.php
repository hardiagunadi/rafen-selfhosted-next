@extends('layouts.admin')

@section('title', 'Tambah Profil PPP')

@section('content')
<div class="mf-page">
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9)">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <div class="mf-page-title">Tambah Profil PPP</div>
                <div class="mf-page-sub">Buat profil PPP baru</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('ppp-profiles.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left" style="margin-right:.4rem;font-size:.8rem"></i> Kembali
            </a>
        </div>
    </div>

    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle" style="margin-top:.1rem;flex-shrink:0"></i>
        <div>
            <strong>Terdapat kesalahan input:</strong>
            <ul style="margin:.25rem 0 0 1rem;padding:0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <form action="{{ route('ppp-profiles.store') }}" method="POST">
        @csrf
        <div class="mf-grid">
            <div class="mf-section">
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9)">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="mf-section-title">Informasi Profil</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Nama <span class="mf-req">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="mf-input @error('name') mf-input-error @enderror" required>
                            @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Owner Data</label>
                            <select name="owner_id" class="mf-input @error('owner_id') mf-input-error @enderror">
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                            @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row mf-row-3">
                        <div class="mf-field">
                            <label class="mf-label">Harga Modal</label>
                            <input type="number" step="0.01" name="harga_modal" value="{{ old('harga_modal', 0) }}" class="mf-input @error('harga_modal') mf-input-error @enderror">
                            @error('harga_modal')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Harga Promo</label>
                            <input type="number" step="0.01" name="harga_promo" value="{{ old('harga_promo', 0) }}" class="mf-input @error('harga_promo') mf-input-error @enderror">
                            @error('harga_promo')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">PPN (%)</label>
                            <input type="number" step="0.01" name="ppn" value="{{ old('ppn', 0) }}" class="mf-input @error('ppn') mf-input-error @enderror">
                            @error('ppn')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Group Profil</label>
                            <select name="profile_group_id" class="mf-input @error('profile_group_id') mf-input-error @enderror">
                                <option value="">- pilih -</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" @selected(old('profile_group_id') == $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                            @error('profile_group_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Bandwidth</label>
                            <select name="bandwidth_profile_id" class="mf-input @error('bandwidth_profile_id') mf-input-error @enderror">
                                <option value="">- pilih -</option>
                                @foreach($bandwidths as $bw)
                                    <option value="{{ $bw->id }}" @selected(old('bandwidth_profile_id') == $bw->id)>{{ $bw->name }}</option>
                                @endforeach
                            </select>
                            @error('bandwidth_profile_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">
                                Parent Queue
                                <span class="mf-opt">opsional, override per-profil</span>
                            </label>
                            <select name="parent_queue" id="parent_queue_select" class="mf-input @error('parent_queue') mf-input-error @enderror">
                                <option value="">- dari ProfileGroup / tidak ada -</option>
                            </select>
                            @error('parent_queue')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <span class="mf-hint" id="queue-fetch-status"></span>
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Masa Aktif</label>
                            <input type="number" name="masa_aktif" value="{{ old('masa_aktif', 1) }}" class="mf-input @error('masa_aktif') mf-input-error @enderror" min="1">
                            @error('masa_aktif')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Satuan</label>
                            <select name="satuan" class="mf-input @error('satuan') mf-input-error @enderror">
                                <option value="bulan" @selected(old('satuan', 'bulan') === 'bulan')>Bulan</option>
                            </select>
                            @error('satuan')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mf-footer">
            <a href="{{ route('ppp-profiles.index') }}" class="mf-btn-cancel">Batal</a>
            <button type="submit" class="mf-btn-submit">
                <i class="fas fa-save" style="margin-right:.4rem"></i> Simpan
            </button>
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
    (function fetchParentQueues() {
        var sel = document.getElementById('parent_queue_select');
        var status = document.getElementById('queue-fetch-status');
        var current = '{{ old("parent_queue") }}';
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
            status.textContent = '';
        })
        .catch(function () { status.textContent = 'Gagal mengambil queue dari Mikrotik.'; });
    })();
</script>
@endsection
