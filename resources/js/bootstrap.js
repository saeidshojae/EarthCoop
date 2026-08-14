import 'bootstrap';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.Pusher = Pusher;

const runtimeRealtime = window.EarthCoopRealtimeConfig || {};
const hasRuntimeRealtime = Object.keys(runtimeRealtime).length > 0;
const realtimeEnabled = hasRuntimeRealtime ? runtimeRealtime.enabled !== false : true;
const realtimeKey = hasRuntimeRealtime ? runtimeRealtime.key : (import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY);
const realtimeHost = hasRuntimeRealtime ? runtimeRealtime.host : (import.meta.env.VITE_REVERB_HOST || import.meta.env.VITE_PUSHER_HOST);
const realtimePort = hasRuntimeRealtime ? runtimeRealtime.port : (import.meta.env.VITE_REVERB_PORT || import.meta.env.VITE_PUSHER_PORT);
const realtimeScheme = hasRuntimeRealtime ? runtimeRealtime.scheme : (import.meta.env.VITE_REVERB_SCHEME || import.meta.env.VITE_PUSHER_SCHEME || 'https');
const realtimeCluster = hasRuntimeRealtime ? runtimeRealtime.cluster : (import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1');
const chatTransport = String(runtimeRealtime.transport || import.meta.env.VITE_GROUP_CHAT_TRANSPORT || 'auto').toLowerCase();
const isSecureRealtime = String(realtimeScheme).toLowerCase() === 'https';

if (realtimeEnabled && realtimeKey && chatTransport !== 'polling') {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: realtimeKey,
        cluster: realtimeCluster,
        wsHost: realtimeHost || undefined,
        wsPort: realtimePort ? Number(realtimePort) : 80,
        wssPort: realtimePort ? Number(realtimePort) : 443,
        forceTLS: isSecureRealtime,
        enabledTransports: isSecureRealtime ? ['wss', 'ws'] : ['ws'],
        encrypted: isSecureRealtime,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    });
}

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
