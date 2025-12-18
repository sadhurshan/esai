import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

type EchoInstance = InstanceType<typeof Echo>;

interface CreateRealtimeClientOptions {
    token: string;
}

let warnedMissingKey = false;

export function createRealtimeClient({ token }: CreateRealtimeClientOptions): EchoInstance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const appKey = import.meta.env.VITE_PUSHER_APP_KEY;

    if (!appKey) {
        if (import.meta.env.DEV && !warnedMissingKey) {
            console.warn('VITE_PUSHER_APP_KEY is not set. Realtime notifications are disabled.');
            warnedMissingKey = true;
        }
        return null;
    }

    window.Pusher = window.Pusher ?? Pusher;

    const scheme = String(import.meta.env.VITE_PUSHER_SCHEME ?? (import.meta.env.DEV ? 'http' : 'https')).toLowerCase();
    const forceTLS = scheme === 'https';
    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';
    const customHost = import.meta.env.VITE_PUSHER_HOST;
    const defaultPort = forceTLS ? 443 : 80;
    const port = Number(import.meta.env.VITE_PUSHER_PORT ?? (customHost ? 6001 : defaultPort));
    const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
    const authEndpoint = apiBaseUrl ? `${apiBaseUrl}/broadcasting/auth` : '/broadcasting/auth';

    return new Echo({
        broadcaster: 'pusher',
        key: appKey,
        cluster,
        wsHost: customHost && customHost.length > 0 ? customHost : undefined,
        wsPort: port,
        wssPort: port,
        forceTLS,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint,
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });
}
