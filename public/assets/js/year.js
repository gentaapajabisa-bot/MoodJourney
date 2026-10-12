/**
 * Kalender setahun (year in pixels): info hari saat disorot, navigasi panah, ganti pewarnaan,
 * dan unduh sebagai gambar (hanya warna, tanpa isi catatan).
 */
(function () {
    "use strict";

    var grid = document.getElementById("yip");
    if (!grid) return;

    var info = document.getElementById("yip-info");
    var cells = Array.prototype.slice.call(grid.querySelectorAll(".yip-cell"));
    var byKey = {};
    cells.forEach(function (c) {
        var p = c.dataset.date.split("-");
        c._m = parseInt(p[1], 10);
        c._d = parseInt(p[2], 10);
        byKey[c._m + "-" + c._d] = c;
    });

    /* ---------- info hari ---------- */
    function show(cell) {
        var n = parseInt(cell.dataset.n || "0", 10);
        info.replaceChildren();

        var title = document.createElement("strong");
        title.textContent = cell.dataset.label;
        info.appendChild(title);

        var text = document.createElement("span");
        if (n > 0) {
            text.textContent = " · " + cell.dataset.emoji + " " + cell.dataset.mood + " · " + n + " catatan";
        } else if (cell.dataset.future === "1") {
            text.textContent = " · belum tiba";
        } else {
            text.textContent = " · belum ada catatan";
        }
        info.appendChild(text);

        var link = null;
        if (n > 0) {
            link = document.createElement("a");
            link.href = "/history.php?from=" + cell.dataset.date + "&to=" + cell.dataset.date;
            link.textContent = "Lihat catatan →";
        } else if (cell.dataset.future !== "1") {
            link = document.createElement("a");
            link.href = "/diary.php?date=" + cell.dataset.date;
            link.textContent = "Catat hari ini →";
            if (cell.classList.contains("is-today") === false) link.textContent = "Isi catatan →";
        }
        if (link) {
            link.className = "link yip-info-link";
            info.appendChild(link);
        }
    }

    cells.forEach(function (c) {
        c.addEventListener("mouseenter", function () { show(c); });
        c.addEventListener("focus", function () { show(c); setFocus(c, false); });
        c.addEventListener("click", function () { show(c); setFocus(c, false); });
    });

    /* ---------- fokus & panah (roving tabindex) ---------- */
    var current = null;
    function setFocus(cell, focus) {
        if (current) current.tabIndex = -1;
        current = cell;
        cell.tabIndex = 0;
        if (focus) cell.focus();
    }
    var start = grid.querySelector(".is-today") || cells[cells.length - 1] || cells[0];
    if (start) { start.tabIndex = 0; current = start; }

    function find(m, d) {
        if (m < 1 || m > 12 || d < 1) return null;
        for (var dd = Math.min(d, 31); dd >= 1; dd--) {
            if (byKey[m + "-" + dd]) return byKey[m + "-" + dd];
        }
        return null;
    }

    grid.addEventListener("keydown", function (e) {
        if (!current || ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", "Home", "End"].indexOf(e.key) < 0) return;
        var mobile = window.matchMedia("(max-width: 720px)").matches;
        var dm = 0, dd = 0;
        if (e.key === "ArrowRight") { mobile ? dm = 1 : dd = 1; }
        if (e.key === "ArrowLeft")  { mobile ? dm = -1 : dd = -1; }
        if (e.key === "ArrowDown")  { mobile ? dd = 1 : dm = 1; }
        if (e.key === "ArrowUp")    { mobile ? dd = -1 : dm = -1; }
        var next = null;
        if (e.key === "Home") next = cells[0];
        else if (e.key === "End") next = cells[cells.length - 1];
        else {
            next = byKey[(current._m + dm) + "-" + (current._d + dd)];
            if (!next && dd === 0) next = find(current._m + dm, current._d);
        }
        if (next) {
            e.preventDefault();
            setFocus(next, true);
        }
    });

    /* ---------- pewarnaan ---------- */
    var modeBtns = document.querySelectorAll("[data-yip-mode]");
    function setMode(mode) {
        grid.dataset.mode = mode;
        modeBtns.forEach(function (b) { b.setAttribute("aria-pressed", b.dataset.yipMode === mode ? "true" : "false"); });
        try { localStorage.setItem("mj-yip-mode", mode); } catch (e) { /* abaikan */ }
    }
    modeBtns.forEach(function (b) { b.addEventListener("click", function () { setMode(b.dataset.yipMode); }); });
    try {
        var saved = localStorage.getItem("mj-yip-mode");
        if (saved === "mood" || saved === "score") setMode(saved);
    } catch (e) { /* abaikan */ }

    /* ---------- unduh gambar ---------- */
    var SCORE_COLORS = { 1: "#f6c9c7", 2: "#f8dfc3", 3: "#e7e4f0", 4: "#cfe9da", 5: "#a9dcc3" };
    var MONTHS = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    var dl = document.getElementById("yip-download");

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    if (dl) dl.addEventListener("click", function () {
        var mode = grid.dataset.mode;
        var year = grid.dataset.year;
        var cell = 34, gap = 6, left = 64, top = 118, pad = 36;
        var W = pad + left + 31 * (cell + gap) - gap + pad;
        var H = top + 12 * (cell + gap) - gap + 110;
        var scale = 1.5;

        var cv = document.createElement("canvas");
        cv.width = Math.round(W * scale);
        cv.height = Math.round(H * scale);
        var ctx = cv.getContext("2d");
        ctx.scale(scale, scale);

        var bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, "#f4efff");
        bg.addColorStop(1, "#fff0f6");
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = "#4b3bb5";
        ctx.font = "800 30px system-ui, -apple-system, 'Segoe UI', sans-serif";
        ctx.fillText("Setahun dalam piksel · " + year, pad, 56);
        ctx.fillStyle = "#8a7fb5";
        ctx.font = "600 15px system-ui, -apple-system, 'Segoe UI', sans-serif";
        ctx.fillText("MoodJourney", pad, 82);

        ctx.font = "600 13px system-ui, sans-serif";
        ctx.fillStyle = "#8a7fb5";
        ctx.textBaseline = "middle";
        for (var m = 1; m <= 12; m++) {
            ctx.fillText(MONTHS[m - 1], pad, top + (m - 1) * (cell + gap) + cell / 2);
        }

        cells.forEach(function (c) {
            var x = pad + left + (c._d - 1) * (cell + gap);
            var y = top + (c._m - 1) * (cell + gap);
            var has = c.classList.contains("has");
            roundRect(ctx, x, y, cell, cell, 8);
            if (has) {
                ctx.fillStyle = mode === "mood" ? (c.style.getPropertyValue("--mc").trim() || "#c9b8ff") : (SCORE_COLORS[c.dataset.score] || "#e7e4f0");
                ctx.fill();
            } else {
                ctx.fillStyle = c.dataset.future === "1" ? "rgba(120,100,180,.05)" : "rgba(120,100,180,.12)";
                ctx.fill();
            }
        });

        // legenda
        var ly = top + 12 * (cell + gap) + 26;
        ctx.textBaseline = "middle";
        ctx.font = "600 13px system-ui, sans-serif";
        ctx.fillStyle = "#8a7fb5";
        if (mode === "score") {
            ctx.fillText("Berat", pad, ly);
            for (var s = 1; s <= 5; s++) {
                roundRect(ctx, pad + 52 + (s - 1) * 30, ly - 10, 22, 22, 6);
                ctx.fillStyle = SCORE_COLORS[s];
                ctx.fill();
            }
            ctx.fillStyle = "#8a7fb5";
            ctx.fillText("Baik", pad + 52 + 5 * 30 + 6, ly);
        } else {
            var seen = {}, x2 = pad;
            cells.forEach(function (c) {
                if (!c.classList.contains("has") || seen[c.dataset.mood]) return;
                seen[c.dataset.mood] = true;
                roundRect(ctx, x2, ly - 10, 22, 22, 6);
                ctx.fillStyle = c.style.getPropertyValue("--mc").trim() || "#c9b8ff";
                ctx.fill();
                ctx.fillStyle = "#6b6190";
                ctx.fillText(c.dataset.mood, x2 + 30, ly);
                x2 += 30 + ctx.measureText(c.dataset.mood).width + 24;
            });
        }

        cv.toBlob(function (blob) {
            if (!blob) return;
            var a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = "moodjourney-setahun-" + year + ".png";
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        }, "image/png");
    });
})();
