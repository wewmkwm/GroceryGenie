const CACHE_NAME = 'gg-cache-v1';
const RUNTIME = 'gg-runtime-v1';

const PRECACHE_URLS = [
  '/',
  '/customer/customer_home.php',
  '/customer/all_recipes.php',
  '/customer/offline.html',
  '/assets/img/default_profile.png',
  '/manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.map((k) => (k !== CACHE_NAME && k !== RUNTIME) ? caches.delete(k) : Promise.resolve())
    ))
  );
  self.clients.claim();
});

// Navigation: network-first, fallback to cache then offline page
async function handleNavigation(event) {
  try {
    const fresh = await fetch(event.request);
    const cache = await caches.open(RUNTIME);
    cache.put(event.request, fresh.clone());
    return fresh;
  } catch (e) {
    const cached = await caches.match(event.request);
    return cached || caches.match('/customer/offline.html');
  }
}

// Static assets/images: cache-first with runtime caching
async function handleAsset(event) {
  const cached = await caches.match(event.request);
  if (cached) return cached;
  try {
    const resp = await fetch(event.request);
    const cache = await caches.open(RUNTIME);
    cache.put(event.request, resp.clone());
    return resp;
  } catch (e) {
    return Response.error();
  }
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (req.mode === 'navigate') {
    event.respondWith(handleNavigation(event));
    return;
  }

  // cache-first for uploads and images
  if (url.pathname.startsWith('/uploads') || url.pathname.includes('/assets/') || url.pathname.endsWith('.png') || url.pathname.endsWith('.jpg') || url.pathname.endsWith('.jpeg')) {
    event.respondWith(handleAsset(event));
    return;
  }

  // default: network-first then cache
  event.respondWith((async () => {
    try {
      const fresh = await fetch(req);
      const cache = await caches.open(RUNTIME);
      cache.put(req, fresh.clone());
      return fresh;
    } catch (e) {
      const cached = await caches.match(req);
      return cached || Promise.reject(e);
    }
  })());
});
