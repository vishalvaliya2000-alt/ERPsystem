const CACHE_NAME = 'erpsaas-cache-v1';
const PRECACHE_URLS = [
    '/offline',
    '/manifest.json',
    '/favicon.svg',
    '/favicon.png'
];

// 1. Install & Pre-cache
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_URLS);
        }).then(() => self.skipWaiting())
    );
});

// 2. Activate & Clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Strategy: Network-First with Offline Fallback
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only process GET requests; ignore non-HTTP(S) or cross-origin POSTs
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Navigation (HTML pages) -> Network first, fallback to cached /offline
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match('/offline');
            })
        );
        return;
    }

    // Static Assets (Images, Icons, Fonts) -> Stale-while-revalidate
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font'
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                }).catch(() => cachedResponse);

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default: Network with graceful catch
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
