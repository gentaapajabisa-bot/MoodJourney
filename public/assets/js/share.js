/**
 * Kartu bagikan: gambar 1080x1350 dibuat di browser (canvas). Tidak ada data diary yang dipakai,
 * hanya angka ringkas dan warna kupu-kupu. Dibagikan lewat Web Share kalau ada, atau diunduh.
 */
(function () {
    "use strict";

    var cv = document.getElementById("share-canvas");
    if (!cv || !cv.getContext) return;
    var ctx = cv.getContext("2d");
    var W = cv.width, H = cv.height;

    var D = {
        days: parseInt(cv.dataset.days, 10) || 0,
        stage: cv.dataset.stage || "egg",
        label: cv.dataset.stageLabel || "",
        logged: parseInt(cv.dataset.logged, 10) || 0,
        longest: parseInt(cv.dataset.longest, 10) || 0,
        name: cv.dataset.name || "",
        c1: cv.dataset.c1 || "#9a8cff",
        c2: cv.dataset.c2 || "#6d5ce8"
    };

    var THEMES = {
        lilac: { bg: ["#efe9ff", "#fbeaf5"], blob: ["#cfc3ff", "#ffd3e8", "#c4e6ff"], ink: "#3a3170", soft: "#7d73b0" },
        mint:  { bg: ["#e3f6ee", "#e6f0ff"], blob: ["#b8ead3", "#bcd9ff", "#e3d6ff"], ink: "#24564a", soft: "#5f8f82" },
        peach: { bg: ["#fff0e6", "#ffe6ef"], blob: ["#ffd4bd", "#ffc2d9", "#ffe9a8"], ink: "#6b3a2e", soft: "#a67667" },
        night: { bg: ["#1f1b3a", "#2e2550"], blob: ["#4a3f8f", "#6b4a9a", "#2f5f8f"], ink: "#f3efff", soft: "#b9b0e6" }
    };
    var state = { theme: "lilac", showName: false, showStats: true };

    function hexRgb(h) {
        var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(h || "");
        return m ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)] : [154, 140, 255];
    }
    function pastel(h, t) {
        var c = hexRgb(h);
        return "rgb(" + c.map(function (v) { return Math.round(v + (255 - v) * t); }).join(",") + ")";
    }
    function rr(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    var UPPER = new Path2D("M102 98 C 118 52, 168 24, 184 52 C 196 76, 160 104, 102 104 Z");
    var LOWER = new Path2D("M102 106 C 150 106, 176 130, 160 158 C 148 176, 118 160, 102 124 Z");

    function drawButterfly(cx, cy, size, c1, c2) {
        var s = size / 200;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(s, s);
        ctx.translate(-100, -100);
        for (var side = 0; side < 2; side++) {
            ctx.save();
            if (side === 1) { ctx.translate(200, 0); ctx.scale(-1, 1); }
            var g1 = ctx.createLinearGradient(100, 30, 190, 110);
            g1.addColorStop(0, pastel(c1, 0.25)); g1.addColorStop(1, pastel(c2, 0.25));
            ctx.fillStyle = g1; ctx.fill(UPPER);
            var g2 = ctx.createLinearGradient(100, 106, 170, 170);
            g2.addColorStop(0, pastel(c2, 0.35)); g2.addColorStop(1, pastel(c1, 0.35));
            ctx.fillStyle = g2; ctx.fill(LOWER);
            ctx.lineWidth = 1.6; ctx.strokeStyle = "rgba(60,45,110,.28)"; ctx.stroke(UPPER); ctx.stroke(LOWER);
            ctx.fillStyle = "rgba(255,255,255,.55)";
            ctx.beginPath(); ctx.arc(150, 70, 8, 0, 6.3); ctx.fill();
            ctx.beginPath(); ctx.arc(136, 138, 5, 0, 6.3); ctx.fill();
            ctx.restore();
        }
        ctx.fillStyle = "#4a3f7a";
        rr(96, 88, 8, 78, 4); ctx.fill();
        ctx.strokeStyle = "#4a3f7a"; ctx.lineWidth = 3; ctx.lineCap = "round";
        ctx.beginPath(); ctx.moveTo(98, 90); ctx.quadraticCurveTo(90, 64, 76, 56); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(102, 90); ctx.quadraticCurveTo(110, 64, 124, 56); ctx.stroke();
        ctx.restore();
    }

    function drawStage(cx, cy, size) {
        var st = D.stage;
        if (st === "young" || st === "adult") {
            drawButterfly(cx, cy, size * (st === "adult" ? 1.05 : 0.88), D.c1, D.c2);
            return;
        }
        ctx.save();
        ctx.translate(cx, cy);
        // daun
        ctx.fillStyle = "#8fd5a8";
        ctx.beginPath(); ctx.ellipse(0, size * 0.28, size * 0.46, size * 0.12, 0, 0, 6.3); ctx.fill();
        if (st === "egg") {
            ctx.fillStyle = pastel(D.c1, 0.55);
            ctx.beginPath(); ctx.ellipse(0, size * 0.1, size * 0.13, size * 0.17, 0, 0, 6.3); ctx.fill();
            ctx.fillStyle = "rgba(255,255,255,.6)";
            ctx.beginPath(); ctx.ellipse(-size * 0.04, size * 0.05, size * 0.03, size * 0.05, 0, 0, 6.3); ctx.fill();
        } else if (st === "larva") {
            for (var i = 0; i < 6; i++) {
                ctx.fillStyle = pastel(i % 2 ? D.c1 : D.c2, 0.45);
                ctx.beginPath(); ctx.arc(-size * 0.25 + i * size * 0.1, size * 0.16 - Math.sin(i * 0.9) * size * 0.05, size * 0.075, 0, 6.3); ctx.fill();
            }
            ctx.fillStyle = "#4a3f7a";
            ctx.beginPath(); ctx.arc(size * 0.27, size * 0.14, size * 0.012, 0, 6.3); ctx.fill();
        } else {
            ctx.strokeStyle = "#8aa77e"; ctx.lineWidth = size * 0.012;
            ctx.beginPath(); ctx.moveTo(0, -size * 0.3); ctx.lineTo(0, -size * 0.1); ctx.stroke();
            var g = ctx.createLinearGradient(0, -size * 0.1, 0, size * 0.25);
            g.addColorStop(0, pastel("#b7e0b0", 0.2)); g.addColorStop(1, pastel(D.c1, 0.3));
            ctx.fillStyle = g;
            ctx.beginPath(); ctx.ellipse(0, size * 0.08, size * 0.12, size * 0.2, 0, 0, 6.3); ctx.fill();
            ctx.strokeStyle = "rgba(60,45,110,.22)"; ctx.lineWidth = 3;
            for (var j = 0; j < 4; j++) { ctx.beginPath(); ctx.moveTo(-size * 0.1, size * (0.0 + j * 0.06)); ctx.lineTo(size * 0.1, size * (0.0 + j * 0.06)); ctx.stroke(); }
        }
        ctx.restore();
    }

    function draw() {
        var t = THEMES[state.theme];
        var bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, t.bg[0]); bg.addColorStop(1, t.bg[1]);
        ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);

        // gumpalan warna lembut
        [[180, 220, 420, 0], [920, 420, 380, 1], [300, 1150, 460, 2]].forEach(function (b) {
            var g = ctx.createRadialGradient(b[0], b[1], 0, b[0], b[1], b[2]);
            g.addColorStop(0, t.blob[b[3]] + "cc"); g.addColorStop(1, t.blob[b[3]] + "00");
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        });

        // bunga kecil di bawah
        var fl = ["#ffc2d9", "#ffe4a8", "#d6c9ff", "#bfe3ff", "#ffcfb8"];
        for (var i = 0; i < 9; i++) {
            var fx = 70 + i * 117, fh = 56 + ((i * 53) % 46);
            ctx.strokeStyle = "rgba(110,170,130,.55)"; ctx.lineWidth = 6; ctx.lineCap = "round";
            ctx.beginPath(); ctx.moveTo(fx, H); ctx.quadraticCurveTo(fx + 12, H - fh / 2, fx, H - fh); ctx.stroke();
            ctx.fillStyle = fl[i % 5]; ctx.beginPath(); ctx.arc(fx, H - fh, 20, 0, 6.3); ctx.fill();
            ctx.fillStyle = "rgba(255,255,255,.7)"; ctx.beginPath(); ctx.arc(fx, H - fh, 7, 0, 6.3); ctx.fill();
        }

        // judul
        ctx.textAlign = "center"; ctx.textBaseline = "alphabetic";
        ctx.fillStyle = t.soft; ctx.font = "600 34px Inter, system-ui, sans-serif";
        var head = state.showName && D.name ? "Perjalanan " + D.name : "Perjalanan kupu-kupuku";
        ctx.fillText(head, W / 2, 130);

        // kupu-kupu / tahap
        drawStage(W / 2, 480, 520);

        // angka besar
        ctx.fillStyle = t.ink; ctx.font = "800 190px Inter, system-ui, sans-serif";
        ctx.fillText(String(D.days), W / 2, 905);
        ctx.fillStyle = t.soft; ctx.font = "600 40px Inter, system-ui, sans-serif";
        ctx.fillText("hari perjalanan", W / 2, 965);

        // chip tahap
        var chip = D.label;
        ctx.font = "700 40px Inter, system-ui, sans-serif";
        var cw = ctx.measureText(chip).width + 80;
        rr(W / 2 - cw / 2, 1000, cw, 76, 38);
        ctx.fillStyle = state.theme === "night" ? "rgba(255,255,255,.14)" : "rgba(255,255,255,.7)"; ctx.fill();
        ctx.fillStyle = t.ink; ctx.textBaseline = "middle";
        ctx.fillText(chip, W / 2, 1040);
        ctx.textBaseline = "alphabetic";

        if (state.showStats) {
            ctx.fillStyle = t.soft; ctx.font = "600 34px Inter, system-ui, sans-serif";
            ctx.fillText(D.logged + " hari tercatat  ·  streak terpanjang " + D.longest, W / 2, 1135);
        }

        // merek
        ctx.fillStyle = t.ink; ctx.font = "800 40px Inter, system-ui, sans-serif";
        ctx.fillText("MoodJourney", W / 2, 1210);
    }

    var note = document.getElementById("share-note");
    function say(msg) { if (note) note.textContent = msg; }

    function toBlob() {
        return new Promise(function (resolve) {
            try { cv.toBlob(function (b) { resolve(b); }, "image/png"); } catch (e) { resolve(null); }
        });
    }

    function render() { draw(); }
    // pastikan font dimuat dulu agar tulisan tidak berganti setelah digambar
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(render);
    }
    render();

    document.querySelectorAll(".share-theme").forEach(function (b) {
        b.addEventListener("click", function () {
            state.theme = b.dataset.theme;
            document.querySelectorAll(".share-theme").forEach(function (o) {
                var on = o === b;
                o.classList.toggle("is-active", on);
                o.setAttribute("aria-checked", on ? "true" : "false");
            });
            render();
        });
    });
    var nameBox = document.getElementById("share-name");
    var statsBox = document.getElementById("share-stats");
    if (nameBox) nameBox.addEventListener("change", function () { state.showName = nameBox.checked; render(); });
    if (statsBox) statsBox.addEventListener("change", function () { state.showStats = statsBox.checked; render(); });

    document.getElementById("share-download").addEventListener("click", async function () {
        var blob = await toBlob();
        if (!blob) { say("Gambar belum bisa dibuat di perangkat ini."); return; }
        var a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "moodjourney-perjalanan.png";
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        say("Gambar diunduh.");
    });

    document.getElementById("share-send").addEventListener("click", async function () {
        var blob = await toBlob();
        if (!blob) { say("Gambar belum bisa dibuat di perangkat ini."); return; }
        var file = new File([blob], "moodjourney-perjalanan.png", { type: "image/png" });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: "Perjalanan kupu-kupuku", text: "Perjalananku di MoodJourney" });
                say("");
            } catch (e) {
                if (e && e.name !== "AbortError") say("Tidak bisa membagikan langsung. Coba tombol Unduh gambar.");
            }
        } else {
            say("Perangkat ini belum mendukung berbagi langsung. Gunakan Unduh gambar, lalu kirim dari galeri.");
        }
    });
})();
