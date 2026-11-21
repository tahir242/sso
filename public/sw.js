/*
 * single sign-on service worker: shows push notifications and opens
 * the related page when one is clicked. Nothing is cached.
 */
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'SSO', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(payload.title || 'SSO', {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        data: payload.data || {},
        tag: payload.tag,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/home';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url === url && 'focus' in client) {
                return client.focus();
            }
        }
        return self.clients.openWindow(url);
    })());
});
