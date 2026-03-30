@extends('layouts.admin')

@section('title', 'Laporkan Gangguan Jaringan')

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
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#991b1b,#ef4444);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(239,68,68,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
.mf-layout { display:grid;grid-template-columns:1fr 320px;gap:1rem;align-items:start; }
@media (max-width:991px) { .mf-layout { grid-template-columns:1fr; } }
</style>

<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#991b1b,#ef4444);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div class="mf-page-title">Laporkan <span class="mf-dim">Gangguan</span></div>
                <div class="mf-page-sub">Buat insiden gangguan jaringan dan notifikasi pelanggan terdampak</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('outages.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="mf-layout">
        {{-- Main form column --}}
        <div class="mf-grid">
            <form id="createOutageForm">
                @csrf

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
                            <label class="mf-label">Judul Gangguan <span class="mf-req">*</span></label>
                            <input type="text" name="title" class="mf-input" required
                                   placeholder="Contoh: Putus Fiber Backbone Jalur A – Desa Binangun">
                        </div>
                        <div class="mf-row">
                            <div class="mf-field">
                                <label class="mf-label">Severity <span class="mf-req">*</span></label>
                                <select name="severity" class="mf-input" required>
                                    <option value="medium" selected>Medium – Berdampak pada sebagian pelanggan</option>
                                    <option value="high">High – Berdampak luas</option>
                                    <option value="critical">Critical – Backbone / down total</option>
                                    <option value="low">Low – Dampak kecil</option>
                                </select>
                            </div>
                            <div class="mf-field">
                                <label class="mf-label">Teknisi Penanggungjawab <span class="mf-opt">Opsional</span></label>
                                <select name="assigned_teknisi_id" class="mf-input">
                                    <option value="">– Pilih Teknisi –</option>
                                    @foreach($teknisiList as $t)
                                    <option value="{{ $t->id }}">{{ $t->nickname ?? $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mf-row">
                            <div class="mf-field">
                                <label class="mf-label">Waktu Mulai Gangguan <span class="mf-req">*</span></label>
                                <input type="datetime-local" name="started_at" class="mf-input" required
                                       value="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                            <div class="mf-field">
                                <label class="mf-label">Estimasi Selesai <span class="mf-opt">Opsional</span></label>
                                <input type="datetime-local" name="estimated_resolved_at" class="mf-input">
                            </div>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Deskripsi / Catatan Internal <span class="mf-opt">Opsional</span></label>
                            <textarea name="description" class="mf-input" rows="2"
                                      placeholder="Deskripsi singkat penyebab gangguan (opsional)"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Section: Area Terdampak --}}
                <div class="mf-section">
                    <div class="mf-section-header">
                        <div class="mf-section-icon" style="background:linear-gradient(140deg,#991b1b,#ef4444);">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="mf-section-title">Area Terdampak</div>
                    </div>
                    <div class="mf-section-body">
                        <p class="mf-hint">
                            Isi minimal salah satu: ketik nama desa/wilayah, pilih Router/NAS, <strong>atau</strong> pilih ODP spesifik.
                            Sistem akan mencocokkan dengan alamat pelanggan, profil router, dan ODP yang terpilih.
                        </p>
                        <div class="mf-field">
                            <label class="mf-label">
                                Kata Kunci Wilayah
                                <span class="badge badge-info badge-sm" style="font-size:.7rem;padding:.2rem .45rem;border-radius:6px;">Cara Cepat</span>
                            </label>
                            <input type="text" id="customAreasInput" class="mf-input"
                                   placeholder="Contoh: Desa Semayu, Kel. Wonoroto (Enter atau koma untuk tambah)">
                            <span class="mf-hint">Dicocokkan dengan field alamat pelanggan. Bisa lebih dari satu.</span>
                            <div id="customAreasTags" class="mt-1"></div>
                            <div id="hiddenCustomAreas"></div>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Router / NAS <span class="mf-opt">Opsional — semua pelanggan di router ini</span></label>
                            <select name="nas_ids[]" class="mf-input select2-nas" multiple style="width:100%;height:auto;">
                                @foreach($nasConnections as $nas)
                                    <option value="{{ $nas->id }}">{{ $nas->name }} ({{ $nas->host }})</option>
                                @endforeach
                            </select>
                            <span class="mf-hint">Pilih router untuk menyertakan semua pelanggan aktif di router tersebut.</span>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">ODP Spesifik <span class="mf-opt">Opsional — jika ingin lebih presisi</span></label>
                            <select name="odp_ids[]" class="mf-input select2-odp" multiple
                                    data-placeholder="Cari ODP..." style="width:100%;height:auto;">
                            </select>
                            <span class="mf-hint">Kosongkan jika sudah mengisi kata kunci wilayah atau router di atas.</span>
                        </div>
                        <div id="affectedPreview" class="alert alert-info d-none">
                            <i class="fas fa-users"></i> <span id="affectedCount">0</span> pelanggan aktif terdampak
                            <span id="affectedSamples" class="text-muted small ml-2"></span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mf-footer" style="margin-top:.25rem;">
                    <a href="{{ route('outages.index') }}" class="mf-btn-cancel">Batal</a>
                    <button type="submit" class="mf-btn-submit" id="submitBtn">
                        <i class="fas fa-broadcast-tower mr-1"></i> Buat Insiden &amp; Notifikasi
                    </button>
                </div>
            </form>
        </div>

        {{-- Sidebar --}}
        <div style="display:flex;flex-direction:column;gap:1rem;align-self:start;">
            {{-- Panduan --}}
            <div class="mf-section">
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="mf-section-title">Panduan</div>
                </div>
                <div class="mf-section-body" style="font-size:.82rem;color:var(--app-text-soft,#5b6b83);gap:.65rem;">
                    <p style="margin:0;"><strong style="color:var(--app-text,#0f172a);">Cara cepat:</strong> Ketik nama desa/kampung/jalan yang tertera di alamat pelanggan. Sistem otomatis cocokkan tanpa perlu pilih ODP satu per satu.</p>
                    <p style="margin:0;"><strong style="color:var(--app-text,#0f172a);">Router/NAS:</strong> Pilih router untuk menyertakan semua pelanggan aktif di router tersebut berdasarkan profile group.</p>
                    <p style="margin:0;"><strong style="color:var(--app-text,#0f172a);">ODP (opsional):</strong> Tambahkan jika ingin lebih presisi, misalnya gangguan hanya di 1-2 ODP spesifik.</p>
                    <p style="margin:0;"><strong style="color:var(--app-text,#0f172a);">Kombinasi:</strong> Bisa pakai keduanya. Sistem ambil pelanggan yang cocok di salah satu (OR).</p>
                    <p style="margin:0;"><strong style="color:var(--app-text,#0f172a);">Notifikasi WA:</strong> Pesan otomatis berisi link halaman status perbaikan yang bisa dipantau pelanggan secara real-time.</p>
                </div>
            </div>

            {{-- Notifikasi WhatsApp --}}
            <div class="mf-section">
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="mf-section-title">Notifikasi WhatsApp</div>
                </div>
                <div class="mf-section-body">
                    <label class="mf-switch">
                        <input type="checkbox" name="send_wa_blast" value="1" id="sendWaBlast" checked>
                        <span class="mf-switch-track"></span>
                        <span class="mf-switch-label">Kirim notifikasi WA ke pelanggan terdampak</span>
                    </label>
                    <label class="mf-switch" style="margin-left:1.5rem;">
                        <input type="checkbox" name="include_status_link" value="1" id="includeStatusLink" checked>
                        <span class="mf-switch-track"></span>
                        <span class="mf-switch-label" style="font-size:.8rem;font-weight:500;">Sertakan link <em>Pantau status perbaikan</em> dalam pesan</span>
                    </label>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnTestBlast">
                            <i class="fas fa-vial"></i> Test Kirim Pesan
                        </button>
                        <small class="text-muted ml-1">Kirim pesan contoh ke 1 nomor sebelum broadcast</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

{{-- Modal Test Blast --}}
<div class="modal fade" id="modalTestBlast" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-vial text-success"></i> Test Kirim Pesan WA</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Kirim pesan <strong>contoh</strong> ke nomor berikut untuk memastikan WA Gateway berjalan normal sebelum broadcast ke seluruh pelanggan.
                </p>
                <div class="form-group mb-2">
                    <label>Nomor Tujuan Test</label>
                    <input type="text" id="testBlastPhone" class="form-control"
                           value="{{ $testBlastPhone }}"
                           placeholder="628xxxxxxxxxx">
                    <small class="text-muted">Default: nomor bisnis dari Pengaturan Tenant.</small>
                </div>
                <div id="testBlastResult" class="d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success" id="btnDoTestBlast">
                    <i class="fab fa-whatsapp"></i> Kirim Test
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Select2 untuk ODP dan NAS — destroy dulu jika sudah di-init oleh global layout
$(function() {
    const $odp = $('.select2-odp');
    if ($odp.hasClass('select2-hidden-accessible')) {
        $odp.select2('destroy');
    }
    $odp.select2({
        theme: 'bootstrap4',
        width: '100%',
        ajax: {
            url: '{{ route('odps.autocomplete') }}',
            dataType: 'json',
            delay: 250,
            data: params => ({search: params.term || ''}),
            processResults: data => ({
                results: (data.data || data).map(o => ({id: o.id, text: o.name + (o.area ? ' – '+o.area : '')}))
            }),
        },
        minimumInputLength: 0,
        placeholder: 'Cari atau pilih ODP...',
    }).on('change', updatePreview);

    const $nas = $('.select2-nas');
    if ($nas.hasClass('select2-hidden-accessible')) {
        $nas.select2('destroy');
    }
    $nas.select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Semua Router',
        allowClear: true,
    }).on('change', updatePreview);
});

// Tag input untuk custom areas
const customAreasSet = new Set();
document.getElementById('customAreasInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = this.value.trim().replace(/,$/, '');
        if (val) addCustomArea(val);
        this.value = '';
    }
});
document.getElementById('customAreasInput').addEventListener('blur', function() {
    const val = this.value.trim();
    if (val) { addCustomArea(val); this.value = ''; }
});

function addCustomArea(val) {
    if (!val || customAreasSet.has(val)) return;
    customAreasSet.add(val);
    renderTags();
    updatePreview();
}
function removeCustomArea(val) {
    customAreasSet.delete(val);
    renderTags();
    updatePreview();
}
function renderTags() {
    const container = document.getElementById('customAreasTags');
    const hidden    = document.getElementById('hiddenCustomAreas');
    container.innerHTML = [...customAreasSet].map(v =>
        `<span class="badge badge-secondary mr-1 mb-1" style="font-size:.85em;padding:5px 8px">
            ${v} <a href="#" onclick="removeCustomArea('${v}');return false" class="text-white ml-1">&times;</a>
        </span>`
    ).join('');
    hidden.innerHTML = [...customAreasSet].map(v => `<input type="hidden" name="custom_areas[]" value="${v}">`).join('');
}

// Preview pelanggan terdampak
let previewTimer;
function updatePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
        const odpIds = $('.select2-odp').val() || [];
        const nasIds = $('.select2-nas').val() || [];
        const keywords = [...customAreasSet];
        if (!odpIds.length && !nasIds.length && !keywords.length) {
            document.getElementById('affectedPreview').classList.add('d-none');
            return;
        }
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        odpIds.forEach(id => formData.append('odp_ids[]', id));
        nasIds.forEach(id => formData.append('nas_ids[]', id));
        keywords.forEach(kw => formData.append('custom_areas[]', kw));

        fetch('{{ route('outages.affected-users-preview') }}', {method:'POST', body:formData})
            .then(r => r.json())
            .then(res => {
                document.getElementById('affectedCount').textContent = res.count;
                const samples = (res.samples||[]).map(s => s.name).join(', ');
                document.getElementById('affectedSamples').textContent = samples ? `(${samples}${res.count>5?', ...':''})` : '';
                document.getElementById('affectedPreview').classList.remove('d-none');
            });
    }, 400);
}

// Test Blast
document.getElementById('btnTestBlast').addEventListener('click', function() {
    document.getElementById('testBlastResult').className = 'd-none';
    $('#modalTestBlast').modal('show');
});

document.getElementById('btnDoTestBlast').addEventListener('click', function() {
    const phone = document.getElementById('testBlastPhone').value.trim();
    if (!phone) { alert('Isi nomor tujuan test.'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    const odpIds = $('.select2-odp').val() || [];
    const nasIds = $('.select2-nas').val() || [];
    const keywords = [...customAreasSet];
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('test_phone', phone);
    odpIds.forEach(id => formData.append('odp_ids[]', id));
    nasIds.forEach(id => formData.append('nas_ids[]', id));
    keywords.forEach(kw => formData.append('custom_areas[]', kw));
    formData.append('include_status_link', document.getElementById('includeStatusLink')?.checked ? '1' : '0');

    fetch('{{ route('outages.test-blast') }}', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        const box = document.getElementById('testBlastResult');
        box.className = 'mt-3 alert ' + (res.success ? 'alert-success' : 'alert-danger');
        let info = res.message;
        if (res.success && res.recipient_count !== undefined) {
            info += `<br><small class="mt-1 d-block">Estimasi penerima broadcast: <strong>${res.recipient_count} pelanggan</strong> berdasarkan area yang dipilih.</small>`;
        }
        box.innerHTML = (res.success ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + info;
    })
    .catch(() => {
        const box = document.getElementById('testBlastResult');
        box.className = 'mt-3 alert alert-danger';
        box.innerHTML = '<i class="fas fa-times-circle"></i> Terjadi kesalahan jaringan.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Kirim Test';
    });
});

// Submit
document.getElementById('createOutageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    const fd = new FormData(this);
    fd.set('send_wa_blast', document.getElementById('sendWaBlast')?.checked ? '1' : '0');
    fd.set('include_status_link', document.getElementById('includeStatusLink')?.checked ? '1' : '0');

    fetch('{{ route('outages.store') }}', {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = res.show_url;
        } else {
            alert(res.message || 'Terjadi kesalahan.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-broadcast-tower"></i> Buat Insiden & Notifikasi';
        }
    })
    .catch(() => {
        alert('Terjadi kesalahan jaringan.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-broadcast-tower"></i> Buat Insiden & Notifikasi';
    });
});
</script>
@endpush
