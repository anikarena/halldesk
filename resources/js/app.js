import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { mountCharts } from './charts';

// Install as an app (PWA). Chrome on Android offers beforeinstallprompt; Safari on
// iPhone has no prompt, so the card explains how to add HallDesk to the home screen.
let installEvent = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installEvent = event;
    window.dispatchEvent(new CustomEvent('app-installable'));
});

Alpine.data('installApp', () => ({
    show: false,
    ios: /iphone|ipad|ipod/i.test(navigator.userAgent) || (/macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1),
    init() {
        const installed = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        let closed = false;
        try { closed = localStorage.getItem('install-app-closed') === '1'; } catch (e) {}
        if (installed || closed) {
            return;
        }
        this.show = this.ios || installEvent !== null;
        window.addEventListener('app-installable', () => { this.show = true; });
        window.addEventListener('appinstalled', () => { this.show = false; });
    },
    async install() {
        if (!installEvent) {
            return;
        }
        installEvent.prompt();
        const { outcome } = await installEvent.userChoice;
        installEvent = null;
        if (outcome === 'accepted') {
            this.show = false;
        }
    },
    dismiss() {
        this.show = false;
        try { localStorage.setItem('install-app-closed', '1'); } catch (e) {}
    },
}));

// Push alerts for this device (resources/views/partials/push-alerts.blade.php).
// The layout gives the server's public key in a meta tag; without it push is off.
const pushKey = document.querySelector('meta[name=push-key]')?.content;
const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

function pushRequest(method, body) {
    return fetch('/push/subscription', {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify(body),
    });
}

async function pushSubscription() {
    const worker = await navigator.serviceWorker.ready;
    return worker.pushManager.getSubscription();
}

function pushKeyBytes() {
    const base64 = pushKey.replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}

Alpine.data('pushAlerts', (closable = false) => ({
    // off | on | blocked | install (iPhone: only an installed app may ask) | hidden
    state: 'hidden',
    busy: false,
    error: '',
    async init() {
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (/macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
        const installed = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        let closed = false;
        try { closed = closable && localStorage.getItem('push-alerts-closed') === '1'; } catch (e) {}

        if (!pushKey || closed) {
            return;
        }
        if (!pushSupported) {
            this.state = ios && !installed && !closable ? 'install' : 'hidden';
            return;
        }
        if (Notification.permission === 'denied') {
            this.state = closable ? 'hidden' : 'blocked';
            return;
        }
        const on = Notification.permission === 'granted' && (await pushSubscription()) !== null;
        // The dashboard card is only an invitation, it goes away once alerts are on
        this.state = on ? (closable ? 'hidden' : 'on') : 'off';
    },
    async turnOn() {
        this.busy = true;
        this.error = '';
        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                this.state = Notification.permission === 'denied' ? 'blocked' : 'off';
                return;
            }
            const worker = await navigator.serviceWorker.ready;
            const subscription = (await worker.pushManager.getSubscription())
                || (await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: pushKeyBytes() }));
            const response = await pushRequest('POST', subscription.toJSON());
            if (!response.ok) {
                throw new Error('save');
            }
            this.state = 'on';
        } catch (e) {
            this.error = 'Alerts could not be turned on. Check your internet and try again.';
        } finally {
            this.busy = false;
        }
    },
    async turnOff() {
        this.busy = true;
        this.error = '';
        try {
            const subscription = await pushSubscription();
            if (subscription) {
                await pushRequest('DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            this.state = 'off';
        } catch (e) {
            this.error = 'Alerts could not be turned off. Check your internet and try again.';
        } finally {
            this.busy = false;
        }
    },
    dismiss() {
        this.state = 'hidden';
        try { localStorage.setItem('push-alerts-closed', '1'); } catch (e) {}
    },
}));

// A device follows the account signed in on it: tell the server once per visit
// who is here now, and take the device off the account on sign out.
async function syncPush() {
    const user = document.querySelector('meta[name=user-id]')?.content;
    if (!pushKey || !pushSupported || !user || Notification.permission !== 'granted') {
        return;
    }
    try {
        if (sessionStorage.getItem('push-user') === user) {
            return;
        }
        const subscription = await pushSubscription();
        if (subscription && (await pushRequest('POST', subscription.toJSON())).ok) {
            sessionStorage.setItem('push-user', user);
        }
    } catch (e) {}
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!pushKey || !pushSupported || !form.matches('form[action$="/logout"]') || form.dataset.pushCleared) {
        return;
    }
    event.preventDefault();
    form.dataset.pushCleared = '1';
    const submit = () => form.submit();
    // Never keep the user waiting on sign out
    const timer = setTimeout(submit, 1500);
    (async () => {
        try {
            sessionStorage.removeItem('push-user');
            const registration = await navigator.serviceWorker.getRegistration();
            const subscription = await registration?.pushManager.getSubscription();
            if (subscription) {
                await pushRequest('DELETE', { endpoint: subscription.endpoint });
            }
        } catch (e) {}
        clearTimeout(timer);
        submit();
    })();
});

// Ask AI chat (resources/views/assistant/index.blade.php). The server keeps the
// conversation; this only shows it and sends messages and Confirm / Cancel.
Alpine.data('assistantChat', (initial) => ({
    messages: initial.messages,
    pending: initial.pending,
    draft: '',
    busy: false,
    error: '',
    init() {
        // Back on the page with an old chat: jump to the latest message
        if (this.messages.length) {
            this.$nextTick(() => window.scrollTo(0, document.body.scrollHeight));
        }
    },
    async send(text) {
        text = (text ?? this.draft).trim();
        if (!text || this.busy || this.pending) {
            return;
        }
        this.messages.push({ role: 'user', text });
        this.draft = '';
        this.$nextTick(() => this.grow());
        if (!(await this.post(initial.urls.message, { message: text }))) {
            // Nothing was saved: put the question back so it can be sent again
            this.messages.pop();
            this.draft = text;
        }
    },
    async decide(approve) {
        const pending = this.pending;
        this.messages.push({ role: 'user', text: (approve ? 'Confirmed: ' : 'Cancelled: ') + pending.summary });
        this.pending = null;
        if (!(await this.post(initial.urls.decide, { id: pending.id, approve }))) {
            this.messages.pop();
            this.pending = pending;
        }
    },
    async post(url, body) {
        this.busy = true;
        this.error = '';
        this.$nextTick(() => this.scrollDown());
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.error = {
                    419: 'Your session has expired. Please reload the page.',
                    429: data.message === 'Too Many Attempts.' ? 'That is a lot of questions. Please wait a minute.' : data.message,
                }[response.status] || data.message || 'Something went wrong. Please try again.';
                return false;
            }
            this.messages.push({ role: 'assistant', text: data.text, links: data.links });
            this.pending = data.pending;
            return true;
        } catch (e) {
            this.error = 'No connection. Check your internet and try again.';
            return false;
        } finally {
            this.busy = false;
            this.$nextTick(() => {
                this.scrollDown();
                this.$refs.input?.focus();
            });
        }
    },
    scrollDown() {
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    },
    grow() {
        const input = this.$refs.input;
        if (input) {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        }
    },
}));

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Sections marked data-reveal fade up once when they scroll into view.
// Without JS or with reduced motion they are simply shown.
function revealOnScroll() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length || !('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    document.documentElement.classList.add('reveal-ready');
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach((item) => observer.observe(item));
}

document.addEventListener('DOMContentLoaded', () => {
    mountCharts();
    revealOnScroll();
    syncPush();
});
