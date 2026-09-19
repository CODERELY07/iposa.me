/* iPOSa service worker. Served by ServiceWorkerController, which fills in the placeholders.
 *
 * - Precaches the built CSS/JS, icons and the offline page on install.
 * - The register (/pos) is network-first and cached after every online visit, so it opens offline.
 * - Other pages fall back to /offline.html when there's no connection.
 * - Fonts are stale-while-revalidate.
 * POST requests are never touched: offline sales are queued by the page (IndexedDB) and replayed.
 */
const VERSION = '__VERSION__';
const STATIC_CACHE = `iposa-static-${VERSION}`;
const PAGE_CACHE = 'iposa-pages';
const FONT_CACHE = 'iposa-fonts';
const OFFLINE_URL = '/offline.html';
const PRECACHE = __PRECACHE__;
const OFFLINE_PAGES = ['/pos'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('iposa-static-') && key !== STATIC_CACHE)
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    // Sent on logout: never leave a signed-in page cached on a shared counter tablet.
    if (event.data === 'clear-user-pages') {
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        if (request.mode === 'navigate') {
            event.respondWith(OFFLINE_PAGES.includes(url.pathname) ? registerPage(request, url) : pageOrOfflineFallback(request));

            return;
        }

        if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || /^\/(favicon|apple-touch-icon)/.test(url.pathname)) {
            event.respondWith(cacheFirst(request, STATIC_CACHE));
        }

        return;
    }

    if (url.hostname === 'fonts.bunny.net') {
        event.respondWith(staleWhileRevalidate(request, FONT_CACHE));
    }
});

/**
 * Network first; keep the latest good copy so the register opens offline.
 */
async function registerPage(request, url) {
    const cache = await caches.open(PAGE_CACHE);

    try {
        const response = await fetch(request);

        // Only cache the real register, never a login redirect or an error page.
        if (response.ok && !response.redirected) {
            await cache.put(url.pathname, response.clone());
        }

        return response;
    } catch (error) {
        return (await cache.match(url.pathname)) || (await caches.match(OFFLINE_URL));
    }
}

async function pageOrOfflineFallback(request) {
    try {
        return await fetch(request);
    } catch (error) {
        return (await caches.match(OFFLINE_URL)) || Response.error();
    }
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(cacheName);
        await cache.put(request, response.clone());
    }

    return response;
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok || response.type === 'opaque') {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached || network;
}
