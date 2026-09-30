const CACHE_NAME = 'slsubcpatrol-v18';
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
    '/pwa-icon-maskable-192.png',
    '/pwa-icon-maskable-512.png',
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

self.addEventListener('push', (event) => {
    const payload = notificationPayload(event);

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon,
            badge: payload.badge,
            tag: payload.tag,
            data: {
                url: payload.url,
            },
            renotify: Boolean(payload.tag),
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = new URL(event.notification.data?.url || '/notifications', self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (! client.url.startsWith(self.location.origin) || ! ('focus' in client)) {
                        continue;
                    }

                    if ('navigate' in client) {
                        return client.navigate(targetUrl).then(() => client.focus());
                    }

                    return client.focus();
                }

                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }

                return undefined;
            })
    );
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

function notificationPayload(event) {
    let data = {};

    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = { body: event.data.text() };
        }
    }

    return {
        title: data.title || 'SLSU Bontoc Patrol',
        body: data.body || 'New patrol alert received.',
        icon: data.icon || '/pwa-icon-192.png',
        badge: data.badge || '/pwa-icon-maskable-192.png',
        tag: data.tag || 'slsu-bontoc-patrol-alert',
        url: data.url || '/notifications',
    };
}
