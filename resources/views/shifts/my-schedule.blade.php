@extends('layouts.admin')

@section('title', 'Jadwal Shift Saya')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-user-clock"></i> Jadwal Shift Saya (14 Hari ke Depan)</h3>
        <button class="btn btn-sm btn-warning" id="btnRequestSwap">
            <i class="fas fa-exchange-alt"></i> Ajukan Tukar Shift
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        <th>Shift</th>
                        <th>Jam</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="myScheduleBody">
                    <tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Ajukan Tukar Shift --}}
<div class="modal fade" id="modalSwapRequest" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajukan Tukar Shift</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Shift yang Ingin Ditukar</label>
                    <select id="swapFromSchedule" class="form-control">
                        <option value="">— Pilih shift saya —</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tujuan (opsional)</label>
                    <input type="text" id="swapTarget" class="form-control" placeholder="Nama rekan yang dituju (opsional)">
                </div>
                <div class="form-group">
                    <label>Alasan</label>
                    <textarea id="swapReason" class="form-control" rows="3" placeholder="Jelaskan alasan tukar shift..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-warning" id="btnSubmitSwap">Kirim Permintaan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    let mySchedules = [];

    function loadMySchedule() {
        const from = moment().format('YYYY-MM-DD');
        const to = moment().add(13, 'days').format('YYYY-MM-DD');
        $.get('{{ route("shifts.schedule") }}', { from, to, user_id: {{ auth()->id() }} }, function(res) {
            const $body = $('#myScheduleBody');
            const schedules = res.data || [];
            mySchedules = schedules;

            if (!schedules.length) {
                $body.html('<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada jadwal dalam 14 hari ke depan</td></tr>');
                return;
            }

            $body.empty();
            schedules.forEach(function(s) {
                const statusMap = {scheduled:'badge-secondary',confirmed:'badge-success',swapped:'badge-warning',cancelled:'badge-danger'};
                const isToday = s.schedule_date === moment().format('YYYY-MM-DD');
                const rowClass = isToday ? 'table-info' : '';
                $body.append(`<tr class="${rowClass}">
                    <td>${moment(s.schedule_date).format('DD/MM/YYYY')}</td>
                    <td>${moment(s.schedule_date).format('dddd')}</td>
                    <td>
                        <span class="badge" style="background:${s.shift_color};color:#fff;">${s.shift_name || '-'}</span>
                    </td>
                    <td><small>${s.start_time || '-'} – ${s.end_time || '-'}</small></td>
                    <td><span class="badge ${statusMap[s.status]||'badge-light'}">${s.status}</span></td>
                </tr>`);
            });

            // Populate swap schedule select
            let opts = '<option value="">— Pilih shift saya —</option>';
            schedules.forEach(function(s) {
                opts += `<option value="${s.id}">${moment(s.schedule_date).format('DD/MM/YYYY')} – ${s.shift_name || 'Shift'}</option>`;
            });
            $('#swapFromSchedule').html(opts);
        });
    }

    $('#btnRequestSwap').on('click', function() {
        $('#modalSwapRequest').modal('show');
    });

    $('#btnSubmitSwap').on('click', function() {
        const fromId = $('#swapFromSchedule').val();
        if (!fromId) return alert('Pilih shift yang ingin ditukar.');
        $.post('{{ route("shifts.swap-requests.store") }}', {
            from_schedule_id: fromId,
            reason: $('#swapReason').val(),
            _token: '{{ csrf_token() }}'
        }, function(res) {
            if (res.success) {
                $('#modalSwapRequest').modal('hide');
                alert('Permintaan tukar shift berhasil dikirim. Menunggu persetujuan admin.');
            }
        }).fail(function(xhr) {
            alert(xhr.responseJSON?.message || 'Gagal mengajukan permintaan.');
        });
    });

    loadMySchedule();
})();
</script>
@endpush
