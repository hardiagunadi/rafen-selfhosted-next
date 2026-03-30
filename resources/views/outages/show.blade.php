@extends('layouts.admin')

@section('title', 'Gangguan #' . $outage->id)

@section('content')
@php
$statusBadge = ['open'=>'badge-danger','in_progress'=>'badge-warning','resolved'=>'badge-success'];
$statusLabel = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Selesai'];
$severityBadge = ['critical'=>'badge-danger','high'=>'badge-warning','medium'=>'badge-info','low'=>'badge-secondary'];
@endphp

<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">
            <span class="badge {{ $statusBadge[$outage->status]??'badge-light' }} mr-2">{{ $statusLabel[$outage->status]??$outage->status }}</span>
            {{ $outage->title }}
        </h1>
    </div>
    <div class="col-sm-6 text-right">
        <a href="{{ route('outages.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        @if($canManage && !$outage->isResolved())
        <a href="{{ route('outages.edit', $outage) }}" class="btn btn-sm btn-default ml-1">
            <i class="fas fa-edit"></i> Edit
        </a>
        @endif
        <a href="{{ route('outage.public-status', $outage->public_token) }}" target="_blank" class="btn btn-sm btn-outline-info ml-1">
            <i class="fas fa-external-link-alt"></i> Halaman Publik
        </a>
    </div>
</div>

<div class="row">
    {{-- Kiri: Info + Form Update + Timeline --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-body pb-2">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width:170px">Severity</th><td><span class="badge {{ $severityBadge[$outage->severity]??'badge-light' }}">{{ strtoupper($outage->severity) }}</span></td></tr>
                    <tr><th>Mulai</th><td>{{ $outage->started_at->format('d M Y H:i') }}</td></tr>
                    <tr><th>Estimasi Selesai</th><td>{{ $outage->estimated_resolved_at?->format('d M Y H:i') ?? '–' }}</td></tr>
                    @if($outage->resolved_at)
                    <tr><th>Diselesaikan</th><td>{{ $outage->resolved_at->format('d M Y H:i') }}</td></tr>
                    @endif
                    <tr><th>Teknisi</th><td>
                        {{ $outage->assignedTeknisi ? ($outage->assignedTeknisi->nickname ?? $outage->assignedTeknisi->name) : '–' }}
                        @if($canManage && !$outage->isResolved())
                        <button class="btn btn-xs btn-default ml-1" onclick="showAssignModal()"><i class="fas fa-user-edit"></i></button>
                        @endif
                    </td></tr>
                    <tr><th>Dibuat oleh</th><td>{{ $outage->createdBy?->name ?? '–' }}</td></tr>
                </table>
                @if($outage->description)
                <hr class="my-2">
                <p class="text-muted mb-0">{{ $outage->description }}</p>
                @endif
            </div>
        </div>

        {{-- Form tambah update --}}
        @if(!$outage->isResolved())
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-comment"></i> Tambah Update Progress</h6></div>
            <div class="card-body">
                <div class="form-group">
                    <textarea id="updateBody" class="form-control" rows="3" placeholder="Tulis update progress perbaikan..."></textarea>
                </div>
                <div class="row align-items-center">
                    <div class="col">
                        <input type="file" id="updateImage" accept="image/*" class="form-control-file small">
                    </div>
                    @if($canManage)
                    <div class="col-auto">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="isPublic" checked>
                            <label class="form-check-label small" for="isPublic">Tampilkan ke pelanggan</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <select id="changeStatus" class="form-control form-control-sm">
                            <option value="">– Ubah Status –</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">✅ Selesai</option>
                        </select>
                    </div>
                    @endif
                    <div class="col-auto">
                        <button class="btn btn-primary btn-sm" id="addUpdateBtn" onclick="submitUpdate()">
                            <i class="fas fa-paper-plane"></i> Kirim
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Timeline --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-history"></i> Riwayat Update</h6></div>
            <div class="card-body p-3">
                <div class="timeline" id="updateTimeline">
                    @forelse(array_reverse($outage->updates->all()) as $update)
                        @include('outages.partials.outage-update', ['update' => $update])
                    @empty
                        <p class="text-muted text-center py-3">Belum ada update.</p>
                    @endforelse
                    <div><i class="fas fa-circle bg-gray"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kanan: Info area + pelanggan + WA blast --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-map-marker-alt text-danger"></i> Area Terdampak</h6></div>
            <div class="card-body">
                @include('outages.partials.affected-areas', ['outage' => $outage])
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-users"></i> Pelanggan Terdampak</h6></div>
            <div class="card-body text-center">
                <h2 class="text-danger">{{ $affectedCount }}</h2>
                <p class="text-muted mb-0">pelanggan aktif</p>
            </div>
        </div>

        @if($canManage)
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fab fa-whatsapp text-success"></i> Notifikasi WA</h6></div>
            <div class="card-body">
                @if($outage->wa_blast_sent_at)
                <p class="text-muted small mb-1">
                    Terakhir blast: {{ $outage->wa_blast_sent_at->format('d/m/Y H:i') }}
                    ({{ $outage->wa_blast_count }} penerima)
                </p>
                @else
                <p class="text-muted small mb-1">Belum ada WA blast.</p>
                @endif
                @if(!$outage->isResolved())
                <button class="btn btn-success btn-sm btn-block" id="blastBtn" onclick="sendBlast(false)">
                    <i class="fab fa-whatsapp"></i> Blast WA Sekarang
                </button>
                @endif
                @if($outage->resolution_wa_sent_at)
                <p class="text-success small mt-2 mb-0">
                    <i class="fas fa-check"></i> Notifikasi pemulihan terkirim {{ $outage->resolution_wa_sent_at->format('d/m/Y H:i') }}
                </p>
                @endif
                <a href="{{ route('outage.public-status', $outage->public_token) }}" target="_blank" class="btn btn-outline-info btn-sm btn-block mt-2">
                    <i class="fas fa-link"></i> Salin Link Status
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Assign Modal --}}
@if($canManage)
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Assign Teknisi</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <select id="assignTeknisiId" class="form-control">
                    <option value="">– Pilih –</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="doAssign()">Assign</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
/* ── AdminLTE timeline enhancements ── */
#updateTimeline.timeline > .ou-animate {
    animation: ou-slidein .3s ease both;
}
@keyframes ou-slidein {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* Time label chip */
.ou-time-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 2px 12px;
    font-size: .72rem;
    font-weight: 600;
}

/* Icon dot — override AdminLTE size for crispness */
#updateTimeline.timeline > div > i {
    width: 30px;
    height: 30px;
    font-size: 13px;
    line-height: 30px;
    box-shadow: 0 0 0 3px #fff, 0 2px 8px rgba(0,0,0,.12);
}

/* Timeline item card */
#updateTimeline .timeline-item {
    border-radius: 10px;
    border: 1px solid #e8eef6;
    box-shadow: 0 1px 4px rgba(15,23,42,.05);
    transition: box-shadow .18s, transform .18s;
    margin-left: 10px;
    background: #fff;
}
#updateTimeline .timeline-item:hover {
    box-shadow: 0 5px 18px rgba(15,23,42,.1);
    transform: translateY(-1px);
}

/* Header inside card */
#updateTimeline .timeline-header {
    padding: 8px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafcff;
    border-radius: 10px 10px 0 0;
    font-size: .85rem;
}
.ou-username {
    font-weight: 700;
    font-size: .875rem;
}
.ou-internal-badge {
    font-size: .68rem;
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 1px 8px;
    font-weight: 600;
    margin-left: 4px;
}

/* Meta row (status change) */
.ou-meta-row {
    font-size: .78rem;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    padding: 5px 14px !important;
    font-style: italic;
}

/* Body text */
.ou-body-text {
    font-size: .855rem;
    color: #334155;
    line-height: 1.6;
    white-space: pre-wrap;
    padding: 8px 14px !important;
}

/* Image */
.ou-img {
    max-height: 170px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    cursor: zoom-in;
    margin-top: 4px;
    transition: transform .2s;
}
.ou-img:hover { transform: scale(1.02); }
</style>
@endpush

@push('scripts')
<script>
const outageId   = {{ $outage->id }};
const updateUrl  = '{{ route('outages.updates.store', $outage) }}';
const blastUrl   = '{{ route('outages.blast', $outage) }}';
const assignUrl  = '{{ route('outages.assign', $outage) }}';
const csrfToken  = '{{ csrf_token() }}';

function submitUpdate() {
    const body      = document.getElementById('updateBody')?.value?.trim();
    const imageFile = document.getElementById('updateImage')?.files?.[0];
    const isPublic  = document.getElementById('isPublic')?.checked ?? true;
    const status    = document.getElementById('changeStatus')?.value;

    if (!body && !imageFile && !status) {
        alert('Isi catatan, upload foto, atau ubah status.');
        return;
    }

    const fd = new FormData();
    fd.append('_token', csrfToken);
    fd.append('_method', 'POST');
    if (body)      fd.append('body', body);
    if (imageFile) fd.append('image', imageFile);
    fd.append('is_public', isPublic ? '1' : '0');
    if (status)    fd.append('status', status);

    const btn = document.getElementById('addUpdateBtn');
    btn.disabled = true;

    fetch(updateUrl, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (res.new_status && ['resolved'].includes(res.new_status)) {
                    window.location.reload();
                    return;
                }
                if (res.update) {
                    prependUpdate(res.update);
                }
                document.getElementById('updateBody').value = '';
                if (document.getElementById('updateImage')) document.getElementById('updateImage').value = '';
                if (document.getElementById('changeStatus')) document.getElementById('changeStatus').value = '';
            } else {
                alert(res.message || 'Gagal menyimpan update.');
            }
        })
        .finally(() => { btn.disabled = false; });
}

function prependUpdate(u) {
    const timeline = document.getElementById('updateTimeline');
    const typeConfig = {
        created:       { icon: 'fas fa-plus-circle',  bg: 'bg-success' },
        status_change: { icon: 'fas fa-exchange-alt', bg: 'bg-warning' },
        note:          { icon: 'fas fa-comment-dots', bg: 'bg-info'    },
        resolved:      { icon: 'fas fa-check-circle', bg: 'bg-success' },
        assigned:      { icon: 'fas fa-user-check',   bg: 'bg-primary' },
    };
    const colorMap = {
        created: 'text-success', status_change: 'text-warning',
        note: 'text-info', resolved: 'text-success', assigned: 'text-primary',
    };
    const cfg   = typeConfig[u.type] || { icon: 'fas fa-info-circle', bg: 'bg-secondary' };
    const color = colorMap[u.type] || 'text-muted';
    const label = `<div class="ou-animate time-label"><span class="ou-time-label"><i class="fas fa-clock"></i> ${u.created_at}</span></div>`;
    const item  = `
    <div class="ou-animate">
        <i class="${cfg.icon} ${cfg.bg}"></i>
        <div class="timeline-item">
            <div class="timeline-header d-flex align-items-center flex-wrap gap-1">
                <span class="ou-username ${color}">${u.user_name}</span>
                ${!u.is_public ? '<span class="ou-internal-badge"><i class="fas fa-eye-slash fa-xs"></i> internal</span>' : ''}
            </div>
            ${u.meta ? `<div class="timeline-body ou-meta-row"><i class="fas fa-arrow-right fa-xs mr-1"></i>${u.meta}</div>` : ''}
            ${u.body ? `<div class="timeline-body ou-body-text">${u.body}</div>` : ''}
            ${u.image_url ? `<div class="timeline-body"><a href="${u.image_url}" target="_blank"><img src="${u.image_url}" class="ou-img" alt="Foto update"></a></div>` : ''}
        </div>
    </div>`;
    timeline.insertAdjacentHTML('afterbegin', label + item);
}

function sendBlast(force) {
    if (!force && !confirm('Kirim notifikasi WA ke semua pelanggan terdampak?')) return;
    const btn = document.getElementById('blastBtn');
    if (btn) btn.disabled = true;
    fetch(blastUrl + (force ? '?force=1' : ''), {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(`WA blast dikirim ke ${res.recipients} pelanggan.`);
            location.reload();
        } else if (res.message && res.message.includes('30 menit')) {
            if (confirm(res.message + '\n\nPaksa kirim sekarang?')) sendBlast(true);
            else if (btn) btn.disabled = false;
        } else {
            alert(res.message || 'Gagal mengirim blast.');
            if (btn) btn.disabled = false;
        }
    });
}

function showAssignModal() {
    fetch('{{ route('ppp-users.autocomplete') }}?role=teknisi', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .catch(() => {});
    // Load teknisi list
    const select = document.getElementById('assignTeknisiId');
    select.innerHTML = '<option value="">– Pilih –</option>';
    @foreach(\App\Models\User::where('parent_id', $outage->owner_id)->where('role','teknisi')->orderBy('name')->get(['id','name','nickname']) as $t)
    select.innerHTML += '<option value="{{ $t->id }}">{{ $t->nickname ?? $t->name }}</option>';
    @endforeach
    $('#assignModal').modal('show');
}

function doAssign() {
    const id = document.getElementById('assignTeknisiId').value;
    if (!id) return;
    fetch(assignUrl, {
        method:'POST',
        body: JSON.stringify({assigned_teknisi_id: id}),
        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert(res.message || 'Gagal assign teknisi.');
    });
}
</script>
@endpush
