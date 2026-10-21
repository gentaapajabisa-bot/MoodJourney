/*
 * Service worker MoodJourney.
 * - Aset statis (CSS, JS, ikon): cache dengan penyegaran di latar belakang.
 * - Halaman: SELALU lewat jaringan. Halaman berisi data pribadi tidak pernah disimpan di cache.
 *   Kalau offline, ditampilkan /offline.html.
 */
const VERSION = "mj-v16";
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

    // CSS dan JS: jaringan dulu supaya pembaruan langsung terlihat, cache hanya cadangan offline
    if (url.pathname.startsWith("/assets/") && /\.(css|js)$/.test(url.pathname)) {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (res && res.ok) {
                        const copy = res.clone();
                        caches.open(STATIC_CACHE).then((c) => c.put(req, copy));
                    }
                    return res;
                })
                .catch(() => caches.match(req))
        );
        return;
    }

    // Aset lain (ikon, gambar): ambil dari cache, perbarui di latar belakang
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

// Notifikasi push dari server
self.addEventListener("push", (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : "" }; }
    const title = data.title || "MoodJourney";
    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || "Sudah sempat mencatat mood hari ini?",
            icon: "/assets/icons/icon-192.png",
            badge: "/assets/icons/icon-192.png",
            tag: "mj-push",
            data: { url: typeof data.url === "string" && data.url.startsWith("/") ? data.url : "/diary.php" },
        })
    );
});

// Klik pada notifikasi membuka halaman tujuan (default: catat mood)
self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || "/diary.php";
    event.waitUntil(
        self.clients.matchAll({ type: "window", includeUninstalled: false }).then((clients) => {
            for (const c of clients) {
                if ("focus" in c) {
                    c.navigate(target);
                    return c.focus();
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
