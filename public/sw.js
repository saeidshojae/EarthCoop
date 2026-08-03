/* Simple service worker for EarthCoop PWA
   - Precache app shell (versioned)
   - Runtime caching: network-first for API (no caching for auth-protected routes), cache-first for static assets
   - Offline fallback for navigations
*/
const PRECACHE = 'earthcoop-precache-v3';
const RUNTIME = 'earthcoop-runtime-v3';

const PRECACHE_URLS = [
  '/offline.html',
  // Add built asset paths if you produce them to public/build (e.g., /build/assets/app.js)
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(PRECACHE).then(cache => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== PRECACHE && key !== RUNTIME)
          .map(key => caches.delete(key))
    ))
  );
  self.clients.claim();
});

function isNavigationRequest(request) {
  return request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');
}

function isApiRequest(url) {
  try {
    const u = new URL(url, self.location.href);
    return u.pathname.startsWith('/api') || u.pathname.startsWith('/sanctum');
  } catch (e) {
    return false;
  }
}

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  // Ignore non-http(s) schemes and cross-origin requests (e.g., extensions, Vite dev server)
  if (!['http:', 'https:'].includes(url.protocol) || url.origin !== self.location.origin) {
    return;
  }

  // Navigation requests -> app shell with network-first fallback
  if (isNavigationRequest(request)) {
    event.respondWith((async () => {
      try {
        const networkResponse = await fetch(request);
        return networkResponse;
      } catch (err) {
        const cache = await caches.open(PRECACHE);
        return (await cache.match('/offline.html')) || new Response('Offline', { status: 503 });
      }
    })());
    return;
  }

  // API requests: network-first; do not cache responses for auth-sensitive endpoints
  if (isApiRequest(request.url)) {
    event.respondWith((async () => {
      try {
        const networkResponse = await fetch(request);
        return networkResponse;
      } catch (err) {
        // Optionally return a cached response for public GET API endpoints if available
        const cache = await caches.open(RUNTIME);
        const cached = await cache.match(request);
        return cached || new Response(null, { status: 503, statusText: 'Service Unavailable' });
      }
    })());
    return;
  }

  // CSS and JavaScript: network-first so a deployment is visible immediately.
  if (['style', 'script'].includes(request.destination)) {
    event.respondWith((async () => {
      const cache = await caches.open(RUNTIME);
      try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
          await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
      } catch (err) {
        return (await cache.match(request)) || new Response(null, { status: 504, statusText: 'Gateway Timeout' });
      }
    })());
    return;
  }

  // Images and fonts are content-addressed/static enough for cache-first.
  if (['image', 'font'].includes(request.destination)) {
    event.respondWith((async () => {
      const cache = await caches.open(RUNTIME);
      const cached = await cache.match(request);
      if (cached) return cached;
      try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
          await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
      } catch (err) {
        return cached || new Response(null, { status: 504, statusText: 'Gateway Timeout' });
      }
    })());
    return;
  }

  // Default: network-first
  event.respondWith((async () => {
    try {
      return await fetch(request);
    } catch (e) {
      const cache = await caches.open(RUNTIME);
      return await cache.match(request) || new Response(null, { status: 504 });
    }
  })());
});

self.addEventListener('message', event => {
  if (!event.data) return;
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
  }
});
