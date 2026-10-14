/** Notifikasi push: berlangganan lewat service worker, simpan ke server. */
(function () {
    "use strict";
    var box = document.getElementById("push-box");
    if (!box || box.dataset.enabled !== "1") return;

    var onBtn = document.getElementById("push-on");
    var offBtn = document.getElementById("push-off");
    var testBtn = document.getElementById("push-test");
    var status = document.getElementById("push-status");
    function say(m) { if (status) status.textContent = m; }

    var supported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
    if (!supported) {
        onBtn.disabled = true;
        say("Browser ini belum mendukung notifikasi push. Di iPhone, pasang dulu MoodJourney ke Layar Utama.");
        return;
    }

    function keyBytes(b64) {
        var pad = "=".repeat((4 - (b64.length % 4)) % 4);
        var raw = atob((b64 + pad).replace(/-/g, "+").replace(/_/g, "/"));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }
    function post(data) {
        var fd = new FormData();
        fd.append("_csrf", box.dataset.csrf);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch("/push.php", { method: "POST", body: fd, credentials: "same-origin" })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    }
    function getSub() {
        return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); });
    }
    function paint(active) {
        onBtn.hidden = active;
        offBtn.hidden = !active;
        testBtn.hidden = !active;
        say(active ? "Aktif di perangkat ini." : (Notification.permission === "denied" ? "Notifikasi diblokir di browser. Ubah izinnya di pengaturan situs, lalu coba lagi." : ""));
        if (Notification.permission === "denied") onBtn.disabled = true;
    }

    getSub().then(function (sub) { paint(!!sub); }).catch(function () { paint(false); });

    onBtn.addEventListener("click", function () {
        onBtn.disabled = true;
        say("Meminta izin…");
        Notification.requestPermission().then(function (perm) {
            if (perm !== "granted") { onBtn.disabled = perm === "denied"; say("Izin notifikasi tidak diberikan."); return null; }
            return navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(box.dataset.key) });
            }).then(function (sub) {
                var j = sub.toJSON();
                return post({ action: "subscribe", endpoint: sub.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }).then(function (res) {
                    if (!res.ok) { sub.unsubscribe(); throw new Error("simpan"); }
                    paint(true);
                    onBtn.disabled = false;
                });
            });
        }).catch(function () {
            onBtn.disabled = false;
            say("Belum berhasil mengaktifkan. Coba lagi sebentar lagi.");
        });
    });

    offBtn.addEventListener("click", function () {
        getSub().then(function (sub) {
            if (!sub) { paint(false); return; }
            return post({ action: "unsubscribe", endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); }).then(function () { paint(false); });
        }).catch(function () { say("Belum berhasil mematikan. Coba lagi."); });
    });

    testBtn.addEventListener("click", function () {
        testBtn.disabled = true;
        say("Mengirim…");
        post({ action: "test" }).then(function (res) {
            say(res.ok ? "Terkirim. Notifikasinya akan muncul sebentar lagi." : "Belum terkirim. Coba matikan lalu aktifkan lagi.");
        }).then(function () { testBtn.disabled = false; });
    });
})();
