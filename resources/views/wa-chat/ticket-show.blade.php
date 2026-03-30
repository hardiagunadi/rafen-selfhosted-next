@extends('layouts.admin')

@section('title', 'Detail Tiket #' . $waTicket->id)

@section('content')
{{-- Callout: ada outage aktif di area pelanggan ini --}}
@if(isset($relatedOutage) && $relatedOutage)
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-broadcast-tower"></i> Gangguan Jaringan Aktif di Area Pelanggan Ini</h6>
    <p class="mb-1 small">
        <strong>{{ $relatedOutage->title }}</strong> —
        Sejak {{ $relatedOutage->started_at->format('d/m/Y H:i') }}
        @if($relatedOutage->estimated_resolved_at)
        · Estimasi: {{ $relatedOutage->estimated_resolved_at->format('d/m/Y H:i') }}
        @endif
    </p>
    <a href="{{ route('outages.show', $relatedOutage) }}" class="btn btn-sm btn-warning mr-1">
        <i class="fas fa-eye"></i> Detail Insiden
    </a>
    <a href="{{ route('outage.public-status', $relatedOutage->public_token) }}" target="_blank" class="btn btn-sm btn-outline-warning">
        <i class="fas fa-external-link-alt"></i> Halaman Publik
    </a>
</div>
@endif

<div class="row">
    {{-- Kiri: info + timeline + form catatan --}}
    <div class="col-md-8">
        {{-- Info tiket --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Tiket #{{ $waTicket->id }}</h5>
                <a href="{{ route('wa-tickets.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body pb-2">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width:150px">Judul</th><td>{{ $waTicket->title }}</td></tr>
                    <tr><th>Tipe</th><td>
                        @php $typeMap = ['complaint'=>'Komplain','troubleshoot'=>'Troubleshoot','installation'=>'Instalasi','other'=>'Lainnya']; @endphp
                        {{ $typeMap[$waTicket->type] ?? $waTicket->type }}
                    </td></tr>
                    <tr><th>Prioritas</th><td>
                        @php $pMap = ['low'=>'badge-light','normal'=>'badge-info','high'=>'badge-danger']; @endphp
                        <span class="badge {{ $pMap[$waTicket->priority] ?? 'badge-light' }}">{{ $waTicket->priority }}</span>
                    </td></tr>
                    <tr><th>Status</th><td>
                        @php $sMap = ['open'=>'badge-success','in_progress'=>'badge-warning','resolved'=>'badge-secondary','closed'=>'badge-dark']; @endphp
                        <span class="badge {{ $sMap[$waTicket->status] ?? 'badge-light' }}" id="currentStatusBadge">{{ $waTicket->status }}</span>
                    </td></tr>
                    <tr><th>Pelapor</th><td>
                        @if($waTicket->conversation)
                            <i class="fab fa-whatsapp text-success mr-1"></i>
                            {{ $waTicket->conversation->contact_name ?? $waTicket->conversation->contact_phone ?? '-' }}
                        @elseif($waTicket->manual_contact_name)
                            <i class="fas fa-user mr-1 text-muted"></i>
                            {{ $waTicket->manual_contact_name }}
                            @if($waTicket->manual_contact_phone)
                                <small class="text-muted ml-1">({{ $waTicket->manual_contact_phone }})</small>
                            @endif
                        @else
                            -
                        @endif
                    </td></tr>
                    @php $cust = $waTicket->customerModel(); @endphp
                    @if($cust)
                    <tr><th>Pelanggan</th><td>
                        <a href="{{ $waTicket->customer_type === 'ppp' ? route('ppp-users.show', $cust->id) : route('hotspot-users.show', $cust->id) }}" target="_blank">
                            <span class="badge badge-{{ $waTicket->customer_type === 'ppp' ? 'primary' : 'warning' }} mr-1">{{ strtoupper($waTicket->customer_type) }}</span>{{ $cust->customer_name }}
                        </a>
                    </td></tr>
                    @endif
                    <tr><th>Teknisi</th><td>
                        {{ $waTicket->assignedTo ? ($waTicket->assignedTo->nickname ?? $waTicket->assignedTo->name) : '-' }}
                    </td></tr>
                    <tr><th>Dibuat</th><td>{{ $waTicket->created_at->format('d/m/Y H:i') }}</td></tr>
                    @if($waTicket->resolved_at)
                    <tr><th>Diselesaikan</th><td>{{ $waTicket->resolved_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                </table>
                @if($waTicket->description)
                <hr class="my-2">
                <h6>Deskripsi</h6>
                <p class="text-muted mb-1">{{ $waTicket->description }}</p>
                @endif
                @if($waTicket->image_path)
                <hr class="my-2">
                <h6>Foto Awal</h6>
                <img src="{{ asset('storage/' . $waTicket->image_path) }}" alt="Gambar tiket"
                     class="ticket-lightbox-img"
                     style="max-width:100%;max-height:240px;border-radius:8px;cursor:zoom-in;border:1px solid #ddd;">
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card mt-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="fas fa-history mr-1"></i>Timeline Pengerjaan</h6>
            </div>
            <div class="card-body py-3" id="timelineContainer">
                @forelse($waTicket->notes as $note)
                    @include('wa-chat.partials.ticket-note', ['note' => $note])
                @empty
                <p class="text-muted small mb-0">Belum ada aktivitas.</p>
                @endforelse
            </div>
        </div>

        {{-- Panel riwayat chat WA --}}
        @if($waTicket->conversation)
        <div class="card mt-3" id="chatPanelCard">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fab fa-whatsapp text-success mr-1"></i>Riwayat Chat
                    <small class="text-muted ml-1">{{ $waTicket->conversation->contact_name ?? $waTicket->conversation->contact_phone }}</small>
                </h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnToggleChat">
                    <i class="fas fa-chevron-up" id="chatToggleIcon"></i>
                </button>
            </div>
            <div id="chatPanelBody">
                {{-- Area pesan --}}
                <div id="chatMessagesArea"
                     style="height:320px;overflow-y:auto;background:#ece5dd;padding:12px;display:flex;flex-direction:column;gap:6px;">
                    <div class="text-center text-muted small py-4" id="chatLoadingMsg">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Memuat riwayat chat...
                    </div>
                </div>
                {{-- Form kirim pesan --}}
                <div class="card-footer p-2" style="background:#f0f0f0;">
                    <form id="formChatReply" class="d-flex gap-2" style="gap:8px;">
                        @csrf
                        <input type="text" id="chatMessageInput" class="form-control form-control-sm"
                               placeholder="Ketik pesan..." maxlength="4000" autocomplete="off"
                               style="flex:1;">
                        <button type="submit" id="btnSendChat" class="btn btn-sm btn-success ml-1" style="white-space:nowrap;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Form tambah catatan --}}
        <div class="card mt-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="fas fa-comment-medical mr-1"></i>Tambah Catatan</h6>
            </div>
            <div class="card-body">
                <form id="formAddNote" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group mb-2">
                        <textarea id="noteText" name="note" class="form-control" rows="3"
                                  placeholder="Tulis catatan pengerjaan, hasil diagnosa, atau update progress..."></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="noteImage" name="image" accept="image/*">
                            <label class="custom-file-label" for="noteImage">Pilih foto bukti... (opsional)</label>
                        </div>
                        <div id="noteImagePreviewWrap" class="mt-2 d-none">
                            <img id="noteImagePreview" src="" alt="Preview"
                                 style="max-width:100%;max-height:160px;border-radius:6px;border:1px solid #ddd;cursor:zoom-in;">
                            <button type="button" id="btnRemoveNoteImage" class="btn btn-xs btn-danger mt-1 d-block">
                                <i class="fas fa-times mr-1"></i>Hapus foto
                            </button>
                        </div>
                    </div>
                    <button type="submit" id="btnAddNote" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i>Simpan Catatan
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Kanan: aksi --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Aksi</h6></div>
            <div class="card-body">
                <div class="form-group mb-2">
                    <label class="mb-1">Ubah Status</label>
                    <select id="newStatus" class="form-control form-control-sm">
                        <option value="open" @selected($waTicket->status === 'open')>Open</option>
                        <option value="in_progress" @selected($waTicket->status === 'in_progress')>In Progress</option>
                        <option value="resolved" @selected($waTicket->status === 'resolved')>Resolved</option>
                        <option value="closed" @selected($waTicket->status === 'closed')>Closed</option>
                    </select>
                    <small class="text-muted">Otomatis tersimpan saat diubah</small>
                </div>
                @if($user->role !== 'teknisi')
                <hr>
                <div class="form-group mb-1">
                    <label class="mb-1">Assign Teknisi</label>
                    <select id="assignTechnician" class="form-control form-control-sm">
                        <option value="">— Pilih Teknisi —</option>
                        @foreach(App\Models\User::where(function($q) { $q->where('id', auth()->user()->effectiveOwnerId())->orWhere('parent_id', auth()->user()->effectiveOwnerId()); })->where('role', 'teknisi')->get() as $tech)
                        <option value="{{ $tech->id }}" @selected($waTicket->assigned_to_id == $tech->id)>{{ $tech->nickname ?? $tech->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Otomatis tersimpan saat dipilih</small>
                </div>
                @endif
            </div>
        </div>

        @if($waTicket->conversation)
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Percakapan Terkait</h6></div>
            <div class="card-body p-2">
                <a href="{{ route('wa-chat.index') }}#conversation-{{ $waTicket->conversation->id }}" class="btn btn-sm btn-outline-success btn-block">
                    <i class="fab fa-whatsapp"></i> Buka Chat
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Lightbox --}}
<div class="modal fade" id="modalImgLightbox" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:90vw;">
        <div class="modal-content" style="background:transparent;border:none;box-shadow:none;">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="close position-absolute" data-dismiss="modal"
                    style="top:-12px;right:-12px;z-index:10;background:#fff;border-radius:50%;width:32px;height:32px;opacity:1;line-height:32px;padding:0;font-size:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.4);">&times;</button>
                <img id="lbImg" src="" alt="" style="max-width:88vw;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);">
                <div class="mt-2">
                    <a id="lbDownload" href="" target="_blank" class="btn btn-sm btn-light">
                        <i class="fas fa-download mr-1"></i>Buka / Unduh
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
#modalImgLightbox { background: rgba(0,0,0,.85); }

/* Timeline */
.timeline-item { display:flex; gap:12px; margin-bottom:16px; }
.timeline-item:last-child { margin-bottom:0; }
.tl-icon { flex-shrink:0; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; }
.tl-icon.created    { background:#d4edda; color:#155724; }
.tl-icon.assigned   { background:#cce5ff; color:#004085; }
.tl-icon.reassigned { background:#d6d8ff; color:#4338ca; }
.tl-icon.status_change { background:#fff3cd; color:#856404; }
.tl-icon.note       { background:#f0f0f0; color:#555; }
.tl-body { flex:1; font-size:.875rem; }
.tl-header { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.tl-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:.68rem; font-weight:600; letter-spacing:.02em; text-transform:uppercase; }
.tl-badge.created { background:#d4edda; color:#155724; }
.tl-badge.assigned { background:#cce5ff; color:#004085; }
.tl-badge.reassigned { background:#d6d8ff; color:#4338ca; }
.tl-badge.status_change { background:#fff3cd; color:#856404; }
.tl-badge.note { background:#f0f0f0; color:#555; }
.tl-body .tl-meta   { font-size:.72rem; color:#888; }
.tl-body .tl-note   { margin-top:4px; white-space:pre-wrap; }
.tl-body .tl-img    { margin-top:6px; border-radius:6px; max-width:240px; max-height:160px; cursor:zoom-in; border:1px solid #ddd; display:block; }
</style>

<script>
// Lightbox
$(document).on('click', '.ticket-lightbox-img, .tl-img', function() {
    const src = $(this).attr('src');
    $('#lbImg').attr('src', src);
    $('#lbDownload').attr('href', src);
    $('#modalImgLightbox').modal('show');
});
$('#modalImgLightbox').on('click', function(e) {
    if ($(e.target).is('#modalImgLightbox') || $(e.target).is('.modal-body')) $(this).modal('hide');
});

// Preview foto catatan
$('#noteImage').on('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        $('#noteImagePreview').attr('src', e.target.result);
        $('#noteImagePreviewWrap').removeClass('d-none');
    };
    reader.readAsDataURL(file);
    $(this).next('.custom-file-label').text(file.name);
});
$('#btnRemoveNoteImage').on('click', function() {
    $('#noteImage').val('');
    $('#noteImagePreviewWrap').addClass('d-none');
    $('#noteImagePreview').attr('src', '');
    $('#noteImage').next('.custom-file-label').text('Pilih foto bukti... (opsional)');
});

// Preview foto catatan → lightbox
$(document).on('click', '#noteImagePreview', function() {
    const src = $(this).attr('src');
    $('#lbImg').attr('src', src);
    $('#lbDownload').attr('href', src);
    $('#modalImgLightbox').modal('show');
});

// Auto-save status on change
$('#newStatus').on('change', function() {
    const status = $(this).val();
    $.ajax({
        url: '{{ route("wa-tickets.update", $waTicket) }}',
        method: 'PUT',
        data: { status, _token: '{{ csrf_token() }}' },
        success: function(res) {
            if (res.success) {
                window.AppAjax.showToast('Status diperbarui.', 'success');
                // Reload timeline untuk tampilkan entry status_change
                reloadTimeline();
            }
        },
        error: function() { window.AppAjax.showToast('Gagal memperbarui status.', 'danger'); }
    });
});

// Auto-save assign on change
$('#assignTechnician').on('change', function() {
    const id = $(this).val();
    if (!id) return;
    $.post('{{ route("wa-tickets.assign", $waTicket) }}', {
        assigned_to_id: id, _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) {
            window.AppAjax.showToast('Teknisi berhasil di-assign.', 'success');
            reloadTimeline();
        }
    }).fail(function(xhr) { window.AppAjax.showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal assign teknisi.', 'danger'); });
});

// Submit form catatan
$('#formAddNote').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    $('#btnAddNote').prop('disabled', true);
    $.ajax({
        url: '{{ route("wa-tickets.notes.store", $waTicket) }}',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                // Append note baru ke timeline
                $('#timelineContainer').append(renderTimelineNote(res.note));
                // Reset form
                $('#noteText').val('');
                $('#noteImage').val('');
                $('#noteImagePreviewWrap').addClass('d-none');
                $('#noteImagePreview').attr('src', '');
                $('#noteImage').next('.custom-file-label').text('Pilih foto bukti... (opsional)');
                window.AppAjax.showToast('Catatan berhasil disimpan.', 'success');
            }
        },
        error: function(xhr) { window.AppAjax.showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan catatan.', 'danger'); },
        complete: function() { $('#btnAddNote').prop('disabled', false); },
    });
});

function esc(str) { return $('<span>').text(str || '').html(); }

function renderTimelineNote(n) {
    const iconMap = {
        created:       ['fa-plus-circle', 'created'],
        assigned:      ['fa-user-tag',    'assigned'],
        reassigned:    ['fa-random',      'reassigned'],
        status_change: ['fa-exchange-alt','status_change'],
        note:          ['fa-comment',     'note'],
    };
    const badgeMap = {
        created:       ['Dibuat',         'created'],
        assigned:      ['Assign Baru',    'assigned'],
        reassigned:    ['Assign Ulang',   'reassigned'],
        status_change: ['Status',         'status_change'],
        note:          ['Catatan',        'note'],
    };
    const [icon, cls] = iconMap[n.type] || ['fa-circle', 'note'];
    const [badgeText, badgeCls] = badgeMap[n.type] || ['Aktivitas', 'note'];
    const imgHtml = n.image_url
        ? `<img src="${esc(n.image_url)}" class="tl-img" alt="foto">`
        : '';
    const noteHtml = n.note ? `<div class="tl-note">${esc(n.note)}</div>` : '';
    const metaHtml = n.meta ? `<span>${esc(n.meta)}</span> · ` : '';
    return `<div class="timeline-item">
        <div class="tl-icon ${cls}"><i class="fas ${icon}"></i></div>
        <div class="tl-body">
            <div class="tl-header"><strong>${esc(n.user_name)}</strong><span class="tl-badge ${badgeCls}">${esc(badgeText)}</span></div>
            ${noteHtml}${imgHtml}
            <div class="tl-meta">${metaHtml}${esc(n.created_at)}</div>
        </div>
    </div>`;
}

function reloadTimeline() {
    // Reload halaman ringan hanya untuk refresh timeline setelah status/assign change
    setTimeout(() => location.reload(), 800);
}

@if($waTicket->conversation)
// ── Chat panel ──────────────────────────────────────────────────────────────
const CHAT_HISTORY_URL = '{{ route("wa-tickets.chat.history", $waTicket) }}';
const CHAT_REPLY_URL   = '{{ route("wa-tickets.chat.reply", $waTicket) }}';
const CSRF_TOKEN       = '{{ csrf_token() }}';

let lastChatMsgId  = 0;
let chatPollTimer  = null;
let chatCollapsed  = false;

function escHtml(str) {
    return $('<span>').text(str || '').html();
}

function renderChatBubble(msg) {
    const isOut   = msg.direction === 'outbound';
    const align   = isOut ? 'flex-end' : 'flex-start';
    const bgColor = isOut ? '#dcf8c6' : '#fff';
    const sender  = msg.sender_name ? `<div style="font-size:.68rem;color:#888;margin-bottom:2px;">${escHtml(msg.sender_name)}</div>` : '';
    let body      = '';

    if (msg.media_type === 'image' && msg.media_url) {
        body = `<img src="${escHtml(msg.media_url)}" class="ticket-lightbox-img"
                     style="max-width:220px;max-height:180px;border-radius:6px;cursor:zoom-in;display:block;margin-bottom:4px;">`;
    }
    if (msg.message) {
        body += `<div style="white-space:pre-wrap;word-break:break-word;">${escHtml(msg.message)}</div>`;
    }

    return `<div style="display:flex;flex-direction:column;align-items:${align};max-width:100%;">
        <div style="max-width:80%;background:${bgColor};border-radius:8px;padding:6px 10px;box-shadow:0 1px 2px rgba(0,0,0,.15);">
            ${sender}${body}
            <div style="font-size:.65rem;color:#aaa;text-align:right;margin-top:2px;">${escHtml(msg.created_at_human)} · ${escHtml(msg.created_at_date)}</div>
        </div>
    </div>`;
}

function appendChatMessages(messages) {
    const area = $('#chatMessagesArea');
    messages.forEach(function(msg) {
        area.append(renderChatBubble(msg));
        if (msg.id > lastChatMsgId) lastChatMsgId = msg.id;
    });
    // Auto scroll ke bawah
    area.scrollTop(area[0].scrollHeight);
}

function loadChatHistory() {
    $.get(CHAT_HISTORY_URL, function(res) {
        $('#chatLoadingMsg').remove();
        if (!res.has_conversation) {
            $('#chatMessagesArea').html('<div class="text-center text-muted small py-4">Tidak ada percakapan terkait.</div>');
            $('#formChatReply').closest('.card-footer').hide();
            return;
        }
        if (res.messages.length === 0) {
            $('#chatMessagesArea').html('<div class="text-center text-muted small py-4">Belum ada pesan.</div>');
        } else {
            $('#chatMessagesArea').empty();
            appendChatMessages(res.messages);
        }
        startChatPolling();
    }).fail(function() {
        $('#chatLoadingMsg').html('<i class="fas fa-exclamation-circle mr-1"></i>Gagal memuat riwayat chat.');
    });
}

function startChatPolling() {
    if (chatPollTimer) return;
    chatPollTimer = setInterval(function() {
        if (chatCollapsed) return;
        $.get(CHAT_HISTORY_URL, { after: lastChatMsgId }, function(res) {
            if (res.messages && res.messages.length > 0) {
                // Hapus placeholder "Belum ada pesan" jika ada
                $('#chatMessagesArea .text-center').remove();
                appendChatMessages(res.messages);
            }
        });
    }, 7000);
}

// Toggle collapse panel chat
$('#btnToggleChat').on('click', function() {
    chatCollapsed = !chatCollapsed;
    if (chatCollapsed) {
        $('#chatPanelBody').slideUp(150);
        $('#chatToggleIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    } else {
        $('#chatPanelBody').slideDown(150);
        $('#chatToggleIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        // Scroll ke bawah setelah expand
        setTimeout(function() {
            const area = $('#chatMessagesArea');
            area.scrollTop(area[0].scrollHeight);
        }, 160);
    }
});

// Kirim pesan
$('#formChatReply').on('submit', function(e) {
    e.preventDefault();
    const msg = $('#chatMessageInput').val().trim();
    if (!msg) return;

    $('#btnSendChat').prop('disabled', true);
    $.ajax({
        url: CHAT_REPLY_URL,
        method: 'POST',
        data: { message: msg, _token: CSRF_TOKEN },
        success: function(res) {
            if (res.success) {
                $('#chatMessageInput').val('');
                $('#chatMessagesArea .text-center').remove();
                appendChatMessages([res.message]);
            } else {
                window.AppAjax.showToast(res.message || 'Gagal mengirim pesan.', 'danger');
            }
        },
        error: function(xhr) {
            window.AppAjax.showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal mengirim pesan.', 'danger');
        },
        complete: function() { $('#btnSendChat').prop('disabled', false); },
    });
});

// Enter untuk kirim (Shift+Enter untuk baris baru)
$('#chatMessageInput').on('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        $('#formChatReply').submit();
    }
});

// Load history saat halaman siap
loadChatHistory();
@endif
</script>
@endpush
