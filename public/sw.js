const CACHE_NAME = 'budget-tracker-static-v2';
const CACHEABLE_PATTERNS = [/^\/build\//, /^\/icons\//, /^https:\/\/fonts\.(googleapis|gstatic)\.com\//];

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

function isCacheable(url) {
  return CACHEABLE_PATTERNS.some((pattern) => pattern.test(url.pathname) || pattern.test(url.href));
}

// Cache-first for static build assets, icons, and web fonts only.
// Everything else (API calls, HTML pages) passes straight through to the
// network — this is a data app, so we never want to serve stale finances.
self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (!isCacheable(url)) return;

  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      const cached = await cache.match(request);
      if (cached) return cached;
      const response = await fetch(request);
      if (response.ok) cache.put(request, response.clone());
      return response;
    })
  );
});
