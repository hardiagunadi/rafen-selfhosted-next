// Rafen App Shell Service Worker — v1
// Handles: app shell caching (CSS/JS/images) + map tile caching
// Does NOT handle: POST requests, CSRF-sensitive routes, API data

const SHELL_CACHE = 'rafen-shell-v1';
const MAP_CACHE_DEFAULT = 'tenant-map-cache-default';

// Assets to pre-cache on install
const PRECACHE_URLS = [
    '/offline.html',
    '/branding/favicon-192.png',
    '/branding/favicon-512.png',
];

// ── Install: pre-cache shell assets ──────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => {
            return cache.addAll(PRECACHE_URLS);
        }).then(() => self.skipWaiting())
    );
});

// ── Activate: purge old shell caches ─────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((k) => k.startsWith('rafen-shell-') && k !== SHELL_CACHE)
                    .map((k) => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// ── Messages (map tile control, from map.blade.php) ───────────────────────────
let activeMapCache = MAP_CACHE_DEFAULT;

self.addEventListener('message', (event) => {
    const payload = event.data || {};

    if (payload.type === 'SET_ACTIVE_CACHE' && typeof payload.cacheName === 'string' && payload.cacheName !== '') {
        activeMapCache = payload.cacheName;
    }

    if (payload.type === 'WARMUP_TILES' && Array.isArray(payload.urls)) {
        const cacheName = (typeof payload.cacheName === 'string' && payload.cacheName !== '')
            ? payload.cacheName
            : activeMapCache;
        event.waitUntil(warmupTiles(cacheName, payload.urls));
    }

    if (payload.type === 'PURGE_TENANT_CACHE' && typeof payload.cachePrefix === 'string' && payload.cachePrefix !== '') {
        event.waitUntil(purgeTenantCaches(payload.cachePrefix));
    }
});

// ── Fetch: routing strategy ───────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Never intercept non-GET
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Map tiles → network-first with cache
    if (isMapTileRequest(url)) {
        event.respondWith(networkFirstTile(request));
        return;
    }

    // Cross-origin: cache CDN static assets, pass through everything else
    if (url.origin !== self.location.origin) {
        if (isCdnStaticAsset(url)) {
            event.respondWith(cacheFirstStatic(request));
        }
        return;
    }

    // Portal routes handled by sw-portal.js — do not intercept
    if (url.pathname.startsWith('/portal/')) return;

    // Same-origin: skip data/API/auth routes
    if (shouldSkipCaching(url)) return;

    // Same-origin static assets (hashed build files, images, icons) → cache-first
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirstStatic(request));
        return;
    }

    // Same-origin navigation (HTML pages) → network-first with offline fallback
    if (request.headers.get('Accept') && request.headers.get('Accept').includes('text/html')) {
        event.respondWith(networkFirstHtml(request));
        return;
    }
});

// ── Strategy: network-first tile ─────────────────────────────────────────────
async function networkFirstTile(request) {
    const cache = await caches.open(activeMapCache || MAP_CACHE_DEFAULT);
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) return cached;
        throw error;
    }
}

// ── Strategy: cache-first static ─────────────────────────────────────────────
async function cacheFirstStatic(request) {
    const cache = await caches.open(SHELL_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        return new Response('', { status: 503 });
    }
}

// ── Strategy: network-first HTML with offline fallback ────────────────────────
async function networkFirstHtml(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        const cached = await caches.match('/offline.html');
        if (cached) return cached;
        return new Response(
            '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Offline — Rafen</title></head>' +
            '<body style="font-family:sans-serif;text-align:center;padding:3rem;color:#0f172a">' +
            '<h2>Tidak ada koneksi</h2><p>Periksa koneksi internet Anda dan coba lagi.</p>' +
            '<button onclick="location.reload()">Coba Lagi</button></body></html>',
            { status: 200, headers: { 'Content-Type': 'text/html' } }
        );
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function isMapTileRequest(url) {
    return url.hostname === 'tile.openstreetmap.org' || url.hostname === 'server.arcgisonline.com';
}

function isCdnStaticAsset(url) {
    const cdnHosts = ['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];
    return cdnHosts.some((h) => url.hostname === h);
}

function isStaticAsset(url) {
    return url.pathname.startsWith('/build/assets/') ||
           url.pathname.startsWith('/branding/') ||
           /\.(png|jpg|jpeg|svg|ico|woff2?|ttf)$/i.test(url.pathname);
}

function shouldSkipCaching(url) {
    const skipPrefixes = [
        '/api/', '/datatable', '/webhook/', '/payment/',
        '/subscription/', '/logout', '/login',
        '/sanctum/', '/livewire/',
        '/mikrotik/', '/olt/', '/cpe/',
    ];
    return skipPrefixes.some((p) => url.pathname.startsWith(p));
}

async function warmupTiles(cacheName, urls) {
    const cache = await caches.open(cacheName || MAP_CACHE_DEFAULT);
    for (const url of urls) {
        if (typeof url !== 'string' || url === '') continue;
        const request = new Request(url, { mode: 'cors' });
        const existing = await cache.match(request);
        if (existing) continue;
        try {
            const response = await fetch(request);
            if (response && response.ok) await cache.put(request, response.clone());
        } catch (_) { /* skip failed tiles */ }
    }
}

async function purgeTenantCaches(cachePrefix) {
    const cacheNames = await caches.keys();
    await Promise.all(
        cacheNames
            .filter((n) => n.startsWith(cachePrefix))
            .map((n) => caches.delete(n))
    );
}

// ── Web Push: receive push event ─────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = { title: 'Rafen', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Rafen';
    const options = {
        body:    data.body   || '',
        icon:    data.icon   || '/branding/favicon-192.png',
        badge:   data.badge  || '/branding/favicon-192.png',
        tag:     data.tag    || 'rafen-notify',
        data:    { url: data.url || '/' },
        vibrate: [100, 50, 200],
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ── Web Push: notification click ─────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const rawTargetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        focusOrOpenNotificationTarget(rawTargetUrl, '/')
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

    const matchingPath = sameOriginClients.find((client) => {
        const clientUrl = new URL(client.url);

        return clientUrl.pathname === target.pathname;
    });
    if (matchingPath) {
        return matchingPath;
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
