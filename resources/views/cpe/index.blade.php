@extends('layouts.admin')

@section('title', 'CPE Management')

@section('content')
@php
    $currentUser = auth()->user();
    $canManage = $currentUser->isSuperAdmin() || in_array($currentUser->role, ['administrator', 'noc', 'it_support'], true);
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-router mr-2"></i> CPE Management
        </h3>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs px-3 pt-2" id="cpeTab">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-linked">
                    <i class="fas fa-link mr-1 text-success"></i> Terhubung
                </a>
            </li>
            @if($canManage)
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-unlinked" id="tab-unlinked-link">
                    <i class="fas fa-unlink mr-1 text-warning"></i> Belum Terhubung
                    <span class="badge badge-warning ml-1" id="unlinked-count" style="display:none"></span>
                </a>
            </li>
            @endif
        </ul>

        <div class="tab-content px-3 pb-3 pt-2">
            {{-- Tab: Terhubung --}}
            <div class="tab-pane fade show active" id="tab-linked">
                {{-- Search bar (diisi DataTables initComplete) --}}
                <div id="cpe-filter-bar" class="mt-2 mb-2"></div>
                <div class="table-responsive">
                    <table id="cpe-table" class="table table-bordered table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Pelanggan</th>
                                <th>Username PPPoE</th>
                                <th>Pabrikan / Model</th>
                                <th>Firmware</th>
                                <th>Inform Period</th>
                                <th>Status</th>
                                <th>Terakhir Online</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- Tab: Belum Terhubung --}}
            @if($canManage)
            <div class="tab-pane fade" id="tab-unlinked">
                <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                    <small class="text-muted">Device yang sudah inform ke GenieACS tapi belum terhubung ke PPP user manapun.</small>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-success" id="btn-bulk-auto-link">
                            <i class="fas fa-magic mr-1"></i> Auto-Link Semua
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-reload-unlinked">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="unlinked-table" class="table table-bordered table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>GenieACS Device ID</th>
                                <th>Pabrikan / Model</th>
                                <th>Serial</th>
                                <th>PPPoE Username (dari modem)</th>
                                <th>Inform Period</th>
                                <th>Terakhir Inform</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="unlinked-tbody">
                            <tr><td colspan="7" class="text-center text-muted">Klik tab untuk memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal: Link ke PPP User --}}
@if($canManage)
<div class="modal fade" id="modalLinkDevice" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-link mr-1"></i> Hubungkan ke PPP User</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="link-genieacs-id">
                <div class="form-group mb-2">
                    <label class="small mb-1">GenieACS Device ID</label>
                    <input type="text" class="form-control form-control-sm" id="link-genieacs-id-display" readonly>
                </div>
                <div class="form-group mb-2">
                    <label class="small mb-1">PPPoE Username di Modem</label>
                    <input type="text" class="form-control form-control-sm" id="link-pppoe-hint" readonly>
                </div>
                <div class="form-group mb-0">
                    <label class="small mb-1">Pilih PPP User <span class="text-danger">*</span></label>
                    <select class="form-control form-control-sm" id="link-ppp-user-select">
                        <option value="">-- Ketik untuk cari --</option>
                    </select>
                    <small class="form-text text-muted">Cari berdasarkan nama pelanggan atau username PPPoE.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-do-link">
                    <i class="fas fa-link mr-1"></i> Hubungkan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Set PPPoE & Hubungkan --}}
<div class="modal fade" id="modalSetPppoe" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key mr-1"></i> Set PPPoE & Hubungkan Modem</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="setpppoe-genieacs-id">

                {{-- Info Modem --}}
                <div id="setpppoe-loading" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Mengambil informasi modem...
                </div>
                <div id="setpppoe-info" style="display:none">
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0 small"><i class="fas fa-info-circle mr-1"></i> Informasi Modem</h6>
                        </div>
                        <div class="card-body py-2 px-3">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr><td class="text-muted py-0" style="width:140px">GenieACS ID</td><td class="py-0 font-weight-bold text-monospace" id="si-genieacs-id"></td></tr>
                                <tr><td class="text-muted py-0">Serial Number</td><td class="py-0 font-weight-bold" id="si-serial"></td></tr>
                                <tr><td class="text-muted py-0">Pabrikan / Model</td><td class="py-0" id="si-model"></td></tr>
                                <tr><td class="text-muted py-0">Firmware</td><td class="py-0" id="si-firmware"></td></tr>
                                <tr><td class="text-muted py-0">IP WAN</td><td class="py-0" id="si-ip"></td></tr>
                                <tr><td class="text-muted py-0">Status PPPoE</td><td class="py-0" id="si-status"></td></tr>
                                <tr><td class="text-muted py-0">Username saat ini</td><td class="py-0" id="si-pppoe-user"></td></tr>
                                <tr><td class="text-muted py-0">Terakhir Inform</td><td class="py-0" id="si-last-inform"></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="small mb-1">Pilih PPP User <span class="text-danger">*</span></label>
                        <select class="form-control form-control-sm" id="setpppoe-ppp-user-select">
                            <option value="">-- Ketik untuk cari --</option>
                        </select>
                        <small class="form-text text-muted">Cari berdasarkan nama pelanggan atau username PPPoE.</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-1">PPPoE Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="setpppoe-username" maxlength="64" placeholder="Username PPPoE">
                    </div>
                    <div class="form-group mb-0">
                        <label class="small mb-1">PPPoE Password <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="setpppoe-password" maxlength="64" placeholder="Password PPPoE">
                    </div>
                </div>
                <div id="setpppoe-error" class="alert alert-danger py-2 small" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-do-set-pppoe" style="display:none">
                    <i class="fas fa-key mr-1"></i> Terapkan ke Modem & Hubungkan
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    var csrfToken = '{{ csrf_token() }}';

    // ── Tab: Terhubung ───────────────────────────────────────────────────────
    var table = $('#cpe-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        dom: '<"row align-items-center"<"col-sm-6"l><"col-sm-6 d-flex justify-content-end"f>>rt<"row align-items-center mt-2"<"col-sm-6"i><"col-sm-6 d-flex justify-content-end"p>>',
        ajax: { url: '{{ route('cpe.datatable') }}', type: 'GET' },
        columns: [
            {
                data: 'customer_name',
                render: function (data, type, row) {
                    if (row.ppp_user_id) {
                        return '<a href="/ppp-users/' + row.ppp_user_id + '/edit">' + data + '</a>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { data: 'username' },
            {
                data: 'manufacturer',
                render: function (data, type, row) {
                    var m = row.model !== '-' ? row.model : '';
                    return data + (m ? ' / ' + m : '');
                }
            },
            { data: 'firmware' },
            {
                data: 'inform_interval',
                render: function (data) {
                    if (!data) return '<span class="text-muted">-</span>';
                    if (data <= 300) return '<span class="badge badge-success">' + data + 's</span>';
                    if (data <= 900) return '<span class="badge badge-warning">' + data + 's</span>';
                    return '<span class="badge badge-danger">' + data + 's</span>';
                }
            },
            {
                data: 'status',
                render: function (data) {
                    var map = {
                        online:  '<span class="badge badge-success">Online</span>',
                        offline: '<span class="badge badge-danger">Offline</span>',
                        unknown: '<span class="badge badge-secondary">Tidak Diketahui</span>',
                    };
                    return map[data] || '<span class="badge badge-secondary">' + data + '</span>';
                }
            },
            { data: 'last_seen_at' },
            {
                data: 'ppp_user_id',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    var html = '<div class="text-right">';
                    if (data) {
                        html += '<a href="/ppp-users/' + data + '/edit" class="btn btn-sm btn-outline-primary" title="Detail Pelanggan"><i class="fas fa-user"></i></a> ';
                    }
                    @if($canManage)
                    html += '<button class="btn btn-sm btn-outline-warning btn-reboot" data-id="' + data + '" title="Reboot"><i class="fas fa-power-off"></i></button>';
                    @endif
                    html += '</div>';
                    return html;
                }
            },
        ],
        language: {
            search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok.',
            emptyTable: 'Belum ada perangkat CPE.',
            paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
            processing: 'Memuat...',
        },
        initComplete: function () {
            var $dtFilter = $('#cpe-table_filter');
            $dtFilter.find('label').css({'display':'flex','align-items':'center','gap':'.4rem','margin':'0'});
            $dtFilter.css({'margin':'0'});
            $('#cpe-filter-bar').append($dtFilter);
        },
    });

    @if($canManage)
    $('#cpe-table').on('click', '.btn-reboot', function () {
        var pppUserId = $(this).data('id');
        if (!pppUserId || !confirm('Yakin ingin mereboot perangkat ini?')) return;
        $.ajax({
            url: '/ppp-users/' + pppUserId + '/cpe/reboot',
            method: 'POST',
            data: { _token: csrfToken },
            success: function (res) { if (window.AppAjax) window.AppAjax.showToast(res.message, 'success'); },
            error: function (xhr) { if (window.AppAjax) window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal reboot.', 'danger'); },
        });
    });

    // ── Tab: Belum Terhubung ─────────────────────────────────────────────────
    var unlinkedLoaded = false;

    function loadUnlinked() {
        $('#unlinked-tbody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</td></tr>');
        $.get('{{ route('cpe.unlinked') }}', function (data) {
            var count = data.length;
            if (count > 0) {
                $('#unlinked-count').text(count).show();
            } else {
                $('#unlinked-count').hide();
            }

            if (count === 0) {
                $('#unlinked-tbody').html('<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-check-circle text-success mr-1"></i> Semua device sudah terhubung.</td></tr>');
                return;
            }

            var rows = '';
            data.forEach(function (d) {
                var pppHint = d.pppoe_user !== '-'
                    ? '<span class="text-danger font-weight-bold">' + d.pppoe_user + '</span><br><small class="text-muted">Menunggu sinkronisasi otomatis (5 menit) atau hubungkan manual</small>'
                    : '<span class="text-muted">-</span>';

                rows += '<tr>';
                rows += '<td><small class="text-monospace">' + d.genieacs_id + '</small></td>';
                var modelLabel = d.manufacturer + (d.model !== '-' ? ' / ' + d.model : '');
                if (d.mac_address) {
                    rows += '<td><span class="btn-model-info" style="cursor:pointer;border-bottom:1px dashed #aaa" '
                        + 'data-toggle="tooltip" data-placement="right" data-html="true" '
                        + 'title="<i class=\'fas fa-network-wired mr-1\'></i><b>MAC:</b> ' + d.mac_address + '">'
                        + modelLabel + ' <i class="fas fa-info-circle text-muted small"></i></span></td>';
                } else {
                    rows += '<td>' + modelLabel + '</td>';
                }
                rows += '<td>' + d.serial + '</td>';
                rows += '<td>' + pppHint + '</td>';
                var interval = d.inform_interval;
                var intervalBadge = interval
                    ? (interval <= 300
                        ? '<span class="badge badge-success">' + interval + 's</span>'
                        : (interval <= 900
                            ? '<span class="badge badge-warning">' + interval + 's</span>'
                            : '<span class="badge badge-danger">' + interval + 's</span>'))
                    : '<span class="text-muted">-</span>';
                rows += '<td>' + intervalBadge + '</td>';
                rows += '<td>' + d.last_inform + '</td>';
                var fetchBtn = '';
                if (d.pppoe_user === '-') {
                    fetchBtn = '<button class="btn btn-sm btn-outline-info btn-fetch-param mr-1" '
                        + 'data-id="' + d.genieacs_id + '" '
                        + 'title="Minta GenieACS mengambil parameter PPPoE dari modem">'
                        + '<i class="fas fa-download mr-1"></i>Fetch Param</button>';
                }
                rows += '<td class="text-right">'
                    + fetchBtn
                    + '<button class="btn btn-sm btn-success btn-set-pppoe mr-1" '
                    + 'data-id="' + d.genieacs_id + '" '
                    + 'title="Set PPPoE & Hubungkan ke PPP User">'
                    + '<i class="fas fa-key mr-1"></i> Set PPPoE</button>'
                    + '<button class="btn btn-sm btn-primary btn-link-device mr-1" '
                    + 'data-id="' + d.genieacs_id + '" '
                    + 'data-pppoe="' + d.pppoe_user + '" '
                    + 'title="Hubungkan ke PPP User (tanpa set PPPoE)">'
                    + '<i class="fas fa-link mr-1"></i> Hubungkan</button>'
                    + '<button class="btn btn-sm btn-danger btn-delete-unlinked" '
                    + 'data-id="' + d.genieacs_id + '" '
                    + 'title="Hapus device dari GenieACS">'
                    + '<i class="fas fa-trash"></i></button>'
                    + '</td>';
                rows += '</tr>';
            });
            $('#unlinked-tbody').html(rows);
            $('#unlinked-tbody [data-toggle="tooltip"]').tooltip();
        }).fail(function () {
            $('#unlinked-tbody').html('<tr><td colspan="7" class="text-center text-danger">Gagal memuat data.</td></tr>');
        });
    }

    // Load saat tab dibuka
    $(document).on('shown.bs.tab', 'a[href="#tab-unlinked"]', function () {
        if (!unlinkedLoaded) {
            loadUnlinked();
            unlinkedLoaded = true;
        }
    });

    $('#btn-reload-unlinked').on('click', function () {
        unlinkedLoaded = false;
        loadUnlinked();
        unlinkedLoaded = true;
    });

    // ── Auto-Link Semua ──────────────────────────────────────────────────────
    $('#btn-bulk-auto-link').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
        $.ajax({
            url: '{{ route("cpe.bulk-auto-link") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (window.AppAjax) window.AppAjax.showToast(res.message, 'success');
                unlinkedLoaded = false;
                loadUnlinked();
                unlinkedLoaded = true;
                if (res.linked > 0) {
                    table.ajax.reload();
                }
            },
            error: function (xhr) {
                if (window.AppAjax) window.AppAjax.showToast(
                    xhr.responseJSON?.message || 'Gagal menjalankan auto-link.', 'danger'
                );
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-magic mr-1"></i> Auto-Link Semua');
            },
        });
    });

    // ── Fetch Param — trigger refreshObject untuk device tanpa PPPoE username ──
    $(document).on('click', '.btn-fetch-param', function () {
        var $btn    = $(this);
        var genieId = $btn.data('id');
        var url     = '/cpe/unlinked/' + encodeURIComponent(genieId) + '/refresh-param';

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');

        $.ajax({
            url: url,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (window.AppAjax) window.AppAjax.showToast(res.message, 'success');
                // Reload setelah 3 detik agar GenieACS sempat memproses
                setTimeout(function () {
                    unlinkedLoaded = false;
                    loadUnlinked();
                    unlinkedLoaded = true;
                }, 3000);
            },
            error: function (xhr) {
                if (window.AppAjax) window.AppAjax.showToast(
                    xhr.responseJSON?.message || 'Gagal mengirim perintah refresh.', 'danger'
                );
                $btn.prop('disabled', false).html('<i class="fas fa-download mr-1"></i>Fetch Param');
            },
        });
    });

    // ── Hapus Device Belum Terhubung ─────────────────────────────────────────
    $(document).on('click', '.btn-delete-unlinked', function () {
        var $btn    = $(this);
        var genieId = $btn.data('id');
        if (!confirm('Hapus device "' + genieId + '" dari GenieACS?\n\nTindakan ini tidak dapat dibatalkan.')) return;

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '/cpe/unlinked/' + encodeURIComponent(genieId),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
            success: function (res) {
                if (window.AppAjax) window.AppAjax.showToast(res.message, 'success');
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                // Update badge count
                var remaining = $('#unlinked-tbody tr').length - 1;
                if (remaining <= 0) {
                    $('#unlinked-count').hide();
                    $('#unlinked-tbody').html('<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-check-circle text-success mr-1"></i> Semua device sudah terhubung.</td></tr>');
                } else {
                    $('#unlinked-count').text(remaining);
                }
            },
            error: function (xhr) {
                if (window.AppAjax) window.AppAjax.showToast(
                    xhr.responseJSON?.message || 'Gagal menghapus device.', 'danger'
                );
                $btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
            },
        });
    });

    // ── Modal: Link ke PPP User ──────────────────────────────────────────────
    $(document).on('click', '.btn-link-device', function () {
        var genieId = $(this).data('id');
        var pppHint = $(this).data('pppoe');
        $('#link-genieacs-id').val(genieId);
        $('#link-genieacs-id-display').val(genieId);
        $('#link-pppoe-hint').val(pppHint !== '-' ? pppHint : '(tidak terdeteksi)');
        $('#link-ppp-user-select').val('').trigger('change');
        $('#modalLinkDevice').modal('show');
    });

    // Select2 untuk pilih PPP user
    if ($.fn.select2) {
        $('#link-ppp-user-select').select2({
            dropdownParent: $('#modalLinkDevice'),
            placeholder: 'Ketik nama atau username PPPoE...',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route('cpe.search-ppp-users') }}',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results || [] }; },
            },
        });
    }

    $('#btn-do-link').on('click', function () {
        var genieId    = $('#link-genieacs-id').val();
        var pppUserId  = $('#link-ppp-user-select').val();
        if (!pppUserId) { if (window.AppAjax) window.AppAjax.showToast('Pilih PPP user terlebih dahulu.', 'warning'); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        $.ajax({
            url: '{{ route('cpe.link') }}',
            method: 'POST',
            data: { _token: csrfToken, genieacs_id: genieId, ppp_user_id: pppUserId },
            success: function (res) {
                if (window.AppAjax) window.AppAjax.showToast(res.message, 'success');
                $('#modalLinkDevice').modal('hide');
                table.ajax.reload();
                unlinkedLoaded = false;
                loadUnlinked();
                unlinkedLoaded = true;
            },
            error: function (xhr) {
                if (window.AppAjax) window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal menghubungkan.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-link mr-1"></i> Hubungkan');
            },
        });
    });

    // ── Modal: Set PPPoE & Hubungkan ─────────────────────────────────────────
    $(document).on('click', '.btn-set-pppoe', function () {
        var genieId = $(this).data('id');
        $('#setpppoe-genieacs-id').val(genieId);
        $('#setpppoe-loading').show();
        $('#setpppoe-info').hide();
        $('#setpppoe-error').hide();
        $('#btn-do-set-pppoe').hide();
        $('#setpppoe-ppp-user-select').val('').trigger('change');
        $('#setpppoe-username').val('');
        $('#setpppoe-password').val('');
        $('#modalSetPppoe').modal('show');

        // Fetch device info
        $.get('/cpe/unlinked/' + encodeURIComponent(genieId) + '/info', function (res) {
            $('#setpppoe-loading').hide();
            if (!res.success) {
                $('#setpppoe-error').text(res.message).show();
                return;
            }
            $('#si-genieacs-id').text(res.genieacs_id);
            $('#si-serial').text(res.serial);
            $('#si-model').text(res.manufacturer + (res.model !== '-' ? ' / ' + res.model : ''));
            $('#si-firmware').text(res.firmware);
            $('#si-ip').text(res.external_ip);
            var statusMap = { Connected: '<span class="badge badge-success">Connected</span>', Disconnected: '<span class="badge badge-danger">Disconnected</span>' };
            $('#si-status').html(statusMap[res.conn_status] || '<span class="badge badge-secondary">' + res.conn_status + '</span>');
            $('#si-pppoe-user').html(res.pppoe_user ? res.pppoe_user : '<span class="text-muted">(kosong)</span>');
            $('#si-last-inform').text(res.last_inform);
            $('#setpppoe-info').show();
            $('#btn-do-set-pppoe').show();
        }).fail(function (xhr) {
            $('#setpppoe-loading').hide();
            $('#setpppoe-error').text(xhr.responseJSON?.message || 'Gagal mengambil informasi modem.').show();
        });
    });

    // Select2 untuk modal Set PPPoE
    if ($.fn.select2) {
        $('#setpppoe-ppp-user-select').select2({
            dropdownParent: $('#modalSetPppoe'),
            placeholder: 'Ketik nama atau username PPPoE...',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route('cpe.search-ppp-users') }}',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results || [] }; },
            },
        });

        // Auto-fill username & password saat pilih PPP user
        $('#setpppoe-ppp-user-select').on('select2:select', function (e) {
            var d = e.params.data;
            if (d.username) $('#setpppoe-username').val(d.username);
            if (d.password) $('#setpppoe-password').val(d.password);
        });
    }

    $('#btn-do-set-pppoe').on('click', function () {
        var genieId   = $('#setpppoe-genieacs-id').val();
        var pppUserId = $('#setpppoe-ppp-user-select').val();
        var username  = $('#setpppoe-username').val().trim();
        var password  = $('#setpppoe-password').val().trim();

        if (!pppUserId) { if (window.AppAjax) window.AppAjax.showToast('Pilih PPP user terlebih dahulu.', 'warning'); return; }
        if (!username)  { if (window.AppAjax) window.AppAjax.showToast('Username PPPoE tidak boleh kosong.', 'warning'); return; }
        if (!password)  { if (window.AppAjax) window.AppAjax.showToast('Password PPPoE tidak boleh kosong.', 'warning'); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengirim...');
        $.ajax({
            url: '/cpe/unlinked/' + encodeURIComponent(genieId) + '/set-pppoe',
            method: 'POST',
            data: { _token: csrfToken, ppp_user_id: pppUserId, username: username, password: password },
            success: function (res) {
                if (window.AppAjax) window.AppAjax.showToast(res.message, 'success');
                $('#modalSetPppoe').modal('hide');
                table.ajax.reload();
                unlinkedLoaded = false;
                loadUnlinked();
                unlinkedLoaded = true;
            },
            error: function (xhr) {
                if (window.AppAjax) window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal menerapkan PPPoE.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-key mr-1"></i> Terapkan ke Modem & Hubungkan');
            },
        });
    });
    @endif
});
</script>
@endpush
