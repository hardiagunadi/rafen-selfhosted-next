<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - RAFEN ISP Manager</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/rafen-favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .auth-wordmark {
            width: min(300px, 92%);
            margin: 0 auto;
            filter: drop-shadow(0 8px 18px rgba(11, 42, 74, .2));
        }
    </style>
</head>
<body class="hold-transition register-page">
@php
    $turnstileEnabled = (bool) config('services.turnstile.enabled', false);
    $serverLoadTimeMs = defined('LARAVEL_START')
        ? (microtime(true) - LARAVEL_START) * 1000
        : null;
@endphp
<div class="register-box" style="width: 450px;">
    <div class="register-logo">
        <img src="{{ asset('branding/rafen-wordmark.svg') }}" alt="Rafen Wordmark" class="auth-wordmark">
    </div>
    <div class="card">
        <div class="card-body register-card-body">
            <p class="login-box-msg">Daftar Akun Baru</p>

            <div class="alert alert-info">
                <i class="fas fa-gift"></i> Dapatkan <strong>14 hari trial gratis</strong> untuk mencoba semua fitur!
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('register') }}" method="POST">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="name" class="form-control" placeholder="Nama Lengkap *" required value="{{ old('name') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email *" required value="{{ old('email') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" name="phone" class="form-control" placeholder="Nomor HP (Opsional)" value="{{ old('phone') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-phone"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" name="company_name" id="reg_company_name" class="form-control" placeholder="Nama ISP / Perusahaan (Opsional)" value="{{ old('company_name') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-building"></span></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" name="admin_subdomain" id="reg_subdomain"
                            class="form-control @error('admin_subdomain') is-invalid @enderror"
                            placeholder="nama-isp" value="{{ old('admin_subdomain') }}"
                            pattern="[a-z0-9\-]+" maxlength="63" required>
                        <div class="input-group-append">
                            <span class="input-group-text text-muted" style="font-size:.82rem;">.{{ config('app.main_domain') }}</span>
                        </div>
                        @error('admin_subdomain')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <small class="text-muted">URL dashboard Anda. Hanya huruf kecil, angka, dan tanda hubung (-).</small>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password *" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Konfirmasi Password *" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                @if ($turnstileEnabled)
                    <div class="cf-turnstile mb-3" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light" data-callback="onTurnstileSuccess" data-expired-callback="onTurnstileExpired" data-error-callback="onTurnstileExpired"></div>
                @endif
                <div class="row">
                    <div class="col-12">
                        <button type="submit" id="btn-register" class="btn btn-primary btn-block" @disabled($turnstileEnabled)>
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </div>
                </div>
            </form>

            <hr>

            <div class="text-center">
                <p class="mb-1">Fitur yang akan Anda dapatkan:</p>
                <small class="text-muted">
                    <i class="fas fa-check text-success"></i> Manajemen Mikrotik Unlimited |
                    <i class="fas fa-check text-success"></i> FreeRADIUS Integration |
                    <i class="fas fa-check text-success"></i> Invoicing Otomatis
                </small>
            </div>

            <hr>

            <p class="mb-0 text-center">
                <a href="{{ route('login') }}">Sudah punya akun? <strong>Login</strong></a>
            </p>
        </div>
    </div>
</div>
@if($serverLoadTimeMs !== null)
    <div class="text-center text-muted small mt-2">Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</div>
@endif
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
@if ($turnstileEnabled)
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
<script>
function onTurnstileSuccess() {
    var button = document.getElementById('btn-register');
    if (button) {
        button.disabled = false;
    }
}
function onTurnstileExpired() {
    var button = document.getElementById('btn-register');
    if (button) {
        button.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var companyInput  = document.getElementById('reg_company_name');
    var subdomainInput = document.getElementById('reg_subdomain');
    if (!companyInput || !subdomainInput) return;

    companyInput.addEventListener('input', function () {
        if (subdomainInput.dataset.manuallyEdited) return;
        subdomainInput.value = companyInput.value
            .toLowerCase()
            .replace(/[^a-z0-9\s\-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .substring(0, 63);
    });
    subdomainInput.addEventListener('input', function () {
        subdomainInput.dataset.manuallyEdited = '1';
    });
});
</script>
</body>
</html>
