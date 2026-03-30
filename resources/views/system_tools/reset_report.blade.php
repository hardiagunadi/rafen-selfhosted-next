@extends('layouts.admin')

@section('title', 'Reset Laporan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Reset Laporan Invoice</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Fitur ini akan <strong>menghapus semua data invoice</strong> pada bulan dan tahun yang dipilih.
                    Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                </p>

                <form id="reset-report-form">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bulan</label>
                                <select name="month" class="form-control">
                                    @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create(null, $m)->format('F') }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun</label>
                                <select name="year" class="form-control">
                                    @foreach(range(date('Y'), 2020, -1) as $y)
                                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-block">
                        <i class="fas fa-trash-alt mr-1"></i>Reset Laporan Periode Ini
                    </button>
                </form>

                <div id="reset-report-status" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('reset-report-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var month = this.querySelector('[name=month]').value;
        var year  = this.querySelector('[name=year]').value;

        if (!confirm('Hapus semua invoice ' + year + '-' + String(month).padStart(2, '0') + '? Tindakan tidak dapat dibatalkan!')) return;

        var btn    = this.querySelector('button[type=submit]');
        var status = document.getElementById('reset-report-status');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';
        status.innerHTML = '';

        var fd = new FormData(this);

        fetch('{{ route("tools.reset-report.execute") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Reset Laporan Periode Ini';
            if (res.error) {
                status.innerHTML = '<div class="alert alert-danger">' + res.error + '</div>';
            } else {
                status.innerHTML = '<div class="alert alert-success">' + res.status + '</div>';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Reset Laporan Periode Ini';
            status.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan.</div>';
        });
    });
})();
</script>
@endsection
