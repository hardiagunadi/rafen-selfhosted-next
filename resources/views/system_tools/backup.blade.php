@extends('layouts.admin')

@section('title', 'Backup & Restore Database')

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Buat Backup Baru</h5>
                <button class="btn btn-primary btn-sm" id="btn-backup">
                    <i class="fas fa-database mr-1"></i>Backup Sekarang
                </button>
            </div>
            <div class="card-body">
                <div id="backup-status"></div>
                <p class="text-muted small mb-0">
                    Backup akan menyimpan seluruh database ke file <code>.sql.gz</code> terenkripsi gzip.
                    File disimpan di server.
                </p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0 text-danger"><i class="fas fa-upload mr-1"></i>Restore dari File</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger py-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Perhatian:</strong> Restore akan menimpa seluruh data database saat ini!
                </div>
                <form id="restore-form">
                    @csrf
                    <div class="form-group">
                        <label>File Backup (.sql.gz)</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="restore-file" name="file" accept=".gz" required>
                            <label class="custom-file-label" for="restore-file">Pilih file .sql.gz...</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-undo mr-1"></i>Restore Database
                    </button>
                </form>
                <div id="restore-status" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">File Backup Tersimpan</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Dibuat</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($files as $file)
                            <tr>
                                <td><code class="small">{{ $file['name'] }}</code></td>
                                <td class="small">{{ $file['size'] }}</td>
                                <td class="small text-muted">{{ $file['modified'] }}</td>
                                <td class="text-right">
                                    <a href="{{ route('tools.backup.download', ['file' => $file['name']]) }}" class="btn btn-xs btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button class="btn btn-xs btn-outline-danger btn-delete-backup" data-file="{{ $file['name'] }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada file backup.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Create backup
    document.getElementById('btn-backup').addEventListener('click', function () {
        var btn = this;
        var status = document.getElementById('backup-status');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';
        status.innerHTML = '';

        fetch('{{ route("tools.backup.create") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-database mr-1"></i>Backup Sekarang';
            if (res.error) {
                status.innerHTML = '<div class="alert alert-danger py-2">' + res.error + '</div>';
            } else {
                status.innerHTML = '<div class="alert alert-success py-2">' + res.status + ' Reload halaman untuk melihat file baru.</div>';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-database mr-1"></i>Backup Sekarang';
            status.innerHTML = '<div class="alert alert-danger py-2">Terjadi kesalahan.</div>';
        });
    });

    // Restore
    document.getElementById('restore-file').addEventListener('change', function () {
        this.nextElementSibling.textContent = this.files[0] ? this.files[0].name : 'Pilih file .sql.gz...';
    });

    document.getElementById('restore-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!confirm('PERHATIAN: Seluruh data database akan ditimpa! Yakin ingin melanjutkan?')) return;

        var btn    = this.querySelector('button[type=submit]');
        var status = document.getElementById('restore-status');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Merestore...';

        var fd = new FormData(this);

        fetch('{{ route("tools.backup.restore") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-undo mr-1"></i>Restore Database';
            if (res.error) {
                status.innerHTML = '<div class="alert alert-danger py-2">' + res.error + '</div>';
            } else {
                status.innerHTML = '<div class="alert alert-success py-2">' + res.status + '</div>';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-undo mr-1"></i>Restore Database';
            status.innerHTML = '<div class="alert alert-danger py-2">Terjadi kesalahan.</div>';
        });
    });

    // Delete backup file
    document.querySelectorAll('.btn-delete-backup').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var file = this.dataset.file;
            if (!confirm('Hapus file backup ' + file + '?')) return;
            var row = this.closest('tr');

            fetch('{{ route("tools.backup.delete") }}', {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: file })
            })
            .then(function (r) { return r.json(); })
            .then(function () { row.remove(); });
        });
    });
})();
</script>
@endsection
