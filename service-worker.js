/*
 * Static-only cache. Provider metadata, image CDN responses, and copyrighted preview
 * audio are intentionally never stored here. Network-first keeps deployments fresh and
 * avoids a stale app shell being mistaken for a perpetually loading player.
 *
 * Phase 2.5: the Laravel /api/v1 origin is never intercepted either — only same-origin
 * GETs are handled below, so catalogue requests (owned + provider metadata, preview
 * pass-throughs) always go straight to the network and are never cached by the app shell.
 * The cache key bumped v7 → v8 so installed clients pick up the API-mode player.
 */
const CACHE_NAME = 'shirin-static-v8';
const STATIC_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './css/reset.css',
  './css/variables.css',
  './css/base.css',
  './css/layout.css',
  './css/components.css',
  './css/player.css',
  './css/animations.css',
  './css/responsive.css',
  './js/app.js',
  './js/api.js',
  './js/animations.js',
  './js/config.js',
  './js/icons.js',
  './js/favorites.js',
  './js/lyrics.js',
  './js/player.js',
  './js/player-view.js',
  './js/queue.js',
  './js/router.js',
  './js/search.js',
  './js/state.js',
  './js/ui.js',
  './js/utils.js',
  './js/views.js',
  './assets/icons/icon.svg',
  './assets/images/artwork-fallback.svg',
  './assets/images/artist-fallback.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || request.method !== 'GET') return;

  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response.ok && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      })
      .catch(() => caches.match(request).then((cached) => {
        if (cached) return cached;
        if (request.mode === 'navigate') return caches.match('./index.html');
        return Response.error();
      }))
  );
});
