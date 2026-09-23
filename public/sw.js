/**
 * King Hub Social — Service Worker
 *
 * Nhiệm vụ:
 *  1. Cache tài nguyên tĩnh để app load nhanh khi "Add to Home Screen".
 *  2. Nhận và hiển thị Web Push Notification khi browser ở background.
 *  3. Khi user bấm vào notification → mở/focus tab LiveChat.
 */

const CACHE_VERSION = 'king-hub-v1';
const PRECACHE_URLS = ['/', '/omnichat/livechat', '/favicon.ico', '/apple-touch-icon.png'];

// ── Install: pre-cache shell ───────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

// ── Activate: xoá cache cũ ────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_VERSION)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// ── Fetch: network-first cho API, cache-first cho tĩnh ────────────────
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Chỉ cache GET requests cùng origin, không cache API/websocket
    if (
        event.request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/webhooks/') ||
        url.pathname.startsWith('/broadcasting/')
    ) {
        return;
    }

    // Network-first cho HTML (trang SPA) để Inertia luôn lấy dữ liệu mới
    if (event.request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match(event.request).then(
                    (cached) => cached ?? caches.match('/'),
                ),
            ),
        );
        return;
    }

    // Cache-first cho assets tĩnh (JS, CSS, fonts, images)
    event.respondWith(
        caches.match(event.request).then(
            (cached) => cached ?? fetch(event.request),
        ),
    );
});

// ── Push: nhận Web Push Notification từ server ────────────────────────
self.addEventListener('push', (event) => {
    let data = {
        title: 'Tin nhắn mới',
        body: 'Bạn có tin nhắn mới từ AEtrading TELE',
        icon: '/apple-touch-icon.png',
        tag: 'omnichat-new',
        url: '/omnichat/livechat',
    };

    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon,
            badge: '/apple-touch-icon.png',
            tag: data.tag,
            data: { url: data.url },
            requireInteraction: false,
            vibrate: [200, 100, 200],
        }),
    );
});

// ── Notification click: mở/focus tab LiveChat ─────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url ?? '/omnichat/livechat';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Nếu đã có tab LiveChat → focus nó
                for (const client of clientList) {
                    if (client.url.includes('/omnichat/livechat') && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Không tìm thấy → mở tab mới
                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }
            }),
    );
});
