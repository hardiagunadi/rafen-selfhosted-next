// Rafen Portal Service Worker — v1
// Scope: /portal/  (registered by portal/layout.blade.php)
// Handles: CDN caching, static assets, HTML navigation, Web Push

const SHELL_CACHE = 'rafen-portal-shell-v1';

const PRECACHE_URLS = [
    '/offline.html',
    '/branding/favicon-192.png',
    '/branding/favicon-512.png',
];

// ── Install ───────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// ── Activate: purge old portal caches ────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((k) => k.startsWith('rafen-portal-shell-') && k !== SHELL_CACHE)
                    .map((k) => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Cross-origin: cache CDN static assets, pass through everything else
    if (url.origin !== self.location.origin) {
        if (isCdnStaticAsset(url)) {
            event.respondWith(cacheFirstStatic(request));
        }
        return;
    }

    // Skip auth/API/data routes
    if (shouldSkipCaching(url)) return;

    // Static assets (hashed build files, images, icons) → cache-first
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirstStatic(request));
        return;
    }

    // HTML navigation → network-first with offline fallback
    if (request.headers.get('Accept') && request.headers.get('Accept').includes('text/html')) {
        event.respondWith(networkFirstHtml(request));
        return;
    }
});

// ── Strategy: cache-first (static assets) ────────────────────────────────────
async function cacheFirstStatic(request) {
    const cache = await caches.open(SHELL_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response && response.ok && response.type !== 'opaque') {
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        return new Response('', { status: 408 });
    }
}

// ── Strategy: network-first HTML ─────────────────────────────────────────────
async function networkFirstHtml(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch (_) {
        const cached = await caches.match(request);
        if (cached) return cached;
        const offline = await caches.match('/offline.html');
        return offline || new Response('Offline', { status: 503 });
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function isCdnStaticAsset(url) {
    return (
        url.hostname === 'cdn.jsdelivr.net' ||
        url.hostname === 'cdnjs.cloudflare.com' ||
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com'
    );
}

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/branding/') ||
        /\.(png|jpg|jpeg|svg|ico|woff2|ttf|webp)$/.test(url.pathname)
    );
}

function shouldSkipCaching(url) {
    const skipPrefixes = [
        '/api/', '/webhook/', '/payment/', '/subscription/',
        '/logout', '/login', '/sanctum/',
    ];
    return skipPrefixes.some((p) => url.pathname.startsWith(p));
}

// ── Web Push: receive push event ──────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = { title: 'Portal Pelanggan', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Portal Pelanggan';
    const options = {
        body:    data.body   || '',
        icon:    data.icon   || '/branding/favicon-192.png',
        badge:   data.badge  || '/branding/favicon-192.png',
        tag:     data.tag    || 'portal-notify',
        data:    { url: data.url || '/portal/' },
        vibrate: [100, 50, 200],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// ── Web Push: notification click ──────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const rawTargetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/portal/';

    event.waitUntil(
        focusOrOpenNotificationTarget(rawTargetUrl, '/portal/')
    );
});

function resolveTargetUrl(rawTargetUrl, fallbackPath) {
    try {
        const targetUrl = new URL(rawTargetUrl, self.location.origin);

        if (targetUrl.origin !== self.location.origin) {
            return new URL(fallbackPath, self.location.origin).href;
        }

        return targetUrl.href;
    } catch (_) {
        return new URL(fallbackPath, self.location.origin).href;
    }
}

function findBestClient(clientList, targetUrl) {
    const target = new URL(targetUrl);
    const sameOriginClients = clientList.filter((client) => client.url.startsWith(self.location.origin));

    const exactMatch = sameOriginClients.find((client) => client.url === target.href);
    if (exactMatch) {
        return exactMatch;
    }

    const portalClient = sameOriginClients.find((client) => {
        const clientUrl = new URL(client.url);

        return clientUrl.pathname.startsWith('/portal/');
    });
    if (portalClient) {
        return portalClient;
    }

    return sameOriginClients[0] || null;
}

async function focusOrOpenNotificationTarget(rawTargetUrl, fallbackPath) {
    const targetUrl = resolveTargetUrl(rawTargetUrl, fallbackPath);
    const clientList = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    const bestClient = findBestClient(clientList, targetUrl);

    if (bestClient && 'focus' in bestClient) {
        await bestClient.focus();
        if ('navigate' in bestClient && bestClient.url !== targetUrl) {
            await bestClient.navigate(targetUrl);
        }
        return;
    }

    if (clients.openWindow) {
        await clients.openWindow(targetUrl);
    }
}
