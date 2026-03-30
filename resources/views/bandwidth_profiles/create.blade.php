@extends('layouts.admin')

@section('title', 'Tambah Profil Bandwidth')

@section('content')
<div class="mf-page">
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#065f46,#10b981)">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div>
                <div class="mf-page-title">Tambah Profil Bandwidth</div>
                <div class="mf-page-sub">Buat profil bandwidth baru</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('bandwidth-profiles.index') }}" class="mf-btn-back">
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

    <form action="{{ route('bandwidth-profiles.store') }}" method="POST">
        @csrf
        <div class="mf-grid">
            <div class="mf-section">
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981)">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="mf-section-title">Informasi Bandwidth</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row">
                        <div class="mf-field" style="grid-column:1/-1">
                            <label class="mf-label">Nama Bandwidth <span class="mf-req">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="mf-input @error('name') mf-input-error @enderror" required>
                            @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Upload Min (Mbps)</label>
                            <input type="number" name="upload_min_mbps" value="{{ old('upload_min_mbps', 0) }}" class="mf-input @error('upload_min_mbps') mf-input-error @enderror" min="0">
                            @error('upload_min_mbps')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Upload Max (Mbps)</label>
                            <input type="number" name="upload_max_mbps" value="{{ old('upload_max_mbps', 0) }}" class="mf-input @error('upload_max_mbps') mf-input-error @enderror" min="0">
                            @error('upload_max_mbps')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Download Min (Mbps)</label>
                            <input type="number" name="download_min_mbps" value="{{ old('download_min_mbps', 0) }}" class="mf-input @error('download_min_mbps') mf-input-error @enderror" min="0">
                            @error('download_min_mbps')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Download Max (Mbps)</label>
                            <input type="number" name="download_max_mbps" value="{{ old('download_max_mbps', 0) }}" class="mf-input @error('download_max_mbps') mf-input-error @enderror" min="0">
                            @error('download_max_mbps')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Owner Data</label>
                            <select name="owner" class="mf-input @error('owner') mf-input-error @enderror">
                                <option value="">- pilih -</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->name }}" @selected(old('owner') === $user->name)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('owner')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mf-footer">
            <a href="{{ route('bandwidth-profiles.index') }}" class="mf-btn-cancel">Batal</a>
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
@endsection
