const DEFAULT_CACHE_NAME = 'tenant-map-cache-default';
let activeCacheName = DEFAULT_CACHE_NAME;

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    const payload = event.data || {};

    if (payload.type === 'SET_ACTIVE_CACHE' && typeof payload.cacheName === 'string' && payload.cacheName !== '') {
        activeCacheName = payload.cacheName;
    }

    if (payload.type === 'WARMUP_TILES' && Array.isArray(payload.urls)) {
        const cacheName = typeof payload.cacheName === 'string' && payload.cacheName !== ''
            ? payload.cacheName
            : activeCacheName;

        event.waitUntil(warmupTiles(cacheName, payload.urls));
    }

    if (payload.type === 'PURGE_TENANT_CACHE' && typeof payload.cachePrefix === 'string' && payload.cachePrefix !== '') {
        event.waitUntil(purgeTenantCaches(payload.cachePrefix));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (!isMapTileRequest(url)) {
        return;
    }

    event.respondWith(networkFirstTile(request));
});

function isMapTileRequest(url) {
    if (url.hostname === 'tile.openstreetmap.org') {
        return true;
    }

    return url.hostname === 'server.arcgisonline.com';
}

async function networkFirstTile(request) {
    const cache = await caches.open(activeCacheName || DEFAULT_CACHE_NAME);

    try {
        const networkResponse = await fetch(request);

        if (networkResponse && networkResponse.ok) {
            await cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        const cachedResponse = await cache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        throw error;
    }
}

async function warmupTiles(cacheName, urls) {
    const cache = await caches.open(cacheName || DEFAULT_CACHE_NAME);

    for (const url of urls) {
        if (typeof url !== 'string' || url === '') {
            continue;
        }

        const request = new Request(url, { mode: 'cors' });
        const existing = await cache.match(request);

        if (existing) {
            continue;
        }

        try {
            const response = await fetch(request);

            if (response && response.ok) {
                await cache.put(request, response.clone());
            }
        } catch (error) {
            // Skip failed tile fetch and continue the remaining tiles.
        }
    }
}

async function purgeTenantCaches(cachePrefix) {
    const cacheNames = await caches.keys();

    await Promise.all(
        cacheNames
            .filter((cacheName) => cacheName.startsWith(cachePrefix))
            .map((cacheName) => caches.delete(cacheName))
    );
}
