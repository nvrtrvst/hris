/* HRIS Yayasan — Service Worker */
// Bump CACHE_NAME setiap deploy: SW baru meng-install (skipWaiting), langsung
// mengambil alih tab yang terbuka (clients.claim), lalu activate menghapus cache
// versi lama — sehingga device yang "nyangkut" sembuh tanpa aksi user.
const CACHE_NAME = 'hris-mobile-v20';

// HANYA aset statis yang aman di-cache (ikon + manifest).
// HTML shell TIDAK di-precache lagi: halaman selalu diambil dari jaringan, jadi
// tidak mungkin menyajikan HTML/bundle usang setelah deploy.
const SHELL_ASSETS = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    '/icons/icon-maskable-512.png',
];

// Prefix aset ber-hash yang isinya immutable (nama file berubah tiap build).
const CACHEABLE_PREFIXES = ['/build/', '/icons/'];
const CACHEABLE_FILES = ['/manifest.json'];

const isStaticAsset = (url) =>
    CACHEABLE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix)) ||
    CACHEABLE_FILES.includes(url.pathname);

// Request Inertia (XHR antar-halaman) & permintaan data JSON TIDAK BOLEH
// dilayani dari cache: props bisa berubah antar deploy dan JSON basi membuat
// komponen React crash / data salah. Selalu ke jaringan.
const isInertiaOrData = (request) =>
    request.headers.get('X-Inertia') !== null ||
    (request.headers.get('Accept') || '').includes('application/json');

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    let url;
    try {
        url = new URL(request.url);
    } catch {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    // Navigasi (HTML) dan semua permintaan Inertia/JSON: network-only.
    // Tidak ada fallback cache — lebih baik gagal jelas daripada menyajikan
    // halaman/JSON usang yang bikin tampilan blank.
    if (request.mode === 'navigate' || url.pathname === '/' || isInertiaOrData(request)) {
        event.respondWith(fetch(request));
        return;
    }

    // Foto presensi protected: network-first, cache hanya fallback offline.
    if (url.pathname.includes('/presensi/photo/')) {
        event.respondWith(fetch(request).catch(() => caches.match(request)));
        return;
    }

    // Selain aset statis: biarkan browser menanganinya (network biasa).
    if (!isStaticAsset(url)) {
        return;
    }

    // Aset statis immutable: cache-first + simpan untuk pemakaian berikutnya.
    event.respondWith(
        caches.match(request).then((hit) => {
            if (hit) {
                return hit;
            }

            return fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }

                return response;
            });
        })
    );
});

// Event push: tampilkan notifikasi + fokus tab saat diklik.
self.addEventListener('push', (event) => {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'HRIS Yayasan', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'HRIS Yayasan';
    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/icon-192.png',
        data: { url: data.url || '/' },
        vibrate: [100, 50, 100],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return clients.openWindow(target);
        })
    );
});
