// Service worker mínimo: permite instalar la app y ver una pantalla si no hay conexión.
// No guarda páginas ni datos (son privados): solo archivos estáticos y la pantalla offline.
const VERSION = 'cobros-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return; // pagos, login, etc. siempre van a la red

    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Páginas: siempre de la red; sin conexión, pantalla offline
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Assets compilados e íconos (nombres con hash): caché primero
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        e.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(VERSION).then((c) => c.put(req, copy));
                return res;
            }))
        );
    }
});
