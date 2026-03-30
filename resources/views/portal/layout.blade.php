<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $portalRouteParams = request()->filled('slug') ? ['slug' => request()->query('slug')] : [];
        $portalIcon32Url = route('portal.icon', array_merge($portalRouteParams, ['size' => 32]));
        $portalIcon180Url = route('portal.icon', array_merge($portalRouteParams, ['size' => 180]));
        $portalIcon192Url = route('portal.icon', array_merge($portalRouteParams, ['size' => 192]));
        $portalBrandLogoUrl = isset($tenantSettings) && $tenantSettings?->business_logo
            ? asset('storage/' . $tenantSettings->business_logo).'?v='.($tenantSettings->updated_at?->timestamp ?? 0)
            : null;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- PWA --}}
    <link rel="manifest" href="{{ route('portal.manifest', $portalRouteParams) }}">
    <meta name="theme-color" content="#0f6b95">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ ($tenantSettings ?? null)?->business_name ?? 'Rafen Portal' }}">
    <link rel="icon" href="{{ $portalIcon32Url }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $portalIcon32Url }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $portalIcon180Url }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ $portalIcon192Url }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ $portalIcon192Url }}">
    <title>@yield('title', 'Portal Pelanggan') @if(isset($tenantSettings) && $tenantSettings?->business_name) — {{ $tenantSettings->business_name }}@endif</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --brand-start: #0a3e68;
            --brand-mid:   #0f6b95;
            --brand-end:   #0c8a8f;
        }
        body {
            background: #f4f7fb;
            min-height: 100vh;
            font-family: 'Source Sans Pro', sans-serif;
        }

        /* ── Navbar ── */
        .portal-navbar {
            background: linear-gradient(105deg, var(--brand-start) 0%, var(--brand-mid) 55%, var(--brand-end) 100%);
            padding: .7rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(10,62,104,.35);
        }
        .portal-navbar .brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .portal-navbar .brand img {
            max-height: 36px;
            max-width: 132px;
            border-radius: 6px;
            background: rgba(255,255,255,.92);
            object-fit: contain;
            padding: 4px;
        }
        .portal-navbar .brand .brand-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.18);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }

        .portal-navbar .nav-links { display: flex; align-items: center; gap: .25rem; }
        .portal-navbar .nav-links a {
            color: rgba(255,255,255,.8);
            text-decoration: none;
            font-size: .875rem;
            padding: .35rem .65rem;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }
        .portal-navbar .nav-links a:hover,
        .portal-navbar .nav-links a.active {
            color: #fff;
            background: rgba(255,255,255,.18);
        }
        .portal-navbar .nav-links a.active { font-weight: 600; }
        .portal-navbar .nav-links .btn-logout {
            color: rgba(255,255,255,.75);
            border: 1px solid rgba(255,255,255,.3);
            margin-left: .25rem;
        }
        .portal-navbar .nav-links .btn-logout:hover {
            background: rgba(255,255,255,.18);
            color: #fff;
        }
        @media(max-width:576px) {
            .portal-navbar .nav-links a { padding: .3rem .45rem; font-size: .8rem; }
            .portal-navbar .brand span { display: none; }
        }

        /* ── Content ── */
        .portal-main {
            padding: 1.75rem 1rem;
            max-width: 980px;
            margin: 0 auto;
        }

        /* ── Cards ── */
        .card { border: none; box-shadow: 0 1px 6px rgba(0,0,0,.07); border-radius: 10px; }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            font-size: .92rem;
            letter-spacing: .01em;
        }
        .card-header.bg-primary,
        .card-header.bg-dark {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-mid)) !important;
            color: #fff !important;
            border-bottom: none;
        }

        /* ── Buttons ── */
        .btn-primary {
            background: linear-gradient(90deg, var(--brand-mid), var(--brand-end));
            border: none;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-mid));
            border: none;
        }

        /* ── Footer ── */
        footer {
            border-top: 1px solid #e2eaf4;
            margin-top: 2rem;
            color: #6b7a90;
            font-size: .82rem;
            padding: 1rem;
        }

        /* ── Page header ── */
        .portal-page-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--brand-start);
            margin-bottom: 1.25rem;
        }
        .portal-page-title i { margin-right: .4rem; color: var(--brand-mid); }
    </style>
    @stack('css')
</head>
<body>
    {{-- PWA install banner (shown only on Android Chrome when installable) --}}
    <div id="pwa-portal-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:10000;background:linear-gradient(90deg,#0a3e68,#0f6b95);color:#fff;padding:10px 16px;align-items:center;justify-content:space-between;font-size:14px;box-shadow:0 -2px 8px rgba(10,62,104,.4);">
        <span id="pwa-portal-message"><i class="fas fa-mobile-alt" style="margin-right:6px;"></i>Pasang Portal Pelanggan di HP Anda</span>
        <span>
            <button id="pwa-portal-install-btn" style="background:#fff;color:#0a3e68;border:none;padding:5px 14px;border-radius:4px;font-weight:600;cursor:pointer;margin-right:8px;">Pasang</button>
            <button id="pwa-portal-dismiss" style="background:transparent;color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.4);padding:5px 10px;border-radius:4px;cursor:pointer;">Nanti</button>
        </span>
    </div>
    <nav class="portal-navbar">
        <div class="brand">
            @if(isset($tenantSettings) && $tenantSettings?->business_logo)
            <img src="{{ $portalBrandLogoUrl }}" alt="{{ $tenantSettings->business_name ?: 'Portal Pelanggan' }} Logo">
            @else
            <span class="brand-icon"><i class="fas fa-wifi"></i></span>
            @endif
            <span>{{ ($tenantSettings ?? null)?->business_name ?? 'Portal Pelanggan' }}</span>
        </div>

        @if(request()->cookie('portal_session'))
        <div class="nav-links">
            <a href="{{ route('portal.dashboard', $portalRouteParams) }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i><span class="d-none d-sm-inline"> Dashboard</span>
            </a>
            <a href="{{ route('portal.invoices', $portalRouteParams) }}" class="{{ request()->routeIs('portal.invoices') ? 'active' : '' }}">
                <i class="fas fa-file-invoice"></i><span class="d-none d-sm-inline"> Tagihan</span>
            </a>
            <a href="{{ route('portal.account', $portalRouteParams) }}" class="{{ request()->routeIs('portal.account') ? 'active' : '' }}">
                <i class="fas fa-user"></i><span class="d-none d-sm-inline"> Akun</span>
            </a>
            <button id="portal-push-btn"
                    style="display:none;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);padding:.3rem .65rem;border-radius:6px;cursor:pointer;font-size:.82rem;"
                    title="Aktifkan Notifikasi">
                <i class="fas fa-bell"></i>
            </button>
            <a href="#" class="btn-logout" onclick="document.getElementById('logout-form').submit();return false;">
                <i class="fas fa-sign-out-alt"></i><span class="d-none d-sm-inline"> Keluar</span>
            </a>
            <form id="logout-form" action="{{ route('portal.logout', $portalRouteParams) }}" method="POST" style="display:none;">@csrf</form>
        </div>
        @endif
    </nav>

    <div class="portal-main">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }} <button type="button" class="close" data-dismiss="alert">&times;</button></div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }} <button type="button" class="close" data-dismiss="alert">&times;</button></div>
        @endif

        @yield('content')
    </div>

    <footer class="text-center">
        &copy; {{ date('Y') }} {{ ($tenantSettings ?? null)?->business_name ?? 'Portal Pelanggan' }}
        @if(($tenantSettings ?? null)?->business_phone)
         &middot; <i class="fas fa-phone-alt fa-xs"></i> {{ $tenantSettings->business_phone }}
        @endif
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('js')
<script>
// PWA: Service Worker registration + install prompt
(function () {
    var DISMISS_TTL_MS = 3 * 24 * 60 * 60 * 1000;
    var DISMISS_STORAGE_KEY = 'rafen-portal-pwa-install-dismissed-until';
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw-portal.js', { scope: '/portal/' }).catch(function () {});
        });
    }

    var deferredPrompt = null;
    var currentBannerMode = 'native';
    var isLoggedIn = document.cookie.split(';').some(function (c) { return c.trim().startsWith('portal_session='); });

    function getBanner() {
        return document.getElementById('pwa-portal-banner');
    }

    function getMessage() {
        return document.getElementById('pwa-portal-message');
    }

    function getButton() {
        return document.getElementById('pwa-portal-install-btn');
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
            '<i class="fas fa-mobile-alt" style="margin-right:6px;"></i>Pasang Portal Pelanggan di HP Anda',
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
        var dismiss = document.getElementById('pwa-portal-dismiss');
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
@if(request()->cookie('portal_session'))
<script>
// ── Web Push Subscription — Customer Portal ───────────────────────────────────
(function () {
    var VAPID_PUBLIC_KEY = '{{ config("push.vapid.public_key", "") }}';
    var SUBSCRIBE_URL    = '{{ route("portal.push.subscribe", $portalRouteParams) }}';
    var UNSUBSCRIBE_URL  = '{{ route("portal.push.unsubscribe", $portalRouteParams) }}';
    var CSRF_TOKEN       = '{{ csrf_token() }}';

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

    function apiRequest(method, url, body) {
        return fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (r) { return r.json(); });
    }

    function setButtonState(subscribed) {
        var btn = document.getElementById('portal-push-btn');
        if (!btn) return;
        btn.style.display = '';
        if (subscribed) {
            btn.innerHTML = '<i class="fas fa-bell text-warning"></i>';
            btn.title = 'Notifikasi aktif';
            btn.dataset.subscribed = '1';
        } else {
            btn.innerHTML = '<i class="fas fa-bell"></i>';
            btn.title = 'Aktifkan Notifikasi';
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
            return apiRequest('POST', SUBSCRIBE_URL, {
                endpoint: sub.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('p256dh')))),
                    auth:   btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('auth')))),
                },
            }).then(function () { return sub; });
        });
    }

    var isPwa = window.matchMedia('(display-mode: standalone)').matches
             || window.navigator.standalone === true;

    function showPushInviteBanner() {
        if (document.getElementById('portal-push-invite-banner')) return;
        var banner = document.createElement('div');
        banner.id = 'portal-push-invite-banner';
        banner.style.cssText = 'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);z-index:9999;background:#0a3e68;color:#fff;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem;box-shadow:0 4px 20px rgba(0,0,0,.35);max-width:360px;width:calc(100% - 2rem);font-size:.875rem;';
        banner.innerHTML = '<i class="fas fa-bell" style="color:#facc15;font-size:1.1rem;flex-shrink:0;"></i>'
            + '<span style="flex:1;line-height:1.4;">Aktifkan notifikasi untuk info tagihan & status layanan.</span>'
            + '<button id="portal-push-invite-yes" style="background:#0f6b95;color:#fff;border:none;border-radius:6px;padding:.35rem .75rem;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap;">Aktifkan</button>'
            + '<button id="portal-push-invite-no" style="background:transparent;color:#94a3b8;border:none;padding:.35rem .5rem;cursor:pointer;font-size:1rem;line-height:1;" title="Tutup">&times;</button>';
        document.body.appendChild(banner);

        document.getElementById('portal-push-invite-no').addEventListener('click', function () {
            banner.remove();
        });

        document.getElementById('portal-push-invite-yes').addEventListener('click', function () {
            banner.remove();
            // Permission request dipicu oleh user gesture (klik tombol) — aman di Android
            Notification.requestPermission().then(function (permission) {
                if (permission === 'granted') {
                    doSubscribe().then(function () {
                        setButtonState(true);
                    }).catch(function () {});
                } else if (permission === 'denied') {
                    setDeniedButtonState();
                }
            });
        });
    }

    function showDeniedGuide() {
        var existing = document.getElementById('portal-push-denied-guide');
        if (existing) { existing.style.display = 'flex'; return; }

        var steps = isPwa ? [
            '<li>Buka <b>Pengaturan</b> Android</li>',
            '<li>Pilih <b>Aplikasi</b> → cari <b>Rafen Portal</b> (atau nama situs ini)</li>',
            '<li>Ketuk <b>Notifikasi</b> → aktifkan</li>',
            '<li>Kembali ke app ini lalu muat ulang</li>',
        ] : [
            '<li>Ketuk ikon <b>kunci / info</b> di address bar</li>',
            '<li>Pilih <b>Izin situs</b> atau <b>Pengaturan situs</b></li>',
            '<li>Cari <b>Notifikasi</b> → ubah ke <b>Izinkan</b></li>',
            '<li>Muat ulang halaman ini</li>',
        ];

        var el = document.createElement('div');
        el.id = 'portal-push-denied-guide';
        el.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:1rem;';
        el.innerHTML = [
            '<div style="background:#fff;border-radius:12px;max-width:340px;width:100%;padding:1.5rem;box-shadow:0 8px 32px rgba(0,0,0,.2);">',
            '<h5 style="margin:0 0 .75rem;font-size:1rem;color:#0f172a;"><i class="fas fa-bell-slash" style="color:#dc2626;margin-right:.4rem;"></i>Notifikasi Diblokir</h5>',
            '<p style="font-size:.875rem;color:#475569;margin:0 0 1rem;">Aktifkan ulang notifikasi dengan langkah berikut:</p>',
            '<ol style="font-size:.875rem;color:#334155;padding-left:1.2rem;margin:0 0 1rem;line-height:1.9;">',
        ].concat(steps).concat([
            '</ol>',
            '<div style="display:flex;gap:.5rem;">',
            '<button onclick="document.getElementById(\'portal-push-denied-guide\').style.display=\'none\'" style="flex:1;padding:.5rem;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:.875rem;">Tutup</button>',
            '<button onclick="location.reload()" style="flex:1;padding:.5rem;border:none;border-radius:6px;background:#0a3e68;color:#fff;cursor:pointer;font-size:.875rem;font-weight:600;">Muat Ulang</button>',
            '</div>',
            '</div>',
        ]).join('');
        document.body.appendChild(el);
    }

    function setDeniedButtonState() {
        var btn = document.getElementById('portal-push-btn');
        if (!btn) return;
        btn.style.display = '';
        btn.innerHTML = '<i class="fas fa-bell-slash" style="color:#fca5a5;"></i>';
        btn.title = 'Notifikasi diblokir — ketuk untuk panduan';
        btn.dataset.subscribed = 'denied';
    }

    var pushInviteTimeout = null;
    var isSyncingPushState = false;

    function serializeSubscription(sub) {
        return {
            endpoint: sub.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('p256dh')))),
                auth:   btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('auth')))),
            },
        };
    }

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
                    setDeniedButtonState();
                    return;
                }

                setButtonState(!!sub);

                if (sub) {
                    return apiRequest('POST', SUBSCRIBE_URL, serializeSubscription(sub)).catch(function () {});
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
        var btn = e.target.closest('#portal-push-btn');
        if (!btn) return;

        if (btn.dataset.subscribed === 'denied') {
            showDeniedGuide();
            return;
        }

        if (btn.dataset.subscribed === '1') {
            if (!confirm('Nonaktifkan notifikasi?\nAnda tidak akan menerima notifikasi tagihan dan info layanan.')) return;
            navigator.serviceWorker.ready.then(function (reg) {
                reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return;
                    var endpoint = sub.endpoint;
                    sub.unsubscribe().then(function () {
                        return apiRequest('DELETE', UNSUBSCRIBE_URL, { endpoint: endpoint });
                    }).then(function () {
                        setButtonState(false);
                    }).catch(function (err) { console.warn('Push unsubscribe failed:', err); });
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
                setButtonState(true);
            }).catch(function (err) { console.warn('Portal push subscribe failed:', err); });
        });
    });
})();
</script>
@endif
</body>
</html>
