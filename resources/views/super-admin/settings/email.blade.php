@extends('layouts.admin')

@section('title', 'Pengaturan Email')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-envelope mr-2 text-primary"></i>Pengaturan Email</h4>
            <small class="text-muted">Konfigurasi SMTP untuk pengiriman email otomatis ke tenant</small>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-cog mr-1"></i> Konfigurasi SMTP</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.settings.email.update') }}">
                        @csrf @method('PUT')

                        <div class="form-group">
                            <label>Mail Driver</label>
                            <select name="mailer" class="form-control" id="mailerSelect">
                                <option value="smtp" @selected($config['mailer'] === 'smtp')>SMTP</option>
                                <option value="log" @selected($config['mailer'] === 'log')>Log (testing)</option>
                            </select>
                            <small class="form-text text-muted">Pilih "Log" untuk mode development/testing — email tidak benar-benar dikirim.</small>
                        </div>

                        <div id="smtpFields">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>SMTP Host</label>
                                        <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                                            value="{{ old('host', $config['host']) }}"
                                            placeholder="smtp.gmail.com">
                                        @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                                            value="{{ old('port', $config['port']) }}"
                                            placeholder="587">
                                        @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control"
                                            value="{{ old('username', $config['username']) }}"
                                            placeholder="noreply@rafen.id">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" name="password" class="form-control"
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            autocomplete="new-password">
                                        <small class="form-text text-muted">Untuk Gmail: gunakan App Password, bukan password akun.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Enkripsi</label>
                                <select name="encryption" class="form-control">
                                    <option value="tls" @selected(($config['encryption'] ?? '') === 'tls')>TLS (port 587)</option>
                                    <option value="ssl" @selected(($config['encryption'] ?? '') === 'ssl')>SSL (port 465)</option>
                                    <option value="" @selected(empty($config['encryption']))>None</option>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Alamat Pengirim (From)</label>
                                    <input type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror"
                                        value="{{ old('from_address', $config['from_address']) }}"
                                        placeholder="noreply@rafen.id" required>
                                    @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nama Pengirim (From Name)</label>
                                    <input type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror"
                                        value="{{ old('from_name', $config['from_name']) }}"
                                        placeholder="Rafen ISP" required>
                                    @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Simpan Konfigurasi
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="fas fa-paper-plane mr-1"></i> Kirim Email Test</div>
                <div class="card-body">
                    <p class="text-muted small">Kirim email test untuk memastikan konfigurasi SMTP berfungsi.</p>
                    <form method="POST" action="{{ route('super-admin.settings.email.test') }}">
                        @csrf
                        <div class="form-group">
                            <label>Kirim ke Email</label>
                            <input type="email" name="to" class="form-control @error('to') is-invalid @enderror"
                                placeholder="admin@example.com"
                                value="{{ old('to', auth()->user()->email) }}" required>
                            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-envelope mr-1"></i> Kirim Test
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-info-circle mr-1"></i> Email yang Dikirim Otomatis</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Trigger</th><th>Penerima</th></tr></thead>
                        <tbody>
                            <tr><td><i class="fas fa-user-plus text-success mr-1"></i>Registrasi tenant baru</td><td>Email tenant</td></tr>
                            <tr><td><i class="fas fa-clock text-warning mr-1"></i>Trial habis dalam 3, 2, 1 hari</td><td>Email tenant</td></tr>
                            <tr><td><i class="fas fa-exclamation text-danger mr-1"></i>Trial berakhir hari ini</td><td>Email tenant</td></tr>
                            <tr><td><i class="fas fa-trash text-danger mr-1"></i>Akun dihapus otomatis</td><td>Email tenant</td></tr>
                            <tr><td><i class="fas fa-file-invoice text-info mr-1"></i>Tagihan langganan dibuat</td><td>Email tenant</td></tr>
                            <tr><td><i class="fas fa-check-circle text-success mr-1"></i>Pembayaran dikonfirmasi</td><td>Email tenant</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('mailerSelect').addEventListener('change', function() {
    document.getElementById('smtpFields').style.display = this.value === 'smtp' ? '' : 'none';
});
// Init
if (document.getElementById('mailerSelect').value !== 'smtp') {
    document.getElementById('smtpFields').style.display = 'none';
}
</script>
@endpush
