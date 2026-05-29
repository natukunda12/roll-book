// ============================================================
// Cashbook Pro — Service Worker v3
// Full offline support + background sync
// ============================================================
const CACHE_VER    = 'v3';
const STATIC_CACHE = 'cashbook-static-' + CACHE_VER;
const PAGE_CACHE   = 'cashbook-pages-' + CACHE_VER;

// Files to pre-cache on install (app shell)
const APP_SHELL = [
  'offline.html',
  'manifest.json',
  'favicon.ico',
  'icons/icon-192x192.png',
  'icons/icon-512x512.png',
];

// ── Install ──────────────────────────────────────────────────
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then(c => c.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

// ── Activate ─────────────────────────────────────────────────
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => !k.endsWith(CACHE_VER)).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch ─────────────────────────────────────────────────────
self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);

  // Skip non-GET, non-same-origin
  if (req.method !== 'GET') return;
  if (url.protocol === 'chrome-extension:') return;

  // External fonts / CDN — cache first, network fallback
  if (url.hostname !== self.location.hostname) {
    e.respondWith(
      caches.match(req).then(cached => cached ||
        fetch(req).then(res => {
          if (res.ok) caches.open(STATIC_CACHE).then(c => c.put(req, res.clone()));
          return res;
        }).catch(() => new Response('', { status: 408 }))
      )
    );
    return;
  }

  // API endpoints — network only (no caching)
  if (url.pathname.includes('api_') || url.pathname.includes('sync.php')) return;

  // App pages — network first, cache fallback, offline page last
  e.respondWith(
    fetch(req)
      .then(res => {
        if (res.ok && req.method === 'GET') {
          const clone = res.clone();
          caches.open(PAGE_CACHE).then(c => c.put(req, clone));
        }
        return res;
      })
      .catch(() =>
        caches.match(req)
          .then(cached => cached || caches.match('offline.html'))
      )
  );
});

// ── Background Sync ───────────────────────────────────────────
self.addEventListener('sync', e => {
  if (e.tag === 'sync-transactions') {
    e.waitUntil(syncPending());
  }
});

async function syncPending() {
  const clients = await self.clients.matchAll();
  clients.forEach(c => c.postMessage({ type: 'SYNC_START' }));
  // Actual sync is triggered from the page JS
}

// ── Push Notifications ────────────────────────────────────────
self.addEventListener('push', e => {
  const data = e.data ? e.data.json() : { title: 'Cashbook Pro', body: 'New notification' };
  e.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: 'icons/icon-192x192.png',
      badge: 'icons/icon-96x96.png',
      vibrate: [200, 100, 200],
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.openWindow('/cashbook-pro/dashboard.php'));
});
