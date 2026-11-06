const CACHE_NAME = 'kfms-static-v1';
const PRECACHE_URLS = [
    '/manifest.webmanifest',
    '/favicon.ico',
    '/favicon.png',
    '/apple-touch-icon.png',
];

const PRIVATE_PATH_PREFIXES = [
    '/dashboard',
    '/access-control',
    '/settings',
    '/staff',
    '/messages',
    '/client',
    '/notifications',
    '/api',
    '/sanctum',
    '/login',
    '/logout',
    '/register',
    '/email',
    '/password',
];

const isPrivatePath = (pathname) => PRIVATE_PATH_PREFIXES.some((prefix) => (
    pathname === prefix || pathname.startsWith(`${prefix}/`)
));

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('kfms-static-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: 'Open KFMS to see the update.' };
    }

    event.waitUntil(self.registration.showNotification(payload.title || 'KFMS update', {
        body: payload.body || 'There is a new update in KFMS.',
        icon: '/admin/assets/images/Kali Logo 2.png',
        badge: '/favicon.png',
        tag: payload.tag || 'kfms-update',
        data: { url: payload.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil((async () => {
        const requestedUrl = new URL(event.notification.data?.url || '/', self.location.origin);
        const targetUrl = requestedUrl.origin === self.location.origin
            ? requestedUrl
            : new URL('/', self.location.origin);
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const currentWindow = windows.find((client) => new URL(client.url).origin === self.location.origin);

        if (currentWindow) {
            await currentWindow.navigate(targetUrl.href);
            return currentWindow.focus();
        }

        return self.clients.openWindow(targetUrl.href);
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin || isPrivatePath(url.pathname)) {
        return;
    }

    if (['font', 'image', 'script', 'style'].includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }

                return response;
            }))
        );
    }
});
