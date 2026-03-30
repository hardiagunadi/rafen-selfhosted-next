@extends('layouts.admin')

@section('title', 'Log Pengiriman WA')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Log Pengiriman WA</h4>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs px-3 pt-2" id="waTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-keluar" data-toggle="tab" href="#pane-keluar" role="tab">
                    <i class="fas fa-paper-plane mr-1"></i> WA Keluar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-masuk" data-toggle="tab" href="#pane-masuk" role="tab">
                    <i class="fas fa-inbox mr-1"></i> WA Masuk
                </a>
            </li>
        </ul>

        <div class="tab-content">
            {{-- ── Tab Keluar ──────────────────────────────────────────────────── --}}
            <div class="tab-pane fade show active p-3" id="pane-keluar" role="tabpanel">
                <div class="d-flex flex-wrap mb-2" style="gap:.5rem;">
                    <select id="filter-keluar-event" class="form-control form-control-sm" style="width:160px;">
                        <option value="">Semua Event</option>
                        <option value="blast">Blast</option>
                        <option value="registration">Registrasi</option>
                        <option value="invoice_created">Invoice Terbit</option>
                        <option value="invoice_paid">Invoice Lunas</option>
                    </select>
                    <select id="filter-keluar-status" class="form-control form-control-sm" style="width:140px;">
                        <option value="">Semua Status</option>
                        <option value="sent">Terkirim</option>
                        <option value="skip">Skip</option>
                        <option value="failed">Gagal</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table id="table-keluar" class="table table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Event</th>
                                <th>Dikirim Oleh</th>
                                <th>Nomor</th>
                                <th>Pelanggan</th>
                                <th>Invoice</th>
                                <th style="width:80px;">Status</th>
                                <th>Alasan / Ref</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- ── Tab Masuk ───────────────────────────────────────────────────── --}}
            <div class="tab-pane fade p-3" id="pane-masuk" role="tabpanel">
                <div class="d-flex flex-wrap mb-2" style="gap:.5rem;">
                    <select id="filter-masuk-event" class="form-control form-control-sm" style="width:160px;">
                        <option value="">Semua Tipe</option>
                        <option value="message">Pesan Masuk</option>
                        <option value="auto_reply">Pesan Keluar Bot</option>
                        <option value="session">Session</option>
                        <option value="status">Status</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table id="table-masuk" class="table table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Tipe</th>
                                <th>Session / Device</th>
                                <th>Pengirim</th>
                                <th>Pesan</th>
                                <th>Status</th>
                                <th style="width:60px;">Payload</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal detail pesan --}}
<div class="modal fade" id="modal-pesan" tabindex="-1" role="dialog" aria-labelledby="modal-pesan-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-pesan-title">Detail Pesan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <small class="text-muted" id="modal-pesan-meta"></small>
                </div>
                <pre id="modal-pesan-body" style="white-space:pre-wrap;word-break:break-word;font-size:.85rem;background:#f8f9fa;padding:1rem;border-radius:4px;max-height:60vh;overflow-y:auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var eventLabelKeluar = {
        blast: 'Blast',
        registration: 'Registrasi',
        invoice_created: 'Invoice Terbit',
        invoice_paid: 'Invoice Lunas',
    };
    var eventLabelMasuk = {
        message: 'Pesan Masuk',
        auto_reply: 'Keluar Bot',
        session: 'Session',
        status: 'Status',
    };

    function statusBadgeKeluar(status) {
        if (status === 'sent')   return '<span class="badge badge-success">Terkirim</span>';
        if (status === 'skip')   return '<span class="badge badge-warning">Skip</span>';
        if (status === 'failed') return '<span class="badge badge-danger">Gagal</span>';
        return '<span class="badge badge-secondary">' + status + '</span>';
    }

    function esc(s) { return $.fn.dataTable.render.text().display(s); }

    function showPesanModal(title, meta, body) {
        document.getElementById('modal-pesan-title').textContent = title;
        document.getElementById('modal-pesan-meta').textContent = meta;
        document.getElementById('modal-pesan-body').textContent = body || '(tidak ada teks pesan tersimpan)';
        $('#modal-pesan').modal('show');
    }

    var tableKeluar = null;
    var tableMasuk  = null;

    function initKeluar() {
        if (tableKeluar) return;
        tableKeluar = $('#table-keluar').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.wa-pengiriman.keluar.datatable") }}',
                data: function (d) {
                    d.status = $('#filter-keluar-status').val();
                    d.event  = $('#filter-keluar-event').val();
                },
            },
            columns: [
                { data: 'created_at', width: '145px' },
                { data: 'event', render: function(d) { return eventLabelKeluar[d] || d; } },
                { data: 'sent_by', render: function(d) {
                    return '<span class="text-info small">' + esc(d) + '</span>';
                }},
                { data: 'phone', render: function(d, t, row) {
                    if (!row.message) return esc(d);
                    return '<a href="#" class="btn-detail-pesan text-primary" style="cursor:pointer;text-decoration:underline dotted;" '
                         + 'data-phone="' + esc(d) + '" '
                         + 'data-meta="' + esc(row.created_at + ' • ' + (row.sent_by || '') + ' → ' + d) + '" '
                         + 'data-message="' + esc(row.message) + '">'
                         + esc(d) + '</a>';
                }},
                { data: 'customer_name', render: function(d, t, row) {
                    return esc(d) + (row.username && row.username !== '-'
                        ? '<br><small class="text-muted">' + esc(row.username) + '</small>' : '');
                }},
                { data: 'invoice_number' },
                { data: 'status', render: statusBadgeKeluar, orderable: false },
                { data: 'reason', render: function(d, t, row) {
                    if (row.status === 'sent') {
                        return row.ref_id && row.ref_id !== '-'
                            ? '<span class="text-muted small">ref: ' + esc(row.ref_id) + '</span>'
                            : '<span class="text-muted small">fire-and-forget</span>';
                    }
                    return '<span class="text-danger small">' + esc(d) + '</span>';
                }, orderable: false },
            ],
            pageLength: 25, stateSave: false,
            order: [[0, 'desc']],
        });

        $('#filter-keluar-status, #filter-keluar-event').on('change', function () { tableKeluar.ajax.reload(); });

        $('#table-keluar').on('click', '.btn-detail-pesan', function (e) {
            e.preventDefault();
            var meta    = $(this).data('meta');
            var message = $(this).data('message');
            showPesanModal('Teks Pesan Terkirim', meta, message);
        });
    }

    function initMasuk() {
        if (tableMasuk) return;
        tableMasuk = $('#table-masuk').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("logs.wa-pengiriman.masuk.datatable") }}',
                data: function (d) {
                    d.event_type = $('#filter-masuk-event').val();
                },
            },
            columns: [
                { data: 'created_at', width: '145px' },
                { data: 'event_type', render: function(d) {
                    var label = eventLabelMasuk[d] || d;
                    var cls = d === 'message' ? 'badge-primary'
                            : d === 'auto_reply' ? 'badge-info'
                            : d === 'session' ? 'badge-success'
                            : d === 'status' ? 'badge-warning'
                            : 'badge-secondary';
                    return '<span class="badge ' + cls + '">' + label + '</span>';
                }},
                { data: 'session_id', render: function(d) { return '<span class="text-muted small">' + esc(d) + '</span>'; } },
                { data: 'sender', render: function(d, t, row) {
                    var hasMsg = row.message && row.message !== '-';
                    var hasPayload = row.has_payload;
                    if (!hasMsg && !hasPayload) return esc(d);
                    return '<a href="#" class="btn-detail-masuk text-primary" style="cursor:pointer;text-decoration:underline dotted;" '
                         + 'data-meta="' + esc(row.created_at + ' • ' + (row.event_type === 'auto_reply' ? 'bot → ' : 'dari ') + d) + '" '
                         + 'data-message="' + esc(row.message && row.message !== '-' ? row.message : '') + '" '
                         + 'data-payload="' + esc(row.payload_preview || '') + '">'
                         + esc(d) + '</a>';
                }},
                { data: 'message', render: function(d, t, row) {
                    if (!d || d === '-') {
                        if (row.media_type) {
                            return '<span class="badge badge-light border"><i class="fas fa-paperclip mr-1"></i>' + esc(row.media_type) + '</span>';
                        }
                        return '<span class="text-muted">-</span>';
                    }
                    var short = d.length > 100 ? d.substring(0, 100) + '…' : d;
                    return '<span class="text-muted small">' + esc(short) + '</span>';
                }},
                { data: 'status', render: function(d) {
                    return d && d !== '-' ? '<span class="badge badge-secondary">' + esc(d) + '</span>' : '-';
                }, orderable: false },
                { data: 'has_payload', render: function(d, t, row) {
                    if (!d) return '-';
                    return '<a href="#" class="btn-detail-masuk text-secondary" style="cursor:pointer;" '
                         + 'data-meta="' + esc(row.created_at + ' • raw payload') + '" '
                         + 'data-message="" '
                         + 'data-payload="' + esc(row.payload_preview || '') + '">'
                         + '<i class="fas fa-code"></i></a>';
                }, orderable: false },
            ],
            pageLength: 25, stateSave: false,
            order: [[0, 'desc']],
        });

        $('#filter-masuk-event').on('change', function () { tableMasuk.ajax.reload(); });

        $('#table-masuk').on('click', '.btn-detail-masuk', function (e) {
            e.preventDefault();
            var meta    = $(this).data('meta');
            var message = $(this).data('message');
            var payload = $(this).data('payload');
            var body = '';
            if (message) {
                body += message;
            }
            if (payload) {
                body += (body ? '\n\n--- Raw Payload ---\n' : '') + payload;
            }
            showPesanModal('Detail Pesan Masuk', meta, body || null);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initKeluar();

        $('#tab-masuk').on('shown.bs.tab', function () {
            initMasuk();
            if (tableMasuk) tableMasuk.columns.adjust().draw(false);
        });
    });
})();
</script>
@endsection
