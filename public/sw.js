self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};

    event.waitUntil(
        self.registration.showNotification(data.title || 'Tin nhắn mới', {
            body: data.body || 'Bạn có một tin nhắn mới.',
            icon: data.icon || '/apple-touch-icon.png',
            badge: data.badge || '/favicon-32x32.png',
            tag: data.tag || 'website-chat',
            data: data.data || { url: '/omnichat/livechat' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/omnichat/livechat';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const existing = windows.find((window) => 'focus' in window);

            if (existing) {
                existing.focus();
                existing.postMessage({ type: 'open-url', url });
                return;
            }

            return clients.openWindow(url);
        }),
    );
});
