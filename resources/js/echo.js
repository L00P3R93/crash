import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Reverb speaks the Pusher protocol, so laravel-echo's 'reverb' broadcaster
 * is really pusher-js underneath with Reverb's connection defaults baked in.
 *
 * Private channels (a player's own bet confirmations) need the current
 * Sanctum token on every subscribe, so this is a factory, not a singleton —
 * the play controller creates a fresh Echo instance right after login,
 * carrying that token, and tears it down on logout.
 */
export function createEcho(token) {
    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        },
    });
}
