/*
 * Browser push subscription for the "Notifications" account page.
 * Alpine is provided by Livewire; components are registered on alpine:init.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushSubscription', (publicKey, storeUrl, destroyUrl) => ({
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        subscribed: false,
        message: '',

        async init() {
            if (!this.supported) {
                this.message = 'This browser does not support notifications.';
                return;
            }
            const registration = await navigator.serviceWorker.register('/sw.js');
            this.subscribed = !!(await registration.pushManager.getSubscription());
        },

        async subscribe() {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.message = 'Notifications are blocked in this browser’s settings.';
                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
                await send(storeUrl, 'POST', {
                    ...subscription.toJSON(),
                    contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                });
                this.subscribed = true;
                this.message = 'This browser will now receive notifications.';
                this.$wire.$dispatch('push-subscribed');
            } catch (error) {
                this.message = 'Could not turn on notifications: ' + error.message;
            }
        },

        async unsubscribe() {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await send(destroyUrl, 'DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            this.subscribed = false;
            this.message = 'This browser will no longer receive notifications.';
            this.$wire.$dispatch('push-subscribed');
        },
    }));
});

async function send(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        throw new Error('the server refused the subscription (' + response.status + ')');
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

/*
 * Reveal-on-scroll: elements with `.reveal` fade in
 * when they enter the viewport (also after Livewire re-renders).
 */
(() => {
    if (!('IntersectionObserver' in window)) {
        document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible')));
        return;
    }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    const scan = () => document.querySelectorAll('.reveal:not(.is-visible)').forEach((el) => io.observe(el));

    document.addEventListener('DOMContentLoaded', () => {
        scan();
        new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
    });
})();
