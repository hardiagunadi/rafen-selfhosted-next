@extends('layouts.admin')

@section('title', 'Jadwal Shift')

@section('content')
<div class="card">
    <div class="card-header">
        <ul class="nav nav-pills card-header-pills" id="shiftTabs">
            <li class="nav-item"><a class="nav-link active" href="#tab-schedule" data-toggle="pill">Jadwal</a></li>
            <li class="nav-item"><a class="nav-link" href="#tab-definitions" data-toggle="pill">Definisi Shift</a></li>
            <li class="nav-item"><a class="nav-link" href="#tab-swap" data-toggle="pill">Permintaan Tukar</a></li>
        </ul>
        <div class="card-tools d-flex align-items-center">
            <a href="{{ route('shifts.my') }}" class="btn btn-sm btn-outline-primary mr-2">
                <i class="fas fa-user-clock"></i> Jadwal Saya
            </a>
            <button id="btnSendReminder" class="btn btn-sm btn-success" title="Kirim reminder shift besok">
                <i class="fas fa-bell"></i> Kirim Reminder
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="tab-content">

            {{-- TAB 1: JADWAL MINGGUAN --}}
            <div class="tab-pane fade show active" id="tab-schedule">
                <div class="d-flex align-items-center mb-3">
                    <button id="btnPrevWeek" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-chevron-left"></i></button>
                    <strong id="weekLabel" class="mx-2"></strong>
                    <button id="btnNextWeek" class="btn btn-sm btn-outline-secondary ml-2"><i class="fas fa-chevron-right"></i></button>
                    <button id="btnToday" class="btn btn-sm btn-outline-info ml-3">Minggu Ini</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center" id="scheduleTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:140px">Pegawai</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleBody"></tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 2: DEFINISI SHIFT --}}
            <div class="tab-pane fade" id="tab-definitions">
                <button class="btn btn-sm btn-primary mb-3" id="btnAddDef">
                    <i class="fas fa-plus"></i> Tambah Shift
                </button>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="defTable">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th>Role</th>
                                <th>Warna</th>
                                <th>Aktif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="defBody">
                            <tr><td colspan="7" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 3: PERMINTAAN TUKAR --}}
            <div class="tab-pane fade" id="tab-swap">
                <div class="mb-2">
                    <select id="swapStatusFilter" class="form-control form-control-sm d-inline-block" style="width:auto;">
                        <option value="">Semua</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pemohon</th>
                                <th>Shift Asal</th>
                                <th>Target</th>
                                <th>Alasan</th>
                                <th>Status</th>
                                <th>Tgl Ajuan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="swapBody">
                            <tr><td colspan="8" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Assign Shift --}}
<div class="modal fade" id="modalAssignShift" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Assign Shift</h6><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" id="assignUserId">
                <input type="hidden" id="assignDate">
                <p><strong id="assignInfo"></strong></p>
                <div class="form-group">
                    <label>Shift</label>
                    <select id="assignShiftId" class="form-control"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-danger" id="btnDeleteAssign">Hapus</button>
                <button class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-sm btn-primary" id="btnSaveAssign">Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Definisi Shift --}}
<div class="modal fade" id="modalDefinition" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="defModalTitle">Tambah Shift</h6><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" id="defId">
                <div class="form-group"><label>Nama Shift</label><input type="text" id="defName" class="form-control" required></div>
                <div class="row">
                    <div class="col"><div class="form-group"><label>Jam Mulai</label><input type="time" id="defStart" class="form-control" required></div></div>
                    <div class="col"><div class="form-group"><label>Jam Selesai</label><input type="time" id="defEnd" class="form-control" required></div></div>
                </div>
                <div class="row">
                    <div class="col"><div class="form-group"><label>Role (opsional)</label><input type="text" id="defRole" class="form-control" placeholder="cs, noc, teknisi..."></div></div>
                    <div class="col"><div class="form-group"><label>Warna</label><input type="color" id="defColor" class="form-control" value="#3b82f6"></div></div>
                </div>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="defActive" checked><label class="form-check-label" for="defActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-primary" id="btnSaveDef">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const subUsers = @json($subUsers);
    let definitions = [];
    let scheduleData = {};
    let weekStart = moment().startOf('isoWeek');

    // ─── HELPERS ───────────────────────────────────────────────
    function weekDays() {
        const days = [];
        for (let i = 0; i < 7; i++) days.push(moment(weekStart).add(i, 'days'));
        return days;
    }
    function buildScheduleKey(userId, date) { return `${userId}_${date}`; }

    // ─── SCHEDULE ──────────────────────────────────────────────
    function renderWeekLabel() {
        const end = moment(weekStart).add(6, 'days');
        $('#weekLabel').text(weekStart.format('D MMM') + ' – ' + end.format('D MMM YYYY'));
    }

    function renderScheduleTable(schedules) {
        scheduleData = {};
        schedules.forEach(function(s) {
            const key = buildScheduleKey(s.user_id, s.schedule_date);
            if (!scheduleData[key]) scheduleData[key] = [];
            scheduleData[key].push(s);
        });

        const days = weekDays();

        // Build header
        let headHtml = '<tr><th style="width:140px">Pegawai</th>';
        days.forEach(function(d) {
            const isToday = d.isSame(moment(), 'day');
            headHtml += `<th class="${isToday ? 'table-info' : ''}">${d.format('ddd')}<br><small>${d.format('D/M')}</small></th>`;
        });
        headHtml += '</tr>';
        $('#scheduleTable thead').html(headHtml);

        // Build rows
        let bodyHtml = '';
        subUsers.forEach(function(u) {
            bodyHtml += `<tr><td class="text-left"><strong class="small">${$('<span>').text(u.nickname || u.name).html()}</strong><br><small class="text-muted">${u.role}</small></td>`;
            days.forEach(function(d) {
                const dateStr = d.format('YYYY-MM-DD');
                const key = buildScheduleKey(u.id, dateStr);
                const shifts = scheduleData[key] || [];
                let cellContent = shifts.map(function(s) {
                    return `<span class="badge d-block mb-1" style="background:${s.shift_color};color:#fff;" data-shift-id="${s.id}" title="${s.shift_name}: ${s.start_time}-${s.end_time}">${s.shift_name}</span>`;
                }).join('');
                bodyHtml += `<td class="schedule-cell" data-user="${u.id}" data-date="${dateStr}" data-name="${$('<span>').text(u.nickname || u.name).html()}" style="min-width:90px;cursor:pointer;">
                    ${cellContent}
                    <span class="text-muted add-shift-hint small" style="display:${shifts.length ? 'none':'inline'};">+</span>
                </td>`;
            });
            bodyHtml += '</tr>';
        });
        $('#scheduleBody').html(bodyHtml);
    }

    function loadSchedule() {
        const from = weekStart.format('YYYY-MM-DD');
        const to = moment(weekStart).add(6, 'days').format('YYYY-MM-DD');
        $.get('{{ route("shifts.schedule") }}', {from, to}, function(res) {
            renderScheduleTable(res.data || []);
        });
    }

    $('#btnPrevWeek').on('click', function() { weekStart.subtract(7,'days'); renderWeekLabel(); loadSchedule(); });
    $('#btnNextWeek').on('click', function() { weekStart.add(7,'days'); renderWeekLabel(); loadSchedule(); });
    $('#btnToday').on('click', function() { weekStart = moment().startOf('isoWeek'); renderWeekLabel(); loadSchedule(); });

    // Click cell to assign
    $(document).on('click', '.schedule-cell', function() {
        const userId = $(this).data('user');
        const date = $(this).data('date');
        const name = $(this).data('name');
        $('#assignUserId').val(userId);
        $('#assignDate').val(date);
        $('#assignInfo').text(`${name} — ${date}`);

        // Populate shift select
        let opts = '<option value="">— Pilih shift —</option>';
        definitions.filter(d => d.is_active).forEach(function(d) {
            opts += `<option value="${d.id}">${d.name} (${d.start_time}-${d.end_time})</option>`;
        });
        $('#assignShiftId').html(opts);
        $('#modalAssignShift').modal('show');
    });

    $('#btnSaveAssign').on('click', function() {
        const shiftId = $('#assignShiftId').val();
        if (!shiftId) return alert('Pilih shift.');
        $.post('{{ route("shifts.schedule.store") }}', {
            user_id: $('#assignUserId').val(),
            shift_definition_id: shiftId,
            schedule_date: $('#assignDate').val(),
            _token: '{{ csrf_token() }}'
        }, function(res) {
            if (res.success) { $('#modalAssignShift').modal('hide'); loadSchedule(); }
        });
    });

    // ─── DEFINITIONS ───────────────────────────────────────────
    function loadDefinitions() {
        $.get('{{ route("shifts.definitions") }}', function(res) {
            definitions = res.data || [];
            renderDefinitions();
        });
    }

    function renderDefinitions() {
        const $body = $('#defBody');
        if (!definitions.length) {
            $body.html('<tr><td colspan="7" class="text-center text-muted">Belum ada definisi shift</td></tr>');
            return;
        }
        $body.empty();
        definitions.forEach(function(d) {
            $body.append(`<tr>
                <td>${$('<span>').text(d.name).html()}</td>
                <td>${d.start_time}</td>
                <td>${d.end_time}</td>
                <td><small>${d.role || '-'}</small></td>
                <td><span style="display:inline-block;width:20px;height:20px;background:${d.color};border-radius:3px;"></span></td>
                <td>${d.is_active ? '<span class="badge badge-success">Ya</span>' : '<span class="badge badge-secondary">Tidak</span>'}</td>
                <td>
                    <button class="btn btn-xs btn-info btn-edit-def" data-def='${JSON.stringify(d)}'><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs btn-danger btn-delete-def" data-id="${d.id}"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`);
        });
    }

    $('#btnAddDef').on('click', function() {
        $('#defId').val(''); $('#defName').val(''); $('#defStart').val(''); $('#defEnd').val(''); $('#defRole').val(''); $('#defColor').val('#3b82f6'); $('#defActive').prop('checked', true);
        $('#defModalTitle').text('Tambah Shift');
        $('#modalDefinition').modal('show');
    });

    $(document).on('click', '.btn-edit-def', function() {
        const d = $(this).data('def');
        $('#defId').val(d.id); $('#defName').val(d.name); $('#defStart').val(d.start_time.slice(0,5)); $('#defEnd').val(d.end_time.slice(0,5)); $('#defRole').val(d.role || ''); $('#defColor').val(d.color); $('#defActive').prop('checked', d.is_active);
        $('#defModalTitle').text('Edit Shift');
        $('#modalDefinition').modal('show');
    });

    $(document).on('click', '.btn-delete-def', function() {
        if (!confirm('Hapus definisi shift ini?')) return;
        const id = $(this).data('id');
        $.ajax({ url: `{{ url('shifts/definitions') }}/${id}`, method: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: function(res) { if(res.success) loadDefinitions(); } });
    });

    $('#btnSaveDef').on('click', function() {
        const id = $('#defId').val();
        const data = { name: $('#defName').val(), start_time: $('#defStart').val(), end_time: $('#defEnd').val(), role: $('#defRole').val(), color: $('#defColor').val(), is_active: $('#defActive').is(':checked') ? 1 : 0, _token: '{{ csrf_token() }}' };
        const url = id ? `{{ url('shifts/definitions') }}/${id}` : '{{ route("shifts.definitions.store") }}';
        const method = id ? 'PUT' : 'POST';
        $.ajax({ url, method, data, success: function(res) { if(res.success) { $('#modalDefinition').modal('hide'); loadDefinitions(); } } });
    });

    // ─── SWAP REQUESTS ─────────────────────────────────────────
    function loadSwapRequests() {
        $.get('{{ route("shifts.swap-requests") }}', { status: $('#swapStatusFilter').val() }, function(res) {
            const $body = $('#swapBody');
            const swaps = res.data || [];
            if (!swaps.length) { $body.html('<tr><td colspan="8" class="text-center text-muted">Tidak ada permintaan tukar shift</td></tr>'); return; }
            $body.empty();
            swaps.forEach(function(s) {
                const statusMap = {pending:'badge-warning',approved:'badge-success',rejected:'badge-danger'};
                const fromInfo = s.from_schedule ? `${s.from_schedule.shift_definition?.name || '-'} (${s.from_schedule.schedule_date})` : '-';
                const targetName = s.target ? (s.target.nickname || s.target.name) : '-';
                let actions = '';
                if (s.status === 'pending') {
                    actions = `<button class="btn btn-xs btn-success btn-approve-swap" data-id="${s.id}"><i class="fas fa-check"></i></button>
                               <button class="btn btn-xs btn-danger btn-reject-swap" data-id="${s.id}"><i class="fas fa-times"></i></button>`;
                }
                $body.append(`<tr>
                    <td>#${s.id}</td>
                    <td><small>${s.requester?.nickname || s.requester?.name || '-'}</small></td>
                    <td><small>${fromInfo}</small></td>
                    <td><small>${targetName}</small></td>
                    <td><small>${s.reason || '-'}</small></td>
                    <td><span class="badge ${statusMap[s.status]||'badge-light'}">${s.status}</span></td>
                    <td><small>${s.created_at}</small></td>
                    <td>${actions}</td>
                </tr>`);
            });
        });
    }

    $(document).on('click', '.btn-approve-swap', function() {
        reviewSwap($(this).data('id'), 'approve');
    });
    $(document).on('click', '.btn-reject-swap', function() {
        reviewSwap($(this).data('id'), 'reject');
    });
    function reviewSwap(id, action) {
        $.post(`{{ url('shifts/swap-requests') }}/${id}/review`, { action, _token: '{{ csrf_token() }}' }, function(res) {
            if (res.success) loadSwapRequests();
        });
    }

    $('#swapStatusFilter').on('change', loadSwapRequests);

    // ─── REMINDER ──────────────────────────────────────────────
    $('#btnSendReminder').on('click', function() {
        if (!confirm('Kirim reminder shift besok ke semua pegawai sekarang?')) return;
        $(this).prop('disabled', true);
        $.post('{{ route("shifts.send-reminders") }}', { _token: '{{ csrf_token() }}' }, function(res) {
            alert(`Berhasil mengirim ${res.sent} reminder.`);
        }).always(function() { $('#btnSendReminder').prop('disabled', false); });
    });

    // ─── INIT ──────────────────────────────────────────────────
    renderWeekLabel();
    loadDefinitions();
    loadSchedule();

    // Load swap when tab shown
    $('a[href="#tab-swap"]').on('shown.bs.tab', loadSwapRequests);
})();
</script>
@endpush
