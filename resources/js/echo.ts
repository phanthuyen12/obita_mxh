import { configureEcho } from '@laravel/echo-vue';

const reverbHost = window.location.hostname;
const reverbPort =
    window.location.protocol === 'https:'
        ? 443
        : (import.meta.env.VITE_REVERB_PORT ?? 80);
const reverbTls = window.location.protocol === 'https:';

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbTls,
    enabledTransports: ['ws', 'wss'],
});
