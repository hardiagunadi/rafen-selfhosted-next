@extends('layouts.admin')

@section('title', 'Reset Database')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-skull-crossbones mr-2"></i>Reset Database</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong><i class="fas fa-exclamation-triangle mr-1"></i>PERINGATAN KERAS:</strong>
                    Tindakan ini akan <strong>menghapus permanen</strong> data operasional.
                    Pastikan sudah melakukan backup sebelum melanjutkan.
                </div>

                <form id="reset-db-form">
                    @csrf
                    <div class="form-group">
                        <label>Target Reset</label>
                        <select name="tenant_id" class="form-control" id="tenant-select">
                            <option value="">Semua Data Operasional (PPP, Hotspot, Invoice)</option>
                            @foreach(\App\Models\User::tenants()->orderBy('name')->get() as $tenant)
                            <option value="{{ $tenant->id }}">Tenant: {{ $tenant->name }} ({{ $tenant->email }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Pilih tenant tertentu untuk reset data hanya milik tenant tersebut.</small>
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi — ketik <strong>HAPUS SEMUA DATA</strong> untuk melanjutkan</label>
                        <input type="text" name="confirmation" class="form-control border-danger" placeholder="HAPUS SEMUA DATA" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-bomb mr-1"></i>Eksekusi Reset
                    </button>
                </form>

                <div id="reset-db-status" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('reset-db-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var confirmation = this.querySelector('[name=confirmation]').value;
        var tenantId     = this.querySelector('[name=tenant_id]').value;
        var tenantText   = this.querySelector('[name=tenant_id] option:checked').text;

        if (confirmation !== 'HAPUS SEMUA DATA') {
            document.getElementById('reset-db-status').innerHTML =
                '<div class="alert alert-danger">Teks konfirmasi tidak sesuai.</div>';
            return;
        }

        var msg = tenantId
            ? 'Hapus semua data operasional milik ' + tenantText + '? Tidak dapat dibatalkan!'
            : 'Hapus SEMUA data operasional (semua tenant)? Tindakan ini TIDAK DAPAT DIBATALKAN!';

        if (!confirm(msg)) return;

        var btn    = this.querySelector('button[type=submit]');
        var status = document.getElementById('reset-db-status');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';
        status.innerHTML = '';

        var fd = new FormData(this);

        fetch('{{ route("tools.reset-database.execute") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bomb mr-1"></i>Eksekusi Reset';
            if (res.error) {
                status.innerHTML = '<div class="alert alert-danger">' + res.error + '</div>';
            } else {
                status.innerHTML = '<div class="alert alert-success">' + res.status + '</div>';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bomb mr-1"></i>Eksekusi Reset';
            status.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan.</div>';
        });
    });
})();
</script>
@endsection
