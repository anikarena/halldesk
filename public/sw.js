// HallDesk service worker.
// Pages are never cached: they hold personal data and must always be fresh.
// Built assets under /build are hashed, so they are cached forever once loaded.
// When the network is down, a page request falls back to /offline.html.
// Push alerts from the server are shown as system notifications (see the end).

const VERSION = 'v1';
const SHELL = `halldesk-shell-${VERSION}`;
const ASSETS = `halldesk-assets-${VERSION}`;
const SHELL_FILES = ['/offline.html', '/icons/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL).then((cache) => cache.addAll(SHELL_FILES)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== SHELL && key !== ASSETS).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const url = new URL(request.url);
    if (url.origin === self.location.origin && url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(ASSETS).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }
                const response = await fetch(request);
                if (response.ok) {
                    cache.put(request, response.clone());
                }
                return response;
            }),
        );
    }
});

// Push alert: { title, body, url, tag } from app/Notifications/Channels/PushChannel.php
self.addEventListener('push', (event) => {
    let alert = {};
    try {
        alert = event.data.json();
    } catch (e) {}

    event.waitUntil(
        self.registration.showNotification(alert.title || 'HallDesk', {
            body: alert.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-96.png',
            tag: alert.tag,
            data: { url: alert.url || '/notifications' },
        }),
    );
});

// Tapping the alert opens its page, in the app window when one is already open
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/notifications', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
            for (const client of windows) {
                try {
                    await client.navigate(url);
                    return client.focus();
                } catch (e) {}
            }
            return self.clients.openWindow(url);
        }),
    );
});
