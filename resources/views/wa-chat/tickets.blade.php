@extends('layouts.admin')

@section('title', 'Tiket Pengaduan')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Tiket Pengaduan</h3>
        <div class="d-flex align-items-center" style="gap:.5rem;flex-wrap:wrap;">
            <select id="filterStatus" class="form-control form-control-sm" style="width:auto;">
                <option value="">Semua Status</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>
            <select id="filterType" class="form-control form-control-sm" style="width:auto;">
                <option value="">Semua Tipe</option>
                <option value="complaint">Komplain</option>
                <option value="troubleshoot">Troubleshoot</option>
                <option value="installation">Instalasi</option>
                <option value="other">Lainnya</option>
            </select>
            @if(!auth()->user()->isTeknisi())
            <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalCreateTicket">
                <i class="fas fa-plus"></i> Buat Tiket
            </button>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="ticketTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Judul</th>
                        <th>Tipe</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Pelanggan</th>
                        <th>Teknisi</th>
                        <th>Tgl Buat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="ticketBody">
                    <tr><td colspan="9" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small id="ticketTotal" class="text-muted"></small>
        <div id="ticketPagination"></div>
    </div>
</div>
@endsection

{{-- Modal Buat Tiket Manual --}}
@if(!auth()->user()->isTeknisi())
<div class="modal fade" id="modalCreateTicket" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-success mr-1"></i> Buat Tiket Pengaduan</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="formCreateTicket" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    {{-- Pilih Pelanggan --}}
                    <div class="form-group">
                        <label class="font-weight-bold">Pelanggan <span class="text-danger">*</span></label>
                        <select id="selectCustomer" name="customer_id" class="form-control" style="width:100%;">
                        </select>
                        <small class="text-muted">Ketik nama, username, atau nomor HP pelanggan PPP.</small>
                    </div>

                    {{-- Info pelanggan terpilih --}}
                    <div id="customerInfo" class="alert alert-light border d-none mb-3" style="font-size:.85rem;">
                        <div class="row">
                            <div class="col-6"><strong>Nama:</strong> <span id="ciName">-</span></div>
                            <div class="col-6"><strong>Username:</strong> <span id="ciUsername">-</span></div>
                            <div class="col-6 mt-1"><strong>No. HP:</strong> <span id="ciPhone">-</span></div>
                            <div class="col-6 mt-1"><strong>ID Pelanggan:</strong> <span id="ciId">-</span></div>
                            <div class="col-12 mt-1"><strong>Alamat:</strong> <span id="ciAlamat">-</span></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label class="font-weight-bold">Judul Tiket <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Contoh: Internet mati sejak pagi" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="font-weight-bold">Tipe</label>
                            <select name="type" class="form-control" required>
                                <option value="complaint">Komplain</option>
                                <option value="troubleshoot">Troubleshoot</option>
                                <option value="installation">Instalasi</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Deskripsi / Catatan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Detail keluhan atau informasi tambahan..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="font-weight-bold">Prioritas</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-8">
                            <label class="font-weight-bold">Lampiran Foto <small class="text-muted">(opsional, max 5MB)</small></label>
                            <input type="file" name="image" class="form-control-file" accept="image/*">
                        </div>
                    </div>

                    <div id="createTicketResult" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitTicket">
                        <i class="fas fa-save"></i> Simpan Tiket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
(function() {
    let currentPage = 1;

    function statusBadge(s) {
        const map = {open:'badge-success', in_progress:'badge-warning', resolved:'badge-secondary', closed:'badge-dark'};
        return `<span class="badge ${map[s] || 'badge-light'}">${s.replace('_',' ')}</span>`;
    }
    function priorityBadge(p) {
        const map = {low:'badge-light', normal:'badge-info', high:'badge-danger'};
        return `<span class="badge ${map[p] || 'badge-light'}">${p}</span>`;
    }
    function typeBadge(t) {
        const map = {complaint:'Komplain', troubleshoot:'Troubleshoot', installation:'Instalasi', other:'Lainnya'};
        return map[t] || t;
    }

    function loadTickets(page) {
        page = page || 1;
        currentPage = page;
        $.get('{{ route("wa-tickets.datatable") }}', {
            status: $('#filterStatus').val(),
            type: $('#filterType').val(),
            page: page,
        }, function(res) {
            const $body = $('#ticketBody');
            $body.empty();
            if (!res.data || !res.data.length) {
                $body.html('<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada tiket</td></tr>');
                return;
            }
            res.data.forEach(function(t) {
                const unreadBadge = t.has_unread_update
                    ? ' <span class="badge badge-warning" title="Ada update dari teknisi"><i class="fas fa-bell"></i></span>'
                    : '';
                const rowClass = t.has_unread_update ? 'table-warning' : '';
                $body.append(`<tr class="${rowClass}">
                    <td><small>#${t.id}</small></td>
                    <td>${$('<span>').text(t.title).html()}${unreadBadge}</td>
                    <td><small>${typeBadge(t.type)}</small></td>
                    <td>${priorityBadge(t.priority)}</td>
                    <td>${statusBadge(t.status)}</td>
                    <td><small>${$('<span>').text(t.contact).html()}</small></td>
                    <td><small>${$('<span>').text(t.assigned_to).html()}</small></td>
                    <td><small>${t.created_at}</small></td>
                    <td>
                        <a href="${t.actions_url}" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>`);
            });
            $('#ticketTotal').text(`Total: ${res.total} tiket`);

            // Pagination
            let pages = '';
            for (let i = 1; i <= res.last_page; i++) {
                pages += `<button class="btn btn-xs ${i == res.current_page ? 'btn-primary' : 'btn-outline-secondary'} mr-1 page-btn" data-page="${i}">${i}</button>`;
            }
            $('#ticketPagination').html(pages);
        });
    }

    $(document).on('click', '.page-btn', function() { loadTickets($(this).data('page')); });
    $('#filterStatus, #filterType').on('change', function() { loadTickets(1); });

    loadTickets();

    // ── Modal Buat Tiket ──────────────────────────────────────────
    @if(!auth()->user()->isTeknisi())

    // Select2 untuk pilih pelanggan
    // Fix: cegah klik di dropdown Select2 menutup modal Bootstrap
    $(document).on('mousedown', '.select2-container--open .select2-dropdown', function(e) {
        e.stopPropagation();
    });

    $(function() {
        $('#selectCustomer').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalCreateTicket'),
            placeholder: 'Ketik nama, username, atau nomor HP...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("wa-tickets.customer-autocomplete") }}',
                dataType: 'json',
                delay: 300,
                data: params => ({ q: params.term }),
                processResults: data => ({
                    results: data.map(c => ({
                        id: c.id,
                        text: c.text,
                        data: c,
                    }))
                }),
            },
        }).on('select2:select', function(e) {
            const c = e.params.data.data;
            $('#ciName').text(c.customer_name || '-');
            $('#ciUsername').text(c.username || '-');
            $('#ciPhone').text(c.nomor_hp || '-');
            $('#ciId').text(c.customer_id || '-');
            $('#ciAlamat').text(c.alamat || '-');
            $('#customerInfo').removeClass('d-none');
        }).on('select2:clear', function() {
            $('#customerInfo').addClass('d-none');
        });

        // Reset modal saat ditutup
        $('#modalCreateTicket').on('hidden.bs.modal', function() {
            $('#formCreateTicket')[0].reset();
            $('#selectCustomer').val(null).trigger('change');
            $('#customerInfo').addClass('d-none');
            $('#createTicketResult').addClass('d-none').html('');
        });
    });

    // Submit tiket
    $('#formCreateTicket').on('submit', function(e) {
        e.preventDefault();

        const customerId = $('#selectCustomer').val();
        if (!customerId) {
            window.AppAjax.showToast('Pilih pelanggan terlebih dahulu.', 'warning');
            return;
        }

        const btn = $('#btnSubmitTicket');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

        const fd = new FormData(this);
        fd.set('customer_type', 'ppp');

        fetch('{{ route("wa-tickets.store") }}', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.AppAjax.showToast('Tiket berhasil dibuat.', 'success');
                $('#modalCreateTicket').modal('hide');
                loadTickets(1);
                if (res.ticket_id) {
                    // Tawarkan langsung buka tiket
                    setTimeout(() => {
                        if (confirm('Tiket #' + res.ticket_id + ' berhasil dibuat. Buka detail tiket sekarang?')) {
                            window.location.href = '{{ url("/wa-tickets") }}/' + res.ticket_id;
                        }
                    }, 300);
                }
            } else {
                window.AppAjax.showToast(res.message || 'Gagal membuat tiket.', 'danger');
            }
        })
        .catch(() => window.AppAjax.showToast('Terjadi kesalahan jaringan.', 'danger'))
        .finally(() => {
            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Tiket');
        });
    });

    @endif
})();
</script>
@endpush

