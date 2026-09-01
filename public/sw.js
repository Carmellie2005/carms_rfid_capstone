const CACHE_NAME = 'slsu-bontoc-patrol-v8';
const OFFLINE_URL = '/offline.html';
const CORE_ASSETS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/favicon.png',
    '/favicon-32x32.png',
    '/favicon.ico',
    '/apple-touch-icon.png',
    '/pwa-icon-192.png',
    '/pwa-icon-512.png',
    '/images/slsu-rfid-system-logo-ai-v2.png',
    '/images/user-icons/supervisor-account.png',
    '/images/user-icons/guard-account.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(CORE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );

        return;
    }

    if (isCacheableAsset(url)) {
        event.respondWith(cacheFirst(request));
    }
});

function isCacheableAsset(url) {
    return url.pathname.startsWith('/build/')
        || CORE_ASSETS.includes(url.pathname);
}

function cacheFirst(request) {
    return caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
            return cachedResponse;
        }

        return fetch(request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                const responseCopy = networkResponse.clone();

                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseCopy);
                });
            }

            return networkResponse;
        });
    });
}
