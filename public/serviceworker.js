var staticCacheName = "pwa-v" + new Date().getTime();
var filesToCache = [
    '/images/icons/icon-128x128.png',
    '/images/icons/icon-144x144.png',
    '/images/icons/icon-152x152.png',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-384x384.png',
    '/images/icons/icon-512x512.png',
];

// Cache on install
self.addEventListener("install", event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(staticCacheName)
            .then(cache => {
                return cache.addAll(filesToCache);
            })
    );
});

// Clear old caches on activate
self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(cacheName => cacheName.startsWith("pwa-"))
                        .filter(cacheName => cacheName !== staticCacheName)
                        .map(cacheName => caches.delete(cacheName))
                );
            }),
        ])
    );
});

// Network-first strategy: always fetch fresh, fall back to cache for images only
self.addEventListener("fetch", event => {
    // Only cache GET requests for same-origin image assets
    if (
        event.request.method !== 'GET' ||
        !event.request.url.startsWith(self.location.origin) ||
        !event.request.url.match(/\.(png|jpg|jpeg|gif|svg|ico|webp)$/)
    ) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});
