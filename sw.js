// Service Worker — Prode Vale 4
const CACHE = 'vale4-v1';
const SHELL = ['./','./index.html'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys =>
    Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
  ));
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  // API siempre va a la red
  if (e.request.url.includes('api.php')) return;
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});
