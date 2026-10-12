/*
 * Service worker MoodJourney.
 * - Aset statis (CSS, JS, ikon): cache dengan penyegaran di latar belakang.
 * - Halaman: SELALU lewat jaringan. Halaman berisi data pribadi tidak pernah disimpan di cache.
 *   Kalau offline, ditampilkan /offline.html.
 */
const VERSION = "mj-v11";
const STATIC_CACHE = "mj-static-" + VERSION;
const PRECACHE = ["/offline.html", "/assets/css/style.css", "/assets/js/app.js", "/assets/icons/icon-192.png"];

self.addEventListener("install", (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith("mj-static-") && k !== STATIC_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    const req = event.request;
    if (req.method !== "GET") return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Halaman: jaringan dulu, offline.html sebagai cadangan
    if (req.mode === "navigate") {
        event.respondWith(fetch(req).catch(() => caches.match("/offline.html")));
        return;
    }

    // Aset statis: ambil dari cache, perbarui di latar belakang
    if (url.pathname.startsWith("/assets/")) {
        event.respondWith(
            caches.open(STATIC_CACHE).then((cache) =>
                cache.match(req).then((cached) => {
                    const network = fetch(req)
                        .then((res) => {
                            if (res && res.ok) cache.put(req, res.clone());
                            return res;
                        })
                        .catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
    // selain itu (chat/poll, dsb.) langsung ke jaringan
});

// Klik pada notifikasi pengingat membuka halaman catat mood
self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: "window", includeUninstalled: false }).then((clients) => {
            for (const c of clients) {
                if ("focus" in c) {
                    c.navigate("/diary.php");
                    return c.focus();
                }
            }
            return self.clients.openWindow("/diary.php");
        })
    );
});
