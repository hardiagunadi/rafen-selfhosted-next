@extends('layouts.admin')

@section('title', 'Edit Gangguan #' . $outage->id)

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
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
@media (max-width:767px) { .mf-row { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }
.mf-label { font-size:.77rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);display:flex;align-items:center;gap:.4rem; }
.mf-opt { font-size:.7rem;font-weight:600;border-radius:20px;padding:.1rem .45rem;background:rgba(100,116,139,.1);color:#64748b;text-transform:none;letter-spacing:0; }
.mf-input { height:38px;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);padding:0 .75rem;font-size:.85rem;color:var(--app-text,#0f172a);background:#fff;outline:none;width:100%;transition:border-color 150ms,box-shadow 150ms; }
select.mf-input { appearance:auto; }
textarea.mf-input { height:auto;padding:.5rem .75rem;resize:vertical; }
.mf-input:focus { border-color:#8fb5df;box-shadow:0 0 0 3px rgba(19,103,164,.12); }
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
</style>

<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Insiden <span class="mf-dim">#{{ $outage->id }}</span></div>
                <div class="mf-page-sub">{{ $outage->title }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('outages.show', $outage) }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    <form id="editOutageForm">
        @csrf
        @method('PUT')
        <div class="mf-grid">

            {{-- Section: Detail Gangguan --}}
            <div class="mf-section">
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#991b1b,#ef4444);">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                    <div class="mf-section-title">Detail Gangguan</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-field">
                        <label class="mf-label">Judul</label>
                        <input type="text" name="title" class="mf-input" value="{{ $outage->title }}">
                    </div>
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Severity</label>
                            <select name="severity" class="mf-input">
                                @foreach(['low','medium','high','critical'] as $s)
                                <option value="{{ $s }}" {{ $outage->severity === $s ? 'selected' : '' }}>{{ strtoupper($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Waktu Mulai</label>
                            <input type="datetime-local" name="started_at" class="mf-input"
                                   value="{{ $outage->started_at->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Estimasi Selesai <span class="mf-opt">Opsional</span></label>
                        <input type="datetime-local" name="estimated_resolved_at" class="mf-input"
                               value="{{ $outage->estimated_resolved_at?->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Deskripsi <span class="mf-opt">Opsional</span></label>
                        <textarea name="description" class="mf-input" rows="3">{{ $outage->description }}</textarea>
                    </div>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="mf-footer" style="margin-top:1rem;">
            <a href="{{ route('outages.show', $outage) }}" class="mf-btn-cancel">Batal</a>
            <button type="submit" class="mf-btn-submit" id="saveBtn">
                <i class="fas fa-save mr-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection

@push('js')
<script>
document.getElementById('editOutageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;

    const fd = new FormData(this);
    fetch('{{ route('outages.update', $outage) }}', {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = '{{ route('outages.show', $outage) }}';
        } else {
            alert(res.message || 'Gagal menyimpan.');
            btn.disabled = false;
        }
    })
    .catch(() => { alert('Terjadi kesalahan.'); btn.disabled = false; });
});
</script>
@endpush
