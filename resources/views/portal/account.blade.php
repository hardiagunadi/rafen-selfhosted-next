@extends('portal.layout')

@section('title', 'Informasi Akun')

@php $tenantSettings = $pppUser->owner?->tenantSettings; @endphp

@section('content')
<h5 class="mb-3"><i class="fas fa-user-circle"></i> Informasi Akun</h5>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header bg-dark text-white"><i class="fas fa-id-card"></i> Detail Akun</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th class="pl-3" style="width:140px">ID Pelanggan</th><td>{{ $pppUser->customer_id ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Nama</th><td>{{ $pppUser->customer_name ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Username PPP</th><td>{{ $pppUser->username }}</td></tr>
                    <tr><th class="pl-3">Nomor HP</th><td>{{ $pppUser->nomor_hp ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Email</th><td>{{ $pppUser->email ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Alamat</th><td>{{ $pppUser->alamat ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Paket</th><td>{{ $pppUser->profile?->name ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Tipe Layanan</th><td>{{ $pppUser->tipe_service ?? '-' }}</td></tr>
                    <tr>
                        <th class="pl-3">Status</th>
                        <td>
                            @php $statusBadge = match($pppUser->status_akun) { 'enable'=>'success','disable'=>'secondary','isolir'=>'danger',default=>'light' }; @endphp
                            <span class="badge badge-{{ $statusBadge }}">{{ $pppUser->status_akun }}</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header bg-dark text-white"><i class="fas fa-key"></i> Ganti Password Portal</div>
            <div class="card-body">
                <div id="pwAlert"></div>
                <div class="form-group">
                    <label>Password Lama</label>
                    <input type="password" id="currentPw" class="form-control" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" id="newPw" class="form-control" autocomplete="new-password">
                    <small class="form-text text-muted">Minimal 6 karakter</small>
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <input type="password" id="confirmPw" class="form-control" autocomplete="new-password">
                </div>
                <button id="btnChangePassword" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Password
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$('#btnChangePassword').on('click', function() {
    const btn = $(this);
    const newPw = $('#newPw').val();
    const confirm = $('#confirmPw').val();

    if (newPw !== confirm) {
        $('#pwAlert').html('<div class="alert alert-danger">Konfirmasi password tidak cocok.</div>');
        return;
    }

    btn.prop('disabled', true);
    $.post('{{ route("portal.change-password") }}', {
        current_password: $('#currentPw').val(),
        new_password: newPw,
        new_password_confirmation: confirm,
        _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) {
            $('#pwAlert').html('<div class="alert alert-success">Password berhasil diubah.</div>');
            $('#currentPw, #newPw, #confirmPw').val('');
        }
    }).fail(function(xhr) {
        const msg = xhr.responseJSON?.message || 'Gagal mengubah password.';
        $('#pwAlert').html(`<div class="alert alert-danger">${msg}</div>`);
    }).always(function() { btn.prop('disabled', false); });
});
</script>
@endpush
