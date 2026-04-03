<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $tenantTitle = 'RAFEN Manager';
        $subscriptionExpired = false;
        $subscriptionDaysLeft = null;
        $hotspotModuleEnabled = true;
        $isSelfHostedLicenseEnabled = $isSelfHostedLicenseEnabled ?? false;
        $isSelfHostedApp = (bool) $isSelfHostedLicenseEnabled;
        $systemLicenseSnapshot = $systemLicenseSnapshot ?? null;
        $systemFeatureFlags = $systemFeatureFlags ?? [
            'radius' => true,
            'vpn' => true,
            'wa' => true,
            'olt' => true,
            'genieacs' => true,
        ];
        $isolatedUsersCount = 0;
        $monthlyRegistrationsCount = 0;
        $notificationTotal = 0;
        $vapidPublicKey = config('push.vapid.public_key', '');
        if (auth()->check()) {
            $tenantSettings = \App\Models\TenantSettings::getOrCreate(auth()->user()->effectiveOwnerId());
            if ($tenantSettings?->business_name) {
                $tenantTitle = $tenantSettings->business_name;
            }
            $hotspotModuleEnabled = $tenantSettings?->isHotspotModuleEnabled() ?? true;
            $authUser = auth()->user();
            if (! $isSelfHostedApp && ! $authUser->isSuperAdmin() && ! $authUser->canAccessApp()) {
                $subscriptionExpired = true;
            }
            if (! $isSelfHostedApp && ! $authUser->isSuperAdmin() && ! $subscriptionExpired && $authUser->subscription_expires_at) {
                $subscriptionDaysLeft = now()->diffInDays($authUser->subscription_expires_at, false);
            }

            $now = now();
            $pppUserQuery = \App\Models\PppUser::query()->accessibleBy($authUser);
            $isolatedUsersCount = (clone $pppUserQuery)->where('status_akun', 'isolir')->count();
            $monthlyRegistrationsCount = (clone $pppUserQuery)
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            if ($hotspotModuleEnabled) {
                $hotspotUserQuery = \App\Models\HotspotUser::query()->accessibleBy($authUser);
                $isolatedUsersCount += (clone $hotspotUserQuery)->where('status_akun', 'isolir')->count();
                $monthlyRegistrationsCount += (clone $hotspotUserQuery)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count();
            }

            $dueSoonCount = \App\Models\Invoice::query()
                ->accessibleBy($authUser)
                ->where('status', 'unpaid')
                ->whereBetween('due_date', [$now->toDateString(), $now->copy()->addDays(7)->toDateString()])
                ->count();

            $notificationTotal = $isolatedUsersCount + $monthlyRegistrationsCount + $dueSoonCount;

            // Untuk teknisi: ganti notifikasi dengan jumlah tiket aktif yang di-assign
            if ($authUser->role === 'teknisi') {
                $teknisiActiveTickets = \App\Models\WaTicket::where('assigned_to_id', $authUser->id)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->count();
                $notificationTotal = $teknisiActiveTickets;
            }

            // Admin, CS, dan NOC mendapat notifikasi update tiket dari teknisi
            $csTicketUpdateCount = 0;
            $canSeeTicketUpdate = ($authUser->isAdmin() && ! $authUser->isSubUser())
                || in_array($authUser->role, ['cs', 'noc'], true);
            if ($canSeeTicketUpdate) {
                $ownerId = $authUser->effectiveOwnerId();
                $csTicketUpdateCount = \App\Models\WaTicketNote::query()
                    ->where('read_by_cs', false)
                    ->whereHas('ticket', fn ($q) => $q->where('owner_id', $ownerId))
                    ->count();
                $notificationTotal += $csTicketUpdateCount;
            }

            $tenantListForSuperAdmin = [];
            $impersonatingTenant = null;
            if ($authUser->isSuperAdmin() && ! $isSelfHostedApp) {
                $tenantListForSuperAdmin = \App\Models\User::tenants()
                    ->orderBy('name')
                    ->get(['id', 'name', 'company_name']);

                $impersonatingTenantId = session('impersonating_tenant_id');
                if ($impersonatingTenantId) {
                    $impersonatingTenant = \App\Models\User::find($impersonatingTenantId);
                    if ($impersonatingTenant) {
                        $tenantSettings = \App\Models\TenantSettings::getOrCreate($impersonatingTenantId);
                        if ($tenantSettings?->business_name) {
                            $tenantTitle = $tenantSettings->business_name;
                        }
                        $hotspotModuleEnabled = $tenantSettings?->isHotspotModuleEnabled() ?? true;
                    }
                }
            }
        }
    @endphp
    @php
        $serverLoadTimeMs = defined('LARAVEL_START')
            ? (microtime(true) - LARAVEL_START) * 1000
            : null;
        $tenantHasCustomLogo = ! empty($tenantSettings?->business_logo);
        $tenantBrandLogoUrl = $tenantHasCustomLogo
            ? asset('storage/' . $tenantSettings->business_logo)
            : asset('branding/rafen-mark.svg');
        $adminIcon32Url = route('manifest.admin.icon', ['size' => 32]);
        $adminIcon180Url = route('manifest.admin.icon', ['size' => 180]);
        $adminIcon192Url = route('manifest.admin.icon', ['size' => 192]);
    @endphp
    <title>{{ $tenantTitle }}</title>
    @if($tenantHasCustomLogo)
        <link rel="icon" href="{{ $adminIcon32Url }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $adminIcon32Url }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/svg+xml" href="{{ asset('branding/rafen-favicon.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
    @endif
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $adminIcon180Url }}">
    {{-- PWA --}}
    <link rel="manifest" href="{{ route('manifest.admin') }}">
    <meta name="theme-color" content="#1367a4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $tenantTitle }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ $adminIcon192Url }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --app-bg: #f4f7fb;
            --app-border: #d7e1ee;
            --app-surface: #ffffff;
            --app-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
            --app-shadow-soft: 0 6px 14px rgba(15, 23, 42, 0.05);
            --app-text: #0f172a;
            --app-text-soft: #5b6b83;
        }

        body.sidebar-mini .content-wrapper {
            background:
                radial-gradient(circle at 8% -8%, rgba(14, 116, 144, 0.1), transparent 30%),
                radial-gradient(circle at 100% 0%, rgba(37, 99, 235, 0.07), transparent 24%),
                var(--app-bg);
        }

        .content-wrapper > .content {
            padding-top: 0.8rem;
            padding-bottom: 1rem;
        }

        .content-wrapper > .content > .container-fluid {
            padding-left: 0.95rem;
            padding-right: 0.95rem;
        }

        .content-wrapper .card {
            border: 1px solid var(--app-border);
            border-radius: 16px;
            box-shadow: var(--app-shadow-soft);
            background: var(--app-surface);
            overflow: hidden;
        }

        .content-wrapper .card-header {
            border-bottom: 1px solid #e4ebf5;
            background: linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
            padding: 0.82rem 1rem;
        }

        .content-wrapper .card-title {
            color: var(--app-text);
            font-weight: 700;
        }

        .content-wrapper .card-body {
            padding: 1rem;
        }

        .content-wrapper .card-footer {
            border-top: 1px solid #e4ebf5;
            background: #f8fbff;
            padding: 0.82rem 1rem;
        }

        .content-wrapper .form-control,
        .content-wrapper .custom-select,
        .content-wrapper .custom-file-label,
        .content-wrapper .input-group-text {
            border-radius: 8px;
            border-color: #d4deea;
        }

        .content-wrapper .form-control:focus,
        .content-wrapper .custom-select:focus {
            border-color: #8fb5df;
            box-shadow: 0 0 0 0.2rem rgba(19, 103, 164, 0.15);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection {
            border-color: #d4deea;
            border-radius: 8px;
            min-height: calc(2.25rem + 2px);
        }

        .content-wrapper .select2-container--bootstrap4.select2-container--focus .select2-selection {
            border-color: #8fb5df;
            box-shadow: 0 0 0 0.2rem rgba(19, 103, 164, 0.15);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: calc(2.25rem - 2px);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem - 2px);
        }

        .content-wrapper .table thead th {
            border-top: 0;
            border-bottom: 1px solid #dfe8f4;
            background: #f8fbff;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .content-wrapper .table td {
            vertical-align: middle;
        }

        .help-toc {
            background-color: #f8fbff !important;
            border-color: #cbd9e8 !important;
        }

        .help-toc a,
        .help-toc a:visited {
            color: #0f4f87 !important;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .help-toc a:hover,
        .help-toc a:focus {
            color: #0b3f6c !important;
            text-decoration-thickness: 2px;
        }

        .content-wrapper .btn-primary {
            background-color: #1367a4;
            border-color: #1367a4;
        }

        .content-wrapper .btn-primary:hover,
        .content-wrapper .btn-primary:focus {
            background-color: #0f5689;
            border-color: #0f5689;
        }

        .content-wrapper .small-box,
        .content-wrapper .info-box {
            border-radius: 14px;
            box-shadow: var(--app-shadow);
            overflow: hidden;
        }

        .main-header.navbar {
            border-bottom: 1px solid rgba(9, 39, 68, 0.22);
            background:
                linear-gradient(105deg, rgba(10, 62, 104, 0.98), rgba(15, 107, 149, 0.95) 45%, rgba(12, 138, 143, 0.94)) !important;
            box-shadow: 0 8px 20px rgba(9, 39, 68, 0.22);
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning) {
            color: #e7f5ff;
            border-radius: 9px;
            transition: background-color 0.16s ease, color 0.16s ease, transform 0.16s ease;
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning):hover,
        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning):focus {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.16);
            transform: translateY(-1px);
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link.text-danger,
        .main-header.navbar .navbar-nav > .nav-item > .nav-link.text-warning {
            font-weight: 700;
            text-shadow: 0 1px 8px rgba(7, 20, 35, 0.35);
        }

        .main-header.navbar .user-dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
        }

        .main-header.navbar .user-dropdown-toggle::after {
            border-top-color: currentColor;
        }

        .main-header.navbar .user-dropdown-menu {
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            box-shadow: 0 12px 24px rgba(2, 8, 23, 0.18);
            min-width: 220px;
        }

        .main-header.navbar .user-dropdown-menu .dropdown-item {
            font-weight: 500;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-profile {
            padding: 0.6rem 1rem 0.5rem;
            color: #1f2937;
            line-height: 1.35;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-name {
            display: block;
            font-weight: 700;
            color: #0f172a;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-meta {
            display: block;
            color: #64748b;
            font-size: 0.85rem;
        }

        .main-header.navbar .user-dropdown-menu .dropdown-item:active {
            background-color: #0f6b95;
        }

        .main-header.navbar .nav-link-icon {
            width: 2.3rem;
            height: 2.3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .main-header.navbar .nav-icon-badge {
            position: absolute;
            top: 0.12rem;
            right: 0.12rem;
            min-width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            font-size: 0.65rem;
            line-height: 1.1rem;
            text-align: center;
            font-weight: 700;
            color: #fff;
            animation: badge-pulse 2s ease-in-out infinite;
        }

        @keyframes badge-pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            50% { transform: scale(1.15); box-shadow: 0 0 0 5px rgba(220, 53, 69, 0); }
        }

        /* Sidebar badge ping animation */
        .nav-sidebar .badge {
            position: relative;
            animation: badge-bounce 2.5s ease-in-out infinite;
        }

        .nav-sidebar .badge::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 999px;
            animation: badge-ring 2.5s ease-in-out infinite;
        }

        .nav-sidebar .badge-danger::after {
            background: rgba(220, 53, 69, 0.5);
        }

        .nav-sidebar .badge-warning::after {
            background: rgba(244, 176, 0, 0.5);
        }

        @keyframes badge-bounce {
            0%, 90%, 100% { transform: scale(1); }
            95% { transform: scale(1.25); }
        }

        @keyframes badge-ring {
            0%, 70%, 100% { transform: scale(1); opacity: 0; }
            75% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        .main-header.navbar .nav-icon-badge.badge-danger {
            background-color: #dc3545;
        }

        .main-header.navbar .nav-icon-badge.badge-warning {
            background-color: #f4b000;
        }

        .main-header.navbar .navbar-icon-dropdown {
            min-width: 280px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            box-shadow: 0 12px 24px rgba(2, 8, 23, 0.18);
        }

        .main-header.navbar .navbar-icon-dropdown .dropdown-item-text {
            white-space: normal;
        }

        .main-header.navbar .navbar-search-block {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .main-header.navbar .navbar-search-block .form-control-navbar,
        .main-header.navbar .navbar-search-block .btn-navbar,
        .main-header.navbar .navbar-search-block .custom-select {
            height: calc(2rem + 2px);
        }

        .main-header.navbar .navbar-search-block .search-target-select {
            min-width: 145px;
            border: 1px solid #d0d8e4;
            border-right: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            font-size: 0.9rem;
        }

        .main-header.navbar .navbar-search-block .form-control-navbar {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-left: 1px solid #d0d8e4;
        }

        .main-sidebar.sidebar-modern {
            position: relative;
            border-right: 1px solid rgba(148, 163, 184, 0.24);
            background: linear-gradient(180deg, #081527 0%, #0d2035 48%, #102a44 100%);
        }

        .main-sidebar.sidebar-modern::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 18% 6%, rgba(56, 189, 248, 0.24), transparent 28%),
                radial-gradient(circle at 85% 0%, rgba(14, 165, 233, 0.18), transparent 26%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0) 35%);
        }

        .sidebar-modern .brand-link,
        .sidebar-modern .sidebar {
            position: relative;
            z-index: 1;
        }

        .sidebar-modern .brand-link {
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: linear-gradient(110deg, rgba(15, 118, 168, 0.35), rgba(14, 165, 233, 0.14));
            padding-top: 0.95rem;
            padding-bottom: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
        }

        .sidebar-modern .brand-text {
            color: #f8fbff;
            font-weight: 700;
            letter-spacing: 0.015em;
            text-shadow: 0 2px 10px rgba(15, 23, 42, 0.35);
        }

        .sidebar-modern .brand-logo-mark {
            width: 1.95rem;
            height: 1.95rem;
            border-radius: 0.5rem;
            box-shadow: 0 8px 16px rgba(8, 23, 39, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.24);
            object-fit: contain;
            background: rgba(255, 255, 255, 0.92);
            padding: 0.18rem;
        }

        .sidebar-modern .sidebar {
            scrollbar-width: thin;
            scrollbar-color: rgba(125, 166, 210, 0.5) transparent;
        }

        .sidebar-modern .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-modern .sidebar::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(125, 166, 210, 0.42);
        }

        .sidebar-modern .nav-header {
            color: #9eb3cc;
            font-size: 0.69rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding-top: 0.9rem;
            padding-bottom: 0.45rem;
        }

        .sidebar-modern .nav-sidebar .nav-link {
            margin: 0.17rem 0.4rem;
            border-radius: 11px;
            border: 1px solid transparent;
            color: #d7e5f7;
            padding: 0.58rem 0.72rem;
            transition: all 170ms ease;
        }

        .sidebar-modern .nav-sidebar > .nav-item > .nav-link {
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.015);
        }

        .sidebar-modern .nav-sidebar .nav-link p {
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        .sidebar-modern .nav-sidebar .nav-link .nav-icon {
            width: 1.84rem;
            height: 1.84rem;
            margin-right: 0.5rem;
            border-radius: 9px;
            line-height: 1.84rem;
            text-align: center;
            background: rgba(148, 163, 184, 0.16);
            color: #97b4d2;
            font-size: 0.87rem;
            transition: all 170ms ease;
        }

        .sidebar-modern .nav-sidebar .nav-link .right {
            color: #9ab4d1;
        }

        .sidebar-modern .nav-sidebar .nav-link:hover,
        .sidebar-modern .nav-sidebar .nav-link:focus {
            background: rgba(37, 99, 235, 0.18);
            border-color: rgba(125, 166, 210, 0.3);
            color: #f5fbff;
            transform: translateX(2px);
        }

        .sidebar-modern .nav-sidebar .nav-link:hover .nav-icon,
        .sidebar-modern .nav-sidebar .nav-link:focus .nav-icon {
            background: rgba(191, 219, 254, 0.23);
            color: #eaf5ff;
        }

        .sidebar-modern .nav-sidebar .menu-open > .nav-link,
        .sidebar-modern .nav-sidebar .nav-link.active {
            background: linear-gradient(135deg, #0f6aa7 0%, #17a2b8 100%);
            border-color: rgba(255, 255, 255, 0.26);
            box-shadow: 0 10px 22px rgba(9, 91, 138, 0.36);
            color: #f8fdff;
        }

        .sidebar-modern .nav-sidebar .menu-open > .nav-link .nav-icon,
        .sidebar-modern .nav-sidebar .nav-link.active .nav-icon {
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .sidebar-modern .nav-treeview {
            margin: 0.22rem 0.38rem 0.42rem 1.35rem;
            padding-left: 0.46rem;
            border-left: 1px dashed rgba(148, 163, 184, 0.38);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link {
            margin: 0.14rem 0;
            padding: 0.48rem 0.6rem;
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.24);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link:hover,
        .sidebar-modern .nav-treeview > .nav-item > .nav-link:focus {
            background: rgba(37, 99, 235, 0.26);
            transform: none;
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link.active {
            background: linear-gradient(135deg, #2c7fbf 0%, #0ea5e9 100%);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link .nav-icon {
            width: 1.6rem;
            height: 1.6rem;
            line-height: 1.6rem;
            border-radius: 7px;
            font-size: 0.72rem;
        }

        @media (max-width: 991.98px) {
            .content-wrapper > .content > .container-fluid {
                padding-left: 0.65rem;
                padding-right: 0.65rem;
            }

            .sidebar-modern .nav-sidebar .nav-link {
                margin-left: 0.35rem;
                margin-right: 0.35rem;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    {{-- PWA install banner (shown only on Android Chrome when installable) --}}
    <div id="pwa-install-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:10000;background:#1367a4;color:#fff;padding:10px 16px;align-items:center;justify-content:space-between;font-size:14px;box-shadow:0 -2px 8px rgba(0,0,0,.2);">
        <span id="pwa-install-message"><i class="fas fa-mobile-alt" style="margin-right:6px;"></i>Pasang Rafen Manager di perangkat Anda</span>
        <span>
            <button id="pwa-install-btn" style="background:#fff;color:#1367a4;border:none;padding:5px 14px;border-radius:4px;font-weight:600;cursor:pointer;margin-right:8px;">Pasang</button>
            <button id="pwa-install-dismiss" style="background:transparent;color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.4);padding:5px 10px;border-radius:4px;cursor:pointer;">Nanti</button>
        </span>
    </div>
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ auth()->check() && $isSelfHostedApp && auth()->user()->isSuperAdmin() ? route('super-admin.dashboard') : route('dashboard') }}" class="nav-link">Dashboard</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            @auth
                @if(!$isSelfHostedApp && $subscriptionExpired)
                <li class="nav-item">
                    <a href="{{ route('subscription.expired') }}" class="nav-link text-danger font-weight-bold">
                        <i class="fas fa-exclamation-triangle"></i> Langganan Berakhir
                    </a>
                </li>
                @elseif(!$isSelfHostedApp && $subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
                <li class="nav-item">
                    <a href="{{ route('subscription.index') }}" class="nav-link {{ $subscriptionDaysLeft <= 3 ? 'text-danger' : 'text-warning' }} font-weight-bold">
                        <i class="fas fa-bell"></i>
                        @if($subscriptionDaysLeft <= 0)
                            Langganan habis hari ini!
                        @else
                            Langganan berakhir {{ $subscriptionDaysLeft }} hari lagi
                        @endif
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link nav-link-icon" href="#" role="button" data-widget="navbar-search" title="Pencarian">
                        <i class="fas fa-search"></i>
                    </a>
                </li>
<li class="nav-item" id="push-subscribe-nav" style="display:none;">
                    <button id="push-subscribe-btn" class="nav-link btn btn-link nav-link-icon" title="Aktifkan Notifikasi Push" style="background:none;border:none;cursor:pointer;">
                        <i class="far fa-bell-slash"></i>
                    </button>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link nav-link-icon" href="#" id="navbar-notification-dropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Notifikasi">
                        <i class="far fa-bell"></i>
                        @if($notificationTotal > 0)
                            <span class="nav-icon-badge badge-warning">{{ $notificationTotal }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-icon-dropdown" aria-labelledby="navbar-notification-dropdown">
                        <span class="dropdown-item-text font-weight-bold">Ringkasan Notifikasi</span>
                        <div class="dropdown-divider"></div>
                        @if(auth()->user()->role === 'teknisi')
                        <a href="{{ route('wa-tickets.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-ticket-alt text-warning mr-2"></i>Tiket Aktif Saya</span>
                            <span class="badge badge-{{ $teknisiActiveTickets > 0 ? 'danger' : 'secondary' }}">{{ $teknisiActiveTickets ?? 0 }}</span>
                        </a>
                        @else
                        @if($csTicketUpdateCount > 0)
                        <a href="{{ route('wa-tickets.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-ticket-alt text-warning mr-2"></i>Update Tiket dari Teknisi</span>
                            <span class="badge badge-warning">{{ $csTicketUpdateCount }}</span>
                        </a>
                        @endif
                        <a href="{{ route('ppp-users.index', ['filter_isolir' => 1]) }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-slash text-danger mr-2"></i>User Terisolir</span>
                            <span class="badge badge-danger">{{ $isolatedUsersCount }}</span>
                        </a>
                        <a href="{{ route('ppp-users.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-plus text-info mr-2"></i>Registrasi Bulan Ini</span>
                            <span class="badge badge-info">{{ $monthlyRegistrationsCount }}</span>
                        </a>
                        <a href="{{ route('invoices.index', ['filter_due' => '7days']) }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-times text-warning mr-2"></i>Jatuh Tempo 7 Hari</span>
                            <span class="badge badge-warning">{{ $dueSoonCount }}</span>
                        </a>
                        @endif
                    </div>
                </li>
                @if(auth()->user()->isSuperAdmin() && !$isSelfHostedApp)
                <li class="nav-item dropdown">
                    <a class="nav-link nav-link-icon" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Lihat Tenant">
                        <i class="fas fa-building"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" style="max-height:400px;overflow-y:auto;min-width:260px;">
                        <span class="dropdown-item-text font-weight-bold text-muted"><i class="fas fa-building mr-1"></i> Pilih Tenant</span>
                        <div class="dropdown-divider"></div>
                        @foreach($tenantListForSuperAdmin as $t)
                        <form action="{{ route('super-admin.impersonate', $t) }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item{{ $impersonatingTenant && $impersonatingTenant->id === $t->id ? ' active' : '' }}" style="background:none;border:none;width:100%;text-align:left;cursor:pointer;">
                                {{ $t->name }}
                                @if($t->company_name)
                                    <small class="text-muted d-block">{{ $t->company_name }}</small>
                                @endif
                            </button>
                        </form>
                        @endforeach
                        @if($tenantListForSuperAdmin->isEmpty())
                            <span class="dropdown-item-text text-muted">Belum ada tenant</span>
                        @endif
                    </div>
                </li>
                @endif
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle user-dropdown-toggle" href="#" id="navbar-user-dropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ auth()->user()->name }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-right user-dropdown-menu" aria-labelledby="navbar-user-dropdown">
                        <div class="dropdown-item-text user-dropdown-profile">
                            <span class="user-dropdown-name">{{ auth()->user()->name }}</span>
                            <span class="user-dropdown-meta">(Role: {{ ucwords(str_replace('_', ' ', auth()->user()->role)) }})</span>
                            <span class="user-dropdown-meta">({{ $tenantTitle }})</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </li>
            @else
                <li class="nav-item"><a href="{{ route('login') }}" class="nav-link">Login</a></li>
                @if(!$isSelfHostedApp)
                <li class="nav-item"><a href="{{ route('register') }}" class="nav-link">Register</a></li>
                @endif
            @endauth
        </ul>
        @auth
            <div class="navbar-search-block">
                <form
                    class="form-inline"
                    id="navbar-global-search-form"
                    action="{{ route('ppp-users.index') }}"
                    method="GET"
                    data-pppoe-action="{{ route('ppp-users.index') }}"
                    data-pppoe-autocomplete="{{ route('ppp-users.autocomplete') }}"
                    @if($hotspotModuleEnabled)
                        data-hotspot-action="{{ route('hotspot-users.index') }}"
                        data-hotspot-autocomplete="{{ route('hotspot-users.autocomplete') }}"
                    @endif
                >
                    <div class="input-group input-group-sm w-100">
                        @if($hotspotModuleEnabled)
                            <div class="input-group-prepend">
                                <select class="custom-select search-target-select" id="navbar-search-target" name="target" data-native-select="true">
                                    <option value="pppoe" @selected(request('target', 'pppoe') === 'pppoe')>PPPoE</option>
                                    <option value="hotspot" @selected(request('target') === 'hotspot')>Hotspot</option>
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="target" value="pppoe">
                        @endif
                        <input class="form-control form-control-navbar" name="search" id="navbar-search-keyword" type="search" value="{{ request('search') }}" placeholder="Cari pelanggan, ID, username..." aria-label="Search" list="navbar-search-suggestions" autocomplete="off">
                        <datalist id="navbar-search-suggestions"></datalist>
                        <div class="input-group-append">
                            <button class="btn btn-navbar" type="submit" title="Cari">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-navbar" type="button" data-widget="navbar-search" title="Tutup">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endauth
    </nav>
    @auth
        @include('auth.logout')
    @endauth

    <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-modern">
        <a href="{{ auth()->check() && $isSelfHostedApp && auth()->user()->isSuperAdmin() ? route('super-admin.dashboard') : route('dashboard') }}" class="brand-link text-center">
            <img src="{{ $tenantBrandLogoUrl }}" alt="{{ $tenantTitle }} Logo" class="brand-logo-mark">
            <span class="brand-text font-weight-light">{{ $tenantTitle }}</span>
        </a>
        <div class="sidebar">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
            <nav class="mt-2">
                @php
                    $listPelangganRoutes = $hotspotModuleEnabled
                        ? ['hotspot-users.*', 'ppp-users.*', 'vouchers.*', 'customer-map.*', 'odps.*']
                        : ['ppp-users.*', 'customer-map.*', 'odps.*'];
                    $profilePaketRoutes = $hotspotModuleEnabled
                        ? ['bandwidth-profiles.*', 'profile-groups.*', 'hotspot-profiles.*', 'ppp-profiles.*']
                        : ['bandwidth-profiles.*', 'profile-groups.*', 'ppp-profiles.*'];
                @endphp
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    @if(!$authUser->isSuperAdmin() || $impersonatingTenant || $isSelfHostedApp)
                    @if(!$isSelfHostedApp && $subscriptionExpired)
                    {{-- Subscription expired: hanya tampilkan menu Langganan --}}
                    <li class="nav-item">
                        <a href="{{ route('subscription.expired') }}" class="nav-link text-warning">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>Perpanjang Langganan</p>
                        </a>
                    </li>
                    @else
                    <li class="nav-item">
                        <a href="{{ auth()->check() && $isSelfHostedApp && auth()->user()->isSuperAdmin() ? route('super-admin.dashboard') : route('dashboard') }}" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview {{ request()->is('sessions*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('sessions*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-signal"></i>
                            <p>
                                Session User
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('sessions.pppoe') }}" class="nav-link {{ request()->routeIs('sessions.pppoe*') || request()->routeIs('sessions.pppoe-inactive*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>PPPoE</p>
                                </a>
                            </li>
                            @if($hotspotModuleEnabled)
                                <li class="nav-item">
                                    <a href="{{ route('sessions.hotspot') }}" class="nav-link {{ request()->routeIs('sessions.hotspot*') || request()->routeIs('sessions.hotspot-inactive*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Hotspot</p>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                     <li class="nav-item has-treeview {{ request()->routeIs(...$listPelangganRoutes) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(...$listPelangganRoutes) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                List Pelanggan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if($hotspotModuleEnabled)
                                <li class="nav-item">
                                    <a href="{{ route('hotspot-users.index') }}" class="nav-link {{ request()->routeIs('hotspot-users.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>User Hotspot</p>
                                    </a>
                                </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('ppp-users.index') }}" class="nav-link {{ request()->routeIs('ppp-users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>User PPP</p>
                                </a>
                            </li>
                            @if($hotspotModuleEnabled)
                            <li class="nav-item">
                                <a href="{{ route('vouchers.index') }}" class="nav-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Voucher</p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('customer-map.index') }}" class="nav-link {{ request()->routeIs('customer-map.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Peta Pelanggan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('odps.index') }}" class="nav-link {{ request()->routeIs('odps.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Data ODP</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('mikrotik-connections.index') }}" class="nav-link">
                            <i class="nav-icon fas fa-server"></i>
                            <p>Router (NAS)</p>
                        </a>
                    </li>
                    @if(($systemFeatureFlags['olt'] ?? true) && in_array(auth()->user()->role, ['administrator', 'noc', 'teknisi'], true))
                    <li class="nav-item">
                        <a href="{{ route('olt-connections.index') }}" class="nav-link {{ request()->routeIs('olt-connections.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-broadcast-tower"></i>
                            <p>Monitoring OLT</p>
                        </a>
                    </li>
                    @endif
                    @if(($systemFeatureFlags['genieacs'] ?? true) && in_array(auth()->user()->role, ['administrator', 'noc', 'it_support'], true))
                    <li class="nav-item">
                        <a href="{{ route('cpe.index') }}" class="nav-link {{ request()->routeIs('cpe.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-network-wired"></i>
                            <p>CPE Management</p>
                        </a>
                    </li>

                    @endif
                    @if(auth()->user()->role !== 'teknisi')
                        <li class="nav-item has-treeview {{ request()->routeIs(...$profilePaketRoutes) ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->routeIs(...$profilePaketRoutes) ? 'active' : '' }}">
                                <i class="nav-icon fas fa-box"></i>
                                <p>
                                    Profil Paket
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('bandwidth-profiles.index') }}" class="nav-link {{ request()->routeIs('bandwidth-profiles.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Bandwidth</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('profile-groups.index') }}" class="nav-link {{ request()->routeIs('profile-groups.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Profil Group</p>
                                    </a>
                                </li>
                                @if($hotspotModuleEnabled)
                                    <li class="nav-item">
                                        <a href="{{ route('hotspot-profiles.index') }}" class="nav-link {{ request()->routeIs('hotspot-profiles.*') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Profil Hotspot</p>
                                        </a>
                                    </li>
                                @endif
                                <li class="nav-item">
                                    <a href="{{ route('ppp-profiles.index') }}" class="nav-link {{ request()->routeIs('ppp-profiles.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Profil PPP</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                    
                    <li class="nav-item has-treeview {{ request()->routeIs('payments.pending*') || request()->routeIs('invoices.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('payments.pending*') || request()->routeIs('invoices.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-invoice"></i>
                            <p>
                                Data Tagihan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-toggle="modal" data-target="#period-bills-modal">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tagihan Periode</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Rekap Invoice Terhutang</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('invoices.unpaid') }}" class="nav-link {{ request()->routeIs('invoices.unpaid') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Invoice Belum Lunas</p>
                                </a>
                            </li>
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || in_array(auth()->user()->role, ['keuangan', 'cs']))
                            <li class="nav-item">
                                <a href="{{ route('payments.pending') }}" class="nav-link {{ request()->routeIs('payments.pending*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Konfirmasi Transfer</p>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan', 'teknisi']))
                    <li class="nav-item">
                        <a href="{{ route('teknisi-setoran.index') }}" class="nav-link {{ request()->routeIs('teknisi-setoran.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <p>Rekonsiliasi Nota</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan', 'teknisi'], true))
                    <li class="nav-item has-treeview {{ request()->routeIs('reports.income') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('reports.income') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-dollar-sign"></i>
                            <p>
                                Data Keuangan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'daily']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report', 'daily') === 'daily' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Harian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'period']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'period' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Periode</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'expense']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'expense' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengeluaran</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'profit_loss']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'profit_loss' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laba Rugi</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'bhp_uso']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'bhp_uso' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Hitung BHP | USO</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    @if(!$isSelfHostedApp && !auth()->user()->isSuperAdmin() && !auth()->user()->isSubUser())
                    @php
                        $walletSettings = $tenantSettings ?? \App\Models\TenantSettings::where('user_id', auth()->user()->effectiveOwnerId())->first();
                    @endphp
                    @if($walletSettings?->isUsingPlatformGateway())
                    <li class="nav-item">
                        <a href="{{ route('wallet.index') }}" class="nav-link {{ request()->routeIs('wallet.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>Wallet Saldo</p>
                        </a>
                    </li>
                    @endif
                    @endif
                    @php
                        $isSuperAdmin = auth()->user()->isSuperAdmin();
                        $isAdminOrAbove = $isSuperAdmin || (auth()->user()->isAdmin() && !auth()->user()->isSubUser());
                        $isTeknisi = auth()->user()->role === 'teknisi';
                        $isKeuangan = auth()->user()->role === 'keuangan';
                        $tenantSettings = $tenantSettings ?? \App\Models\TenantSettings::where('user_id', auth()->user()->effectiveOwnerId())->first();
                        $shiftModuleEnabled = $tenantSettings?->isShiftModuleEnabled() ?? false;
                        $waChatRoles = ['administrator', 'noc', 'it_support', 'cs'];
                        $canSeeWaChat = $isSuperAdmin || in_array(auth()->user()->role, $waChatRoles, true) || $isTeknisi;
                        $canSeeShift = $isSuperAdmin || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support', 'cs', 'teknisi'], true);
                        $waChatUnreadCount = ($canSeeWaChat && !$isTeknisi) ? \App\Models\WaConversation::query()->accessibleBy(auth()->user())->where('unread_count', '>', 0)->sum('unread_count') : 0;
                        // Badge tiket untuk teknisi: tiket open/in_progress yang di-assign ke mereka
                        $teknisiTicketCount = $isTeknisi ? \App\Models\WaTicket::where('assigned_to_id', auth()->id())->whereIn('status', ['open', 'in_progress'])->count() : 0;
                        // Badge update tiket dari teknisi (untuk CS/NOC/admin)
                        $csTicketUpdateCount = $csTicketUpdateCount ?? 0;
                        // Badge gangguan jaringan aktif
                        $activeOutageCount = (!$isSuperAdmin) ? \App\Models\Outage::query()->accessibleBy(auth()->user())->whereIn('status', ['open', 'in_progress'])->count() : 0;
                    @endphp
                    @if($isAdminOrAbove)
                    <li class="nav-item has-treeview {{ request()->is('tools*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('tools*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>
                                Tool Sistem
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('tools.usage') }}" class="nav-link {{ request()->is('tools/usage*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Cek Pemakaian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.import') }}" class="nav-link {{ request()->is('tools/import*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Impor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.export-users') }}" class="nav-link {{ request()->is('tools/export-users*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.export-transactions') }}" class="nav-link {{ request()->is('tools/export-transactions*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor Transaksi</p>
                                </a>
                            </li>
                            @if($isSuperAdmin)
                            <li class="nav-item">
                                <a href="{{ route('tools.backup') }}" class="nav-link {{ request()->is('tools/backup*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Backup Restore DB</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.reset-report') }}" class="nav-link text-danger {{ request()->is('tools/reset-report*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Laporan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.reset-database') }}" class="nav-link text-danger {{ request()->is('tools/reset-database*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Database</p>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    @if(!$isTeknisi)
                    <li class="nav-item has-treeview {{ request()->routeIs('logs.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                            <i class="nav-icon far fa-file-alt"></i>
                            <p>
                                Log Aplikasi
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('logs.login') }}" class="nav-link {{ request()->routeIs('logs.login') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Login</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.activity') }}" class="nav-link {{ request()->routeIs('logs.activity') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Aktivitas</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.genieacs') }}" class="nav-link {{ request()->routeIs('logs.genieacs', 'logs.genieacs.data') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log GenieACS</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.radius-auth') }}" class="nav-link {{ request()->routeIs('logs.radius-auth') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Auth Radius</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <hr class="mt-1 mb-1">
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.wa-pengiriman') }}" class="nav-link {{ request()->routeIs('logs.wa-pengiriman') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Pengiriman WA</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    {{-- Chat WA --}}
                    @if($canSeeWaChat)
                    @php
                        $waMenuBadge = $isTeknisi ? $teknisiTicketCount : ($waChatUnreadCount + $csTicketUpdateCount);
                    @endphp
                    <li class="nav-item has-treeview {{ request()->routeIs('wa-chat.*', 'wa-tickets.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('wa-chat.*', 'wa-tickets.*') ? 'active' : '' }}">
                            <i class="nav-icon fab fa-whatsapp text-success"></i>
                            <p>
                                {{ $isTeknisi ? 'Tiket Saya' : 'Chat WA' }}
                                <i class="right fas fa-angle-left"></i>
                                @if($waMenuBadge > 0)
                                <span class="badge badge-danger ml-auto mr-1">{{ $waMenuBadge > 99 ? '99+' : $waMenuBadge }}</span>
                                @endif
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(!$isTeknisi)
                            <li class="nav-item">
                                <a href="{{ route('wa-chat.index') }}" class="nav-link {{ request()->routeIs('wa-chat.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        Inbox Chat
                                        @if($waChatUnreadCount > 0)
                                        <span class="badge badge-danger ml-auto">{{ $waChatUnreadCount > 99 ? '99+' : $waChatUnreadCount }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('wa-tickets.index') }}" class="nav-link {{ request()->routeIs('wa-tickets.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        {{ $isTeknisi ? 'Tiket Saya' : 'Tiket Pengaduan' }}
                                        @if($isTeknisi && $teknisiTicketCount > 0)
                                        <span class="badge badge-danger ml-auto">{{ $teknisiTicketCount > 99 ? '99+' : $teknisiTicketCount }}</span>
                                        @elseif(!$isTeknisi && $csTicketUpdateCount > 0)
                                        <span class="badge badge-warning ml-auto" title="Ada update dari teknisi">{{ $csTicketUpdateCount > 99 ? '99+' : $csTicketUpdateCount }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    {{-- Gangguan Jaringan --}}
                    @if($isSuperAdmin || in_array(auth()->user()->role, ['administrator','noc','it_support','cs','teknisi']))
                    <li class="nav-item {{ request()->routeIs('outages.*') ? 'menu-open' : '' }}">
                        <a href="{{ route('outages.index') }}" class="nav-link {{ request()->routeIs('outages.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-broadcast-tower text-danger"></i>
                            <p>
                                Gangguan Jaringan
                                @if($activeOutageCount > 0)
                                <span class="badge badge-danger ml-auto">{{ $activeOutageCount > 9 ? '9+' : $activeOutageCount }}</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    @endif

                    {{-- Jadwal Shift --}}
                    @if($canSeeShift && $shiftModuleEnabled)
                    <li class="nav-item has-treeview {{ request()->routeIs('shifts.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>
                                Jadwal Shift
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(in_array(auth()->user()->role, ['administrator'], true) || $isSuperAdmin)
                            <li class="nav-item">
                                <a href="{{ route('shifts.index') }}" class="nav-link {{ request()->routeIs('shifts.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Kelola Jadwal</p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('shifts.my') }}" class="nav-link {{ request()->routeIs('shifts.my') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Jadwal Saya</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    @if(!$isTeknisi)
                    <li class="nav-item has-treeview {{ request()->routeIs('users.*', 'tenant-settings.*', 'settings.*', 'wa-gateway.*', 'wa-blast.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('users.*', 'tenant-settings.*', 'settings.*', 'wa-gateway.*', 'wa-blast.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>
                                Pengaturan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(auth()->user()->isSuperAdmin() || (auth()->user()->isAdmin() && !auth()->user()->isSubUser()))
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Manajemen Pengguna</p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('tenant-settings.index') }}" class="nav-link {{ request()->routeIs('tenant-settings.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengaturan</p>
                                </a>
                            </li>
                            @if($systemFeatureFlags['wa'] ?? true)
                            <li class="nav-item has-treeview {{ request()->routeIs('wa-gateway.*', 'wa-blast.*') ? 'menu-open' : '' }}">
                                <a href="#" class="nav-link {{ request()->routeIs('wa-gateway.*', 'wa-blast.*') ? 'active' : '' }}">
                                    <i class="fab fa-whatsapp nav-icon text-success"></i>
                                    <p>
                                        WhatsApp
                                        <i class="right fas fa-angle-left"></i>
                                    </p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item">
                                        <a href="{{ route('wa-gateway.index') }}" class="nav-link {{ request()->routeIs('wa-gateway.*') && request()->query('tab', 'overview') !== 'devices' ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Gateway & Template</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('wa-gateway.index', array_filter([
                                            'tab' => 'devices',
                                            'tenant_id' => request()->query('tenant_id'),
                                        ])) }}" class="nav-link {{ request()->routeIs('wa-gateway.*') && request()->query('tab') === 'devices' ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Manajemen Device</p>
                                        </a>
                                    </li>
                                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support', 'cs'], true))
                                    <li class="nav-item">
                                        <a href="{{ route('wa-blast.index') }}" class="nav-link {{ request()->routeIs('wa-blast.*') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>WA Blast</p>
                                        </a>
                                    </li>
                                    @endif
                                </ul>
                            </li>
                            @endif
                            @if(auth()->user()->isSuperAdmin() && ($systemFeatureFlags['radius'] ?? true))
                            <li class="nav-item">
                                <a href="{{ route('settings.freeradius') }}" class="nav-link {{ request()->routeIs('settings.freeradius') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>FreeRADIUS</p>
                                </a>
                            </li>
                            @endif
                            @if($systemFeatureFlags['vpn'] ?? true)
                            <li class="nav-item">
                                <a href="{{ route('settings.wg') }}" class="nav-link {{ request()->routeIs('settings.wg') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>WireGuard</p>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @endif {{-- end !$isTeknisi (Pengaturan) --}}
                    @endif {{-- end @else (subscription not expired) --}}
                    @if(!$isSelfHostedApp && !auth()->user()->isSubUser())
                    <li class="nav-item has-treeview {{ request()->routeIs('subscription.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-credit-card {{ $subscriptionExpired ? 'text-warning' : '' }}"></i>
                            <p>
                                Langganan
                                @if($subscriptionExpired)
                                    <span class="badge badge-danger badge-pill ml-1">!</span>
                                @elseif($subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
                                    <span class="badge {{ $subscriptionDaysLeft <= 3 ? 'badge-danger' : 'badge-warning' }} badge-pill ml-1">{{ $subscriptionDaysLeft }}h</span>
                                @endif
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('subscription.index') }}" class="nav-link {{ request()->routeIs('subscription.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Status Langganan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.plans') }}" class="nav-link {{ request()->routeIs('subscription.plans') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Paket Tersedia</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.history') }}" class="nav-link {{ request()->routeIs('subscription.history') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Riwayat Pembayaran</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif {{-- end !isSubUser (Langganan) --}}
                    @endif {{-- end !isSuperAdmin (tenant menus) --}}
                    @if(auth()->user()->isSuperAdmin() && !$impersonatingTenant)
                    <li class="nav-header">{{ $isSelfHostedApp ? 'SELF-HOSTED ADMIN' : 'SUPER ADMIN' }}</li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.dashboard') }}" class="nav-link {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-crown"></i>
                            <p>{{ $isSelfHostedApp ? 'Dashboard Sistem' : 'Admin Dashboard' }}</p>
                        </a>
                    </li>
                    @if(!$isSelfHostedApp)
                    <li class="nav-item">
                        <a href="{{ route('super-admin.tenants') }}" class="nav-link {{ request()->routeIs('super-admin.tenants*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Kelola Tenant</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.subscription-plans.index') }}" class="nav-link {{ request()->routeIs('super-admin.subscription-plans*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tags"></i>
                            <p>Paket Langganan</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.payment-gateways') }}" class="nav-link {{ request()->routeIs('super-admin.payment-gateways*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-credit-card"></i>
                            <p>Payment Gateway</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.wallets.index') }}" class="nav-link {{ request()->routeIs('super-admin.wallets*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>Saldo Tenant</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.withdrawals.index') }}" class="nav-link {{ request()->routeIs('super-admin.withdrawals*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-money-bill-wave"></i>
                            <p>Penarikan Saldo</p>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('super-admin.reports.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('super-admin.reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Laporan <i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('super-admin.reports.revenue') }}" class="nav-link {{ request()->routeIs('super-admin.reports.revenue') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pendapatan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('super-admin.reports.tenants') }}" class="nav-link {{ request()->routeIs('super-admin.reports.tenants') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tenant</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{ route('super-admin.server-health') }}" class="nav-link {{ request()->routeIs('super-admin.server-health') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-server"></i>
                            <p>Server Health</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.self-hosted-toolkit.index') }}" class="nav-link {{ request()->routeIs('super-admin.self-hosted-toolkit.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-box-open"></i>
                            <p>Self-Hosted Toolkit</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.terminal.index') }}" class="nav-link {{ request()->routeIs('super-admin.terminal.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-terminal"></i>
                            <p>Terminal</p>
                        </a>
                    </li>
                    @if($systemFeatureFlags['wa'] ?? true)
                    @php $baileysUpdate = \Illuminate\Support\Facades\Cache::get('baileys_update_check'); @endphp
                    <li class="nav-item">
                        <a href="{{ route('super-admin.wa-gateway') }}" class="nav-link {{ request()->routeIs('super-admin.wa-gateway*') ? 'active' : '' }}">
                            <i class="nav-icon fab fa-whatsapp"></i>
                            <p>
                                WA Gateway
                                @if($baileysUpdate && ($baileysUpdate['has_update'] ?? false))
                                    <span class="badge badge-warning badge-pill ml-1" title="Update Baileys tersedia">!</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    @if(!$isSelfHostedApp)
                    @php $pendingWaRequests = \Illuminate\Support\Facades\Cache::remember('sa_pending_wa_device_requests', 60, fn() => \App\Models\WaPlatformDeviceRequest::where('status', 'pending')->count()); @endphp
                    <li class="nav-item">
                        <a href="{{ route('super-admin.wa-platform-device-requests.index') }}" class="nav-link {{ request()->routeIs('super-admin.wa-platform-device-requests*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-mobile-alt"></i>
                            <p>
                                Device Request WA
                                @if($pendingWaRequests > 0)
                                    <span class="badge badge-danger badge-pill ml-1">{{ $pendingWaRequests }}</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    @endif
                    @endif
                    <li class="nav-item">
                        <a href="{{ route('super-admin.settings.email') }}" class="nav-link {{ request()->routeIs('super-admin.settings.email*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>Pengaturan Email</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ $isSelfHostedApp ? route('super-admin.settings.license') : route('super-admin.settings.license-public-key') }}" class="nav-link {{ $isSelfHostedApp ? request()->routeIs('super-admin.settings.license*') : request()->routeIs('super-admin.settings.license-public-key*') }}">
                            <i class="nav-icon fas fa-key"></i>
                            <p>{{ $isSelfHostedApp ? 'Lisensi Sistem' : 'Public Key Lisensi' }}</p>
                        </a>
                    </li>
                    @if($isSelfHostedApp)
                    <li class="nav-item">
                        <a href="{{ route('super-admin.settings.app-update') }}" class="nav-link {{ request()->routeIs('super-admin.settings.app-update*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cloud-download-alt"></i>
                            <p>App Update</p>
                        </a>
                    </li>
                    @endif
                    @if(!$isSelfHostedApp)
                    @includeIf('self-hosted-license.partials.admin-nav-item')
                    @endif
                    <li class="nav-item has-treeview {{ request()->routeIs('logs.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Log Aplikasi <i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('logs.login') }}" class="nav-link {{ request()->routeIs('logs.login') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Login</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.activity') }}" class="nav-link {{ request()->routeIs('logs.activity') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Aktivitas</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{ route('help.index') }}" class="nav-link">
                            <i class="nav-icon fas fa-question-circle"></i>
                            <p>Bantuan</p>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
        </div>
    </aside>

    <div class="content-wrapper">
        @if(!$isSelfHostedApp && $impersonatingTenant)
        <div style="background:#c0392b;color:#fff;padding:8px 16px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span style="font-size:0.9rem;">
                <i class="fas fa-user-secret mr-2"></i>
                Sedang melihat sebagai: <strong>{{ $impersonatingTenant->name }}</strong>{{ $impersonatingTenant->company_name ? ' (' . $impersonatingTenant->company_name . ')' : '' }}
            </span>
            <form action="{{ route('super-admin.stop-impersonating') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-sm" style="background:#fff;color:#c0392b;font-weight:600;border:none;">
                    <i class="fas fa-times mr-1"></i> Keluar dari Mode Ini
                </button>
            </form>
        </div>
        @endif
        @includeIf('self-hosted-license.partials.admin-alert')
        @if(!$isSelfHostedApp && $subscriptionExpired)
        <section class="content-header pb-0">
            <div class="container-fluid">
                <div class="alert alert-danger alert-dismissible mb-0">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Langganan Anda telah berakhir.</strong>
                    Akses fitur dibatasi. Silakan perpanjang untuk menggunakan semua fitur.
                    <a href="{{ route('subscription.plans') }}" class="btn btn-sm btn-danger ml-2">
                        <i class="fas fa-shopping-cart"></i> Perpanjang Sekarang
                    </a>
                </div>
            </div>
        </section>
        @elseif(!$isSelfHostedApp && $subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
        <section class="content-header pb-0">
            <div class="container-fluid">
                <div class="alert {{ $subscriptionDaysLeft <= 3 ? 'alert-danger' : 'alert-warning' }} alert-dismissible mb-0">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    <i class="fas fa-bell mr-1"></i>
                    <strong>Peringatan:</strong>
                    @if($subscriptionDaysLeft <= 0)
                        Langganan Anda habis hari ini!
                    @else
                        Langganan Anda akan berakhir dalam <strong>{{ $subscriptionDaysLeft }} hari</strong>.
                    @endif
                    <a href="{{ route('subscription.renew') }}" class="btn btn-sm {{ $subscriptionDaysLeft <= 3 ? 'btn-danger' : 'btn-warning' }} ml-2">
                        <i class="fas fa-redo"></i> Perpanjang
                    </a>
                </div>
            </div>
        </section>
        @endif

        @if (session('status'))
            <script>window.__flashStatus = {{ Js::from(session('status')) }};</script>
        @endif
        @if (session('success'))
            <script>window.__flashStatus = {{ Js::from(session('success')) }};</script>
        @endif
        @if (session('error'))
            <script>window.__flashError = {{ Js::from(session('error')) }};</script>
        @endif
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>Rafen</strong> &copy; {{ date('Y') }} &mdash; Hardi Agunadi
        <div class="float-right d-none d-sm-inline-block">
            Support ROS 7.x / 6.x
            @if($serverLoadTimeMs !== null)
                <span class="text-muted ml-2">| Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</span>
            @endif
        </div>
    </footer>
</div>

<div class="modal fade" id="invoice-filter-modal" tabindex="-1" aria-labelledby="invoice-filter-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoice-filter-modal-label">Semua Tagihan (Invoice)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('invoices.index') }}">
                    <div class="form-group">
                        <label for="invoice-service-type">Tipe Service</label>
                        <select class="form-control" id="invoice-service-type" name="service_type">
                            <option value="">- Semua -</option>
                            <option value="pppoe">PPPoE</option>
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">Hotspot</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="invoice-owner">Owner Data</label>
                        <select class="form-control" id="invoice-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="all-bills-modal" tabindex="-1" aria-labelledby="all-bills-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="all-bills-modal-label">Semua Tagihan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('reports.income') }}">
                    <div class="form-group">
                        <label for="modal-service-type">Tipe Service</label>
                        <select class="form-control" id="modal-service-type" name="service_type">
                            <option value="">- Semua -</option>
                            <option value="pppoe">PPPoE</option>
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">HOTSPOT</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="modal-owner">Owner Data</label>
                        <select class="form-control" id="modal-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="period-bills-modal" tabindex="-1" aria-labelledby="period-bills-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="period-bills-modal-label">Tagihan Periode</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('reports.income') }}">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="period-date-from">Dari Tanggal</label>
                            <input type="date" class="form-control" id="period-date-from" name="date_from" value="{{ now()->subMonthNoOverflow()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="period-date-to">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="period-date-to" name="date_to" value="{{ now()->subMonthNoOverflow()->endOfMonth()->toDateString() }}">
                        </div>
                    </div>
                    <div class="text-muted mb-3"><em>Tanggal jatuh tempo pelanggan</em></div>
                    <div class="form-group">
                        <label for="period-service-type">Tipe Service</label>
                        <select class="form-control" id="period-service-type" name="service_type">
                            <option value="">- Semua Transaksi -</option>
                            <option value="pppoe">PPPoE</option>
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">HOTSPOT</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="period-owner">Owner Data</label>
                        <select class="form-control" id="period-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="text-right mt-4">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
<script>
window.AppSelect = (function () {
    function canUseSelect2() {
        return typeof window.jQuery !== 'undefined'
            && typeof window.jQuery.fn !== 'undefined'
            && typeof window.jQuery.fn.select2 === 'function';
    }

    function shouldEnhance(selectElement) {
        if (!selectElement) {
            return false;
        }
        if (selectElement.dataset.nativeSelect === 'true') {
            return false;
        }
        if (selectElement.classList.contains('select2-hidden-accessible')) {
            return false;
        }
        if (selectElement.closest('.dataTables_wrapper')) {
            return false;
        }

        return true;
    }

    function buildConfig(selectElement) {
        var totalOptions = selectElement.options ? selectElement.options.length : 0;
        var parentModal = selectElement.closest('.modal');
        var config = {
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: totalOptions > 8 ? 0 : Infinity,
        };

        if (parentModal) {
            config.dropdownParent = window.jQuery(parentModal);
        }

        return config;
    }

    function initSelect(selectElement) {
        if (!canUseSelect2() || !shouldEnhance(selectElement)) {
            return;
        }

        window.jQuery(selectElement).select2(buildConfig(selectElement));
    }

    function initAll(context) {
        if (!canUseSelect2()) {
            return;
        }

        var root = context || document;
        root.querySelectorAll('select').forEach(function (selectElement) {
            initSelect(selectElement);
        });
    }

    function refresh(selectElement) {
        if (!canUseSelect2() || !selectElement) {
            return;
        }

        var $select = window.jQuery(selectElement);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        initSelect(selectElement);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAll(document);
    });

    document.addEventListener('shown.bs.modal', function (event) {
        initAll(event.target);
    });

    document.addEventListener('rafen:select-refresh', function (event) {
        if (event.detail && event.detail.element) {
            refresh(event.detail.element);
            return;
        }
        initAll(event.detail && event.detail.root ? event.detail.root : document);
    });

    return {
        initAll: initAll,
        refresh: refresh,
    };
})();
</script>
<script>
// ── Global AJAX helpers ────────────────────────────────────────────────────
window.AppAjax = (function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    function showToast(message, type) {
        var container = document.getElementById('app-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'app-toast-container';
            container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;min-width:260px;';
            document.body.appendChild(container);
        }
        var colors = { success: '#28a745', danger: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
        var toast = document.createElement('div');
        toast.style.cssText = 'background:' + (colors[type] || '#333') + ';color:#fff;padding:12px 18px;border-radius:6px;margin-bottom:8px;box-shadow:0 2px 8px rgba(0,0,0,.25);font-size:14px;';
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () { toast.style.opacity = '0'; toast.style.transition = 'opacity .4s'; setTimeout(function () { toast.remove(); }, 400); }, 4000);
    }

    function request(method, url, body) {
        var opts = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        };
        if (body instanceof URLSearchParams) {
            opts.body = body;
        } else if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) return Promise.reject(data);
                return data;
            });
        });
    }

    function formRequest(method, url, formData) {
        var params = new URLSearchParams();
        formData.forEach(function (val, key) { params.append(key, val); });
        if (method !== 'POST') params.append('_method', method);
        return request('POST', url, params);
    }

    // Delegated delete handler — call once after DOM ready
    function initDeleteButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ajax-delete]');
            if (!btn) return;
            var msg = btn.dataset.confirm || 'Hapus data ini?';
            if (!confirm(msg)) return;

            var url = btn.dataset.ajaxDelete;
            var row = btn.closest('tr');
            btn.disabled = true;

            request('DELETE', url).then(function (data) {
                showToast(data.message || data.status || 'Data berhasil dihapus.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
                if (row) {
                    row.style.transition = 'opacity .3s';
                    row.style.opacity = '0';
                    setTimeout(function () { row.remove(); }, 300);
                }
            }).catch(function (err) {
                btn.disabled = false;
                showToast((err && (err.error || err.message)) || 'Gagal menghapus data.', 'danger');
            });
        });
    }

    function initPostButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ajax-post]');
            if (!btn) return;
            var msg = btn.dataset.confirm;
            if (msg && !confirm(msg)) return;

            var url = btn.dataset.ajaxPost;
            btn.disabled = true;
            var origText = btn.innerHTML;
            if (btn.dataset.loadingText) btn.innerHTML = btn.dataset.loadingText;

            request('POST', url).then(function (data) {
                btn.disabled = false;
                btn.innerHTML = origText;
                showToast(data.message || data.status || 'Berhasil.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
                if (btn.dataset.reloadRow) {
                    var row = btn.closest('tr');
                    if (row && data.row_html) row.outerHTML = data.row_html;
                }
            }).catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = origText;
                showToast((err && (err.error || err.message)) || 'Gagal.', 'danger');
            });
        });
    }

    function initNavbarGlobalSearch() {
        var searchForm = document.getElementById('navbar-global-search-form');
        if (!searchForm) {
            return;
        }

        var searchInput = document.getElementById('navbar-search-keyword');
        var targetSelect = document.getElementById('navbar-search-target');
        var suggestionsList = document.getElementById('navbar-search-suggestions');
        var autocompleteTimer = null;

        function selectedTarget() {
            return targetSelect ? targetSelect.value : 'pppoe';
        }

        function autocompleteUrlByTarget() {
            if (selectedTarget() === 'hotspot' && searchForm.dataset.hotspotAutocomplete) {
                return searchForm.dataset.hotspotAutocomplete;
            }

            return searchForm.dataset.pppoeAutocomplete || '';
        }

        function clearAutocompleteOptions() {
            if (!suggestionsList) {
                return;
            }

            suggestionsList.innerHTML = '';
        }

        function renderAutocompleteOptions(items) {
            if (!suggestionsList) {
                return;
            }

            clearAutocompleteOptions();

            items.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.value || '';

                if (item.label) {
                    option.label = item.label;
                }

                suggestionsList.appendChild(option);
            });
        }

        function fetchAutocomplete() {
            if (!searchInput) {
                return;
            }

            var keyword = searchInput.value.trim();
            if (keyword.length < 2) {
                clearAutocompleteOptions();

                return;
            }

            var endpoint = autocompleteUrlByTarget();
            if (!endpoint) {
                return;
            }

            window.fetch(endpoint + '?q=' + encodeURIComponent(keyword), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function (response) { return response.ok ? response.json() : { data: [] }; })
                .then(function (payload) {
                    renderAutocompleteOptions(Array.isArray(payload.data) ? payload.data : []);
                })
                .catch(function () {
                    clearAutocompleteOptions();
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (autocompleteTimer) {
                    window.clearTimeout(autocompleteTimer);
                }

                autocompleteTimer = window.setTimeout(fetchAutocomplete, 180);
            });
        }

        if (targetSelect) {
            targetSelect.addEventListener('change', function () {
                clearAutocompleteOptions();
                fetchAutocomplete();
            });
        }

        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var pppoeAction = searchForm.dataset.pppoeAction || searchForm.getAttribute('action');
            var hotspotAction = searchForm.dataset.hotspotAction || pppoeAction;
            var target = selectedTarget();
            var action = target === 'hotspot' ? hotspotAction : pppoeAction;
            var keyword = searchInput ? searchInput.value.trim() : '';
            var targetUrl = new URL(action, window.location.origin);

            if (keyword !== '') {
                targetUrl.searchParams.set('search', keyword);
            }
            targetUrl.searchParams.set('target', target);

            window.location.href = targetUrl.toString();
        });
    }

document.addEventListener('DOMContentLoaded', function () {
        initDeleteButtons();
        initPostButtons();
        initNavbarGlobalSearch();
        if (window.__flashStatus) { showToast(window.__flashStatus, 'success'); window.__flashStatus = null; }
        if (window.__flashError)  { showToast(window.__flashError,  'danger');  window.__flashError  = null; }
    });

    return { request: request, formRequest: formRequest, showToast: showToast };
})();
</script>
@stack('scripts')
<script>$(function(){ $('[data-toggle="tooltip"]').tooltip(); });</script>
<script>
// PWA: Service Worker registration + install prompt
(function () {
    var DISMISS_TTL_MS = 3 * 24 * 60 * 60 * 1000;
    var DISMISS_STORAGE_KEY = 'rafen-pwa-install-dismissed-until';
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }

    var deferredPrompt = null;
    var currentBannerMode = 'native';

    function getBanner() {
        return document.getElementById('pwa-install-banner');
    }

    function getMessage() {
        return document.getElementById('pwa-install-message');
    }

    function getButton() {
        return document.getElementById('pwa-install-btn');
    }

    function isStandaloneMode() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function getDismissedUntil() {
        try {
            return Number(window.localStorage.getItem(DISMISS_STORAGE_KEY) || 0);
        } catch (error) {
            return 0;
        }
    }

    function rememberDismiss() {
        try {
            window.localStorage.setItem(DISMISS_STORAGE_KEY, String(Date.now() + DISMISS_TTL_MS));
        } catch (error) {}
    }

    function clearDismiss() {
        try {
            window.localStorage.removeItem(DISMISS_STORAGE_KEY);
        } catch (error) {}
    }

    function canShowBanner() {
        return !isStandaloneMode() && getDismissedUntil() <= Date.now();
    }

    function hideBanner() {
        var banner = getBanner();
        if (banner) {
            banner.style.display = 'none';
        }
    }

    function setBannerState(messageHtml, buttonLabel, mode, alertText) {
        var banner = getBanner();
        var message = getMessage();
        var button = getButton();

        if (!banner || !message || !button || !canShowBanner()) {
            return;
        }

        message.innerHTML = messageHtml;
        button.textContent = buttonLabel;
        button.dataset.mode = mode;
        button.dataset.alertText = alertText || '';
        currentBannerMode = mode;
        banner.style.display = 'flex';
    }

    function showNativeInstallBanner() {
        if (!deferredPrompt) {
            return;
        }

        clearDismiss();
        setBannerState(
            '<i class="fas fa-mobile-alt" style="margin-right:6px;"></i>Pasang Rafen Manager di perangkat Anda',
            'Pasang',
            'native',
            ''
        );
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showNativeInstallBanner();
    });
    window.addEventListener('appinstalled', function () {
        clearDismiss();
        hideBanner();
        deferredPrompt = null;
    });
    document.addEventListener('DOMContentLoaded', function () {
        var btn = getButton();
        if (btn) {
            btn.addEventListener('click', function () {
                if (currentBannerMode === 'native' && deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choiceResult) {
                        if (!choiceResult || choiceResult.outcome !== 'accepted') {
                            rememberDismiss();
                        } else {
                            clearDismiss();
                        }
                        deferredPrompt = null;
                        hideBanner();
                    });
                    return;
                }
            });
        }
        var dismiss = document.getElementById('pwa-install-dismiss');
        if (dismiss) {
            dismiss.addEventListener('click', function () {
                rememberDismiss();
                hideBanner();
            });
        }
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible' || !canShowBanner()) {
            return;
        }

        if (deferredPrompt) {
            showNativeInstallBanner();
            return;
        }
    });
})();
</script>
<script>
// ── Web Push Subscription — Staff ─────────────────────────────────────────────
(function () {
    var VAPID_PUBLIC_KEY = '{{ $vapidPublicKey ?? "" }}';
    var SUBSCRIBE_URL    = '{{ route("push.subscribe") }}';
    var UNSUBSCRIBE_URL  = '{{ route("push.unsubscribe") }}';

    if (!VAPID_PUBLIC_KEY || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var output = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) { output[i] = rawData.charCodeAt(i); }
        return output;
    }

    function arrayBufferToBase64(buffer) {
        return btoa(String.fromCharCode.apply(null, new Uint8Array(buffer)));
    }

    function serializeSubscription(sub) {
        return {
            endpoint: sub.endpoint,
            keys: {
                p256dh: arrayBufferToBase64(sub.getKey('p256dh')),
                auth:   arrayBufferToBase64(sub.getKey('auth')),
            },
        };
    }

    function setButtonState(subscribed) {
        var nav = document.getElementById('push-subscribe-nav');
        var btn = document.getElementById('push-subscribe-btn');
        if (!nav || !btn) return;
        nav.style.display = '';
        if (subscribed) {
            btn.innerHTML = '<i class="far fa-bell text-success"></i>';
            btn.title = 'Notifikasi Push Aktif (klik untuk nonaktifkan)';
            btn.dataset.subscribed = '1';
        } else {
            btn.innerHTML = '<i class="far fa-bell-slash"></i>';
            btn.title = 'Aktifkan Notifikasi Push';
            btn.dataset.subscribed = '0';
        }
    }

    function doSubscribe() {
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });
        }).then(function (sub) {
            return window.AppAjax.request('POST', SUBSCRIBE_URL, serializeSubscription(sub)).then(function () { return sub; });
        }).then(function () {
            setButtonState(true);
        });
    }

    var isPwa = window.matchMedia('(display-mode: standalone)').matches
             || window.navigator.standalone === true;

    function showPushInviteBanner() {
        if (document.getElementById('push-invite-banner')) return;
        var banner = document.createElement('div');
        banner.id = 'push-invite-banner';
        banner.style.cssText = 'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);z-index:9999;background:#0f172a;color:#fff;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem;box-shadow:0 4px 20px rgba(0,0,0,.35);max-width:360px;width:calc(100% - 2rem);font-size:.875rem;';
        banner.innerHTML = '<i class="fas fa-bell" style="color:#facc15;font-size:1.1rem;flex-shrink:0;"></i>'
            + '<span style="flex:1;line-height:1.4;">Aktifkan notifikasi untuk info tagihan & pesan masuk.</span>'
            + '<button id="push-invite-yes" style="background:#1367a4;color:#fff;border:none;border-radius:6px;padding:.35rem .75rem;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap;">Aktifkan</button>'
            + '<button id="push-invite-no" style="background:transparent;color:#94a3b8;border:none;padding:.35rem .5rem;cursor:pointer;font-size:1rem;line-height:1;" title="Tutup">&times;</button>';
        document.body.appendChild(banner);

        document.getElementById('push-invite-no').addEventListener('click', function () {
            banner.remove();
        });

        document.getElementById('push-invite-yes').addEventListener('click', function () {
            banner.remove();
            // Permission request di sini sudah dipicu oleh user gesture (klik)
            Notification.requestPermission().then(function (permission) {
                if (permission === 'granted') {
                    doSubscribe().then(function () {
                        window.AppAjax.showToast('Notifikasi push berhasil diaktifkan!', 'success');
                    }).catch(function () {});
                } else if (permission === 'denied') {
                    var nav2 = document.getElementById('push-subscribe-nav');
                    var btn2 = document.getElementById('push-subscribe-btn');
                    if (nav2 && btn2) {
                        nav2.style.display = '';
                        btn2.innerHTML = '<i class="fas fa-bell-slash text-danger"></i>';
                        btn2.title = 'Notifikasi diblokir — klik untuk panduan';
                        btn2.dataset.subscribed = 'denied';
                    }
                }
            });
        });
    }

    function showDeniedGuide() {
        var existing = document.getElementById('push-denied-guide');
        if (existing) { existing.style.display = 'flex'; return; }

        var steps = isPwa ? [
            '<li>Buka <b>Pengaturan</b> Android</li>',
            '<li>Pilih <b>Aplikasi</b> → cari <b>Rafen Manager</b> (atau nama situs ini)</li>',
            '<li>Ketuk <b>Notifikasi</b> → aktifkan</li>',
            '<li>Kembali ke app ini lalu muat ulang</li>',
        ] : [
            '<li>Ketuk ikon <b>kunci / info</b> di address bar browser</li>',
            '<li>Pilih <b>Izin situs</b> atau <b>Pengaturan situs</b></li>',
            '<li>Cari <b>Notifikasi</b> → ubah ke <b>Izinkan</b></li>',
            '<li>Muat ulang halaman ini</li>',
        ];

        var el = document.createElement('div');
        el.id = 'push-denied-guide';
        el.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:1rem;';
        el.innerHTML = [
            '<div style="background:#fff;border-radius:12px;max-width:360px;width:100%;padding:1.5rem;box-shadow:0 8px 32px rgba(0,0,0,.2);">',
            '<h5 style="margin:0 0 .75rem;font-size:1rem;color:#0f172a;"><i class="fas fa-bell-slash text-danger mr-2"></i>Notifikasi Diblokir</h5>',
            '<p style="font-size:.875rem;color:#475569;margin:0 0 1rem;">Aktifkan ulang notifikasi dengan langkah berikut:</p>',
            '<ol style="font-size:.875rem;color:#334155;padding-left:1.2rem;margin:0 0 1rem;line-height:1.9;">',
        ].concat(steps).concat([
            '</ol>',
            '<div style="display:flex;gap:.5rem;">',
            '<button onclick="document.getElementById(\'push-denied-guide\').style.display=\'none\'" style="flex:1;padding:.5rem;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:.875rem;">Tutup</button>',
            '<button onclick="location.reload()" style="flex:1;padding:.5rem;border:none;border-radius:6px;background:#1367a4;color:#fff;cursor:pointer;font-size:.875rem;font-weight:600;">Muat Ulang</button>',
            '</div>',
            '</div>',
        ]).join('');
        document.body.appendChild(el);
    }

    var pushInviteTimeout = null;
    var isSyncingPushState = false;

    function queuePushInviteBanner() {
        if (pushInviteTimeout) {
            window.clearTimeout(pushInviteTimeout);
        }

        pushInviteTimeout = window.setTimeout(function () {
            showPushInviteBanner();
        }, 3000);
    }

    function syncPushSubscriptionState(showInvite) {
        if (isSyncingPushState) {
            return;
        }

        isSyncingPushState = true;

        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                if (Notification.permission === 'denied') {
                    var nav = document.getElementById('push-subscribe-nav');
                    var btn = document.getElementById('push-subscribe-btn');
                    if (nav && btn) {
                        nav.style.display = '';
                        btn.innerHTML = '<i class="fas fa-bell-slash text-danger"></i>';
                        btn.title = 'Notifikasi diblokir — klik untuk panduan';
                        btn.dataset.subscribed = 'denied';
                    }
                    return;
                }

                setButtonState(!!sub);

                if (sub) {
                    return window.AppAjax.request('POST', SUBSCRIBE_URL, serializeSubscription(sub)).catch(function () {});
                }

                if (showInvite && Notification.permission === 'default') {
                    queuePushInviteBanner();
                }
            });
        }).finally(function () {
            isSyncingPushState = false;
        });
    }

    syncPushSubscriptionState(true);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            syncPushSubscriptionState(false);
        }
    });

    window.addEventListener('focus', function () {
        syncPushSubscriptionState(false);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#push-subscribe-btn');
        if (!btn) return;

        if (btn.dataset.subscribed === 'denied') {
            showDeniedGuide();
            return;
        }

        if (btn.dataset.subscribed === '1') {
            if (!confirm('Nonaktifkan notifikasi push?\nAnda tidak akan menerima notifikasi tagihan, pesan WA, dan update layanan.')) return;
            navigator.serviceWorker.ready.then(function (reg) {
                reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return;
                    var endpoint = sub.endpoint;
                    sub.unsubscribe().then(function () {
                        return window.AppAjax.request('DELETE', UNSUBSCRIBE_URL, { endpoint: endpoint });
                    }).then(function () {
                        setButtonState(false);
                        window.AppAjax.showToast('Notifikasi push dinonaktifkan.', 'warning');
                    }).catch(function () {
                        window.AppAjax.showToast('Gagal menonaktifkan notifikasi.', 'danger');
                    });
                });
            });
            return;
        }

        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                showDeniedGuide();
                return;
            }
            doSubscribe().then(function () {
                window.AppAjax.showToast('Notifikasi push berhasil diaktifkan!', 'success');
            }).catch(function (err) {
                console.error('Push subscribe error:', err);
                window.AppAjax.showToast('Gagal mengaktifkan notifikasi push.', 'danger');
            });
        });
    });
})();
</script>

{{-- Contact Support Floating Button --}}
<style>
#contact-fab {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 1050;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #1a6db5;
    color: #fff;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(0,0,0,.25);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s, transform .15s;
}
#contact-fab:hover { background: #155fa0; transform: scale(1.07); }
#contact-fab svg { width: 22px; height: 22px; pointer-events: none; }

#contact-overlay {
    position: fixed;
    bottom: 5.5rem;
    right: 1.5rem;
    z-index: 1060;
    width: 300px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,.18);
    overflow: hidden;
    display: none;
    transform-origin: bottom right;
    animation: contactPopIn .18s ease;
}
@keyframes contactPopIn {
    from { opacity: 0; transform: scale(.85); }
    to   { opacity: 1; transform: scale(1); }
}
#contact-overlay .co-header {
    background: #1a6db5;
    color: #fff;
    padding: .875rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
#contact-overlay .co-header strong { font-size: .9375rem; }
#contact-overlay .co-header button {
    background: none; border: none; color: #fff;
    cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0;
    opacity: .8;
}
#contact-overlay .co-header button:hover { opacity: 1; }
#contact-overlay .co-body { padding: .75rem 1rem; }
#contact-overlay .co-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: .6rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: .84rem;
    color: #374151;
}
#contact-overlay .co-item:last-child { border-bottom: none; }
#contact-overlay .co-item svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; color: #1a6db5; }
#contact-overlay .co-item a { color: #1a6db5; text-decoration: none; }
#contact-overlay .co-item a:hover { text-decoration: underline; }
#contact-overlay .co-label { font-size: .7rem; color: #6b7280; margin-bottom: 1px; }
#contact-overlay .co-footer {
    padding: .625rem 1rem .875rem;
    text-align: center;
}
#contact-overlay .co-footer a {
    font-size: .78rem;
    color: #6b7280;
    text-decoration: none;
}
#contact-overlay .co-footer a:hover { color: #1a6db5; }

#contact-overlay .co-chat-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: .6rem 1rem;
    background: #25d366;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background .15s;
    margin-bottom: .5rem;
}
#contact-overlay .co-chat-btn:hover { background: #1ebe5d; color: #fff; text-decoration: none; }
#contact-overlay .co-chat-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
</style>

<button id="contact-fab" title="Kontak Support" aria-label="Kontak Support">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>

<div id="contact-overlay" role="dialog" aria-label="Kontak Support">
    <div class="co-header">
        <strong>Kontak Support</strong>
        <button id="contact-overlay-close" aria-label="Tutup">&times;</button>
    </div>
    <div class="co-body">
        <div class="co-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                <path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M22 6L12 13L2 6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                <div class="co-label">Email</div>
                <a href="mailto:hardiagunadi@gmail.com">hardiagunadi@gmail.com</a>
            </div>
        </div>
        <div class="co-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                <path d="M22 16.92V19.92C22 20.48 21.76 21.02 21.33 21.4C20.91 21.78 20.34 21.97 19.77 21.92C16.56 21.59 13.48 20.53 10.74 18.85C8.2 17.31 6.05 15.17 4.51 12.62C2.82 9.87 1.77 6.78 1.44 3.55C1.39 2.98 1.58 2.42 1.95 2C2.33 1.59 2.86 1.34 3.42 1.34H6.42C7.4 1.33 8.24 2.02 8.42 3C8.57 3.84 8.82 4.67 9.15 5.46C9.42 6.16 9.23 6.96 8.68 7.47L7.39 8.76C8.81 11.39 10.97 13.55 13.6 14.97L14.89 13.68C15.4 13.13 16.2 12.94 16.9 13.21C17.69 13.54 18.52 13.79 19.36 13.94C20.35 14.12 21.05 14.97 21 15.97L22 16.92Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                <div class="co-label">WhatsApp / Telepon</div>
                <a href="https://wa.me/6282220243698" target="_blank">+62 822-2024-3698</a>
            </div>
        </div>
        <div class="co-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                <path d="M21 10C21 17 12 23 12 23C12 23 3 17 3 10C3 7.61 3.95 5.32 5.64 3.64C7.32 1.95 9.61 1 12 1C14.39 1 16.68 1.95 18.36 3.64C20.05 5.32 21 7.61 21 10Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="10" r="3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                <div class="co-label">Alamat</div>
                Dusun Tanjungsari, Desa Binangun,<br>Kec. Watumalang, Wonosobo
            </div>
        </div>
    </div>
    <div class="co-body" style="padding-top:0; border-top: 1px solid #f0f0f0;">
        <div style="padding: .75rem 0 .25rem;">
            <a href="https://wa.me/6282220243698?text={{ urlencode('Halo, saya butuh bantuan support RAFEN.') }}"
               target="_blank"
               rel="noopener"
               class="co-chat-btn">
                <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                Chat via WhatsApp Sekarang
            </a>
        </div>
    </div>
    <div class="co-footer">
        <a href="{{ url('/contact') }}" target="_blank">Lihat halaman kontak lengkap &rarr;</a>
    </div>
</div>

<script>
(function () {
    var fab     = document.getElementById('contact-fab');
    var overlay = document.getElementById('contact-overlay');
    var close   = document.getElementById('contact-overlay-close');

    fab.addEventListener('click', function () {
        var isOpen = overlay.style.display === 'block';
        overlay.style.display = isOpen ? 'none' : 'block';
    });

    close.addEventListener('click', function () {
        overlay.style.display = 'none';
    });

    document.addEventListener('click', function (e) {
        if (!overlay.contains(e.target) && e.target !== fab && !fab.contains(e.target)) {
            overlay.style.display = 'none';
        }
    });
})();
</script>
</body>
</html>
