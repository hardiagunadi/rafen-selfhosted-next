@if(! empty($isSelfHostedLicenseEnabled) && auth()->check() && auth()->user()->isSuperAdmin())
<li class="nav-item">
    <a href="{{ route('super-admin.settings.license') }}" class="nav-link {{ request()->routeIs('super-admin.settings.license*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-certificate"></i>
        <p>Lisensi Sistem</p>
    </a>
</li>
@endif
