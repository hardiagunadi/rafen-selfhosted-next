@if(! empty($isSelfHostedLicenseEnabled) && auth()->check() && auth()->user()->isSuperAdmin() && ! empty($systemLicenseSnapshot))
@php
    $systemLicense = $systemLicenseSnapshot['license'];
    $systemLicenseAlertClass = match ($systemLicense->status) {
        'active' => 'alert-success',
        'grace' => 'alert-warning',
        'restricted', 'invalid', 'missing' => 'alert-danger',
        default => 'alert-secondary',
    };
@endphp
@if($systemLicenseSnapshot['is_enforced'] && $systemLicense->status !== 'active')
<section class="content-header pb-0">
    <div class="container-fluid">
        <div class="alert {{ $systemLicenseAlertClass }} mb-0">
            <i class="fas fa-certificate mr-1"></i>
            <strong>Lisensi sistem:</strong> {{ $systemLicenseSnapshot['status_label'] }}.
            {{ $systemLicense->validation_error ?: 'Periksa lisensi instance self-hosted Anda.' }}
            <a href="{{ route('super-admin.settings.license') }}" class="btn btn-sm btn-outline-dark ml-2">
                Kelola Lisensi
            </a>
        </div>
    </div>
</section>
@endif
@endif
