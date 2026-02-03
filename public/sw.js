/* =========================
   PANTUBE SERVICE WORKER
   Version: 2.0.0
   ========================= */

const CACHE_NAME = 'pantube-v2';
const STATIC_CACHE = 'pantube-static-v2';
const OFFLINE_URL = '/offline.html';
const NETWORK_TIMEOUT = 3000;

const STATIC_FILES = [
  '/',
  '/home',
  '/login',
  '/register',

  '/assets/css/style.css',
  '/assets/js/app.js',

  '/assets/img/logo.png',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png',
  '/assets/img/offline.svg',

  'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',

  '/manifest.json',
  OFFLINE_URL,
];

// INSTALL
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(STATIC_FILES))
      .then(() => self.skipWaiting())
  );
});

// ACTIVATE
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(names =>
      Promise.all(
        names.map(name => {
          if (name !== CACHE_NAME && name !== STATIC_CACHE) {
            return caches.delete(name);
          }
        })
      )
    ).then(() => self.clients.claim())
  );
});

// FETCH
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('/admin/') ||
    url.pathname.includes('/upload') ||
    url.pathname.includes('/messages')
  ) {
    return;
  }

  if (url.origin === location.origin) {
    if (
      url.pathname.startsWith('/assets/') ||
      url.pathname.startsWith('/uploads/')
    ) {
      event.respondWith(cacheFirst(event.request));
    } else {
      event.respondWith(networkFirst(event.request));
    }
  } else {
    event.respondWith(networkFirst(event.request));
  }
});

// CACHE FIRST
async function cacheFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
  } catch {
    if (request.headers.get('Accept')?.includes('text/html')) {
      return caches.match(OFFLINE_URL);
    }
  }
}

// NETWORK FIRST
async function networkFirst(request) {
  const cache = await caches.open(CACHE_NAME);

  try {
    const response = await Promise.race([
      fetch(request),
      new Promise((_, r) => setTimeout(() => r('timeout'), NETWORK_TIMEOUT))
    ]);

    if (response && response.ok) {
      cache.put(request, response.clone());
      return response;
    }
  } catch {}

  const cached = await cache.match(request);
  if (cached) return cached;

  if (request.headers.get('Accept')?.includes('text/html')) {
    return caches.match(OFFLINE_URL);
  }
}
