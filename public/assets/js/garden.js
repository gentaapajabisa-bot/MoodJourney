/**
 * Taman kupu-kupu (Dashboard): kupu-kupu pastel terbang pelan, mengikuti kursor atau sentuhan.
 * Ringan (Canvas 2D), berhenti saat tidak terlihat, dan hanya satu gambar diam untuk pengguna
 * yang meminta "kurangi gerakan".
 */
(function () {
    "use strict";

    var cv = document.getElementById("garden");
    if (!cv || !cv.getContext) return;

    var ctx = cv.getContext("2d");
    var host = cv.parentElement;
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var count = Math.max(1, Math.min(8, parseInt(cv.dataset.count || "1", 10) || 1));

    /* ---------- warna ---------- */
    function hexToRgb(hex) {
        var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || "");
        return m ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)] : [154, 140, 255];
    }
    function pastel(hex, t) { // campur dengan putih
        var c = hexToRgb(hex);
        return "rgb(" + c.map(function (v) { return Math.round(v + (255 - v) * t); }).join(",") + ")";
    }
    var USER = [cv.dataset.c1 || "#9a8cff", cv.dataset.c2 || "#6d5ce8"];
    var PASTELS = [
        ["#ffb3cf", "#d9a7f5"], ["#a9d4ff", "#b9b2ff"], ["#a9ead0", "#a6d8ff"],
        ["#ffe08a", "#ffb199"], ["#c9b8ff", "#ffb8e0"]
    ];
    var FLOWERS = ["#ffc2d9", "#ffe4a8", "#d6c9ff", "#bfe3ff", "#ffcfb8"];

    /* ---------- bentuk kupu-kupu (sama dengan SVG di situs) ---------- */
    var UPPER = new Path2D("M102 98 C 118 52, 168 24, 184 52 C 196 76, 160 104, 102 104 Z");
    var LOWER = new Path2D("M102 106 C 150 106, 176 130, 160 158 C 148 176, 118 160, 102 124 Z");

    var W = 0, H = 0, dpr = 1;
    var flies = [], flowers = [], dust = [], petals = [];
    var pointer = { x: 0, y: 0, active: false, last: 0 };

    function rand(a, b) { return a + Math.random() * (b - a); }

    function makeFly(i) {
        var depth = rand(0.65, 1);
        var colors = i === 0 ? USER : PASTELS[Math.floor(Math.random() * PASTELS.length)];
        return {
            x: rand(30, Math.max(40, W - 30)), y: rand(30, Math.max(40, H * 0.6)),
            vx: rand(-20, 20), vy: rand(-20, 20),
            tx: 0, ty: 0, next: 0,
            depth: depth, size: 38 + depth * 32 + (i === 0 ? 10 : 0),
            phase: rand(0, 6.28), orbit: rand(0, 6.28), orbitSpeed: rand(0.8, 1.6) * (Math.random() < 0.5 ? -1 : 1),
            orbitR: rand(26, 70), c1: pastel(colors[0], i === 0 ? 0.3 : 0.1), c2: pastel(colors[1], i === 0 ? 0.3 : 0.1),
            angle: -Math.PI / 2, flap: 1
        };
    }

    function build() {
        flies = [];
        for (var i = 0; i < count; i++) flies.push(makeFly(i));
        flowers = [];
        var n = Math.max(5, Math.floor(W / 64));
        for (var j = 0; j < n; j++) {
            flowers.push({
                x: (j + 0.5) * (W / n) + rand(-14, 14), h: rand(24, 62), r: rand(6, 10),
                c: FLOWERS[Math.floor(Math.random() * FLOWERS.length)], ph: rand(0, 6.28)
            });
        }
        dust = [];
        for (var k = 0; k < 16; k++) dust.push({ x: rand(0, W), y: rand(0, H), r: rand(1, 2.4), s: rand(4, 12), ph: rand(0, 6.28) });
    }

    function resize() {
        var r = host.getBoundingClientRect();
        dpr = Math.min(2, window.devicePixelRatio || 1);
        W = Math.max(1, r.width);
        H = Math.max(1, r.height);
        cv.width = Math.round(W * dpr);
        cv.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        build();
        if (reduce) draw(0);
    }

    /* ---------- gambar ---------- */
    function drawFlower(f, t) {
        var sway = Math.sin(t * 0.9 + f.ph) * 6;
        var tx = f.x + sway, ty = H - f.h;
        ctx.strokeStyle = "rgba(140,205,165,.9)";
        ctx.lineWidth = 3;
        ctx.lineCap = "round";
        ctx.beginPath();
        ctx.moveTo(f.x, H + 4);
        ctx.quadraticCurveTo(f.x + sway * 0.2, H - f.h * 0.5, tx, ty);
        ctx.stroke();
        ctx.fillStyle = f.c;
        for (var p = 0; p < 6; p++) {
            var a = (p / 6) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(tx + Math.cos(a) * f.r, ty + Math.sin(a) * f.r, f.r * 0.75, 0, 6.2832);
            ctx.fill();
        }
        ctx.fillStyle = "#ffd98a";
        ctx.beginPath();
        ctx.arc(tx, ty, f.r * 0.6, 0, 6.2832);
        ctx.fill();
    }

    function drawFly(f) {
        var s = f.size / 200;
        ctx.save();
        ctx.translate(f.x, f.y);
        ctx.rotate(f.angle + Math.PI / 2);
        ctx.scale(s, s);
        ctx.translate(-100, -108);

        var g = ctx.createLinearGradient(100, 40, 190, 170);
        g.addColorStop(0, f.c1);
        g.addColorStop(1, f.c2);

        for (var side = -1; side <= 1; side += 2) {
            ctx.save();
            ctx.translate(100, 0);
            ctx.scale(side * f.flap, 1);
            ctx.translate(-100, 0);
            ctx.fillStyle = g;
            ctx.strokeStyle = "rgba(90,70,130,.22)";
            ctx.lineWidth = 1.6;
            ctx.fill(UPPER); ctx.stroke(UPPER);
            ctx.fill(LOWER); ctx.stroke(LOWER);
            ctx.fillStyle = "rgba(255,255,255,.7)";
            ctx.beginPath(); ctx.arc(160, 58, 8, 0, 6.2832); ctx.fill();
            ctx.beginPath(); ctx.arc(139, 141, 6, 0, 6.2832); ctx.fill();
            ctx.restore();
        }

        ctx.strokeStyle = "#6b5f8a";
        ctx.lineWidth = 2.2;
        ctx.lineCap = "round";
        ctx.beginPath();
        ctx.moveTo(97, 72); ctx.quadraticCurveTo(90, 56, 78, 48);
        ctx.moveTo(103, 72); ctx.quadraticCurveTo(110, 56, 122, 48);
        ctx.stroke();
        ctx.fillStyle = "#6b5f8a";
        ctx.beginPath(); ctx.ellipse(100, 112, 5.5, 30, 0, 0, 6.2832); ctx.fill();
        ctx.beginPath(); ctx.arc(100, 78, 7.5, 0, 6.2832); ctx.fill();
        ctx.restore();
    }

    function draw(t) {
        ctx.clearRect(0, 0, W, H);
        dust.forEach(function (d) {
            ctx.fillStyle = "rgba(255,255,255,.65)";
            ctx.beginPath();
            ctx.arc(d.x + Math.sin(t * 0.5 + d.ph) * 8, d.y, d.r, 0, 6.2832);
            ctx.fill();
        });
        flowers.forEach(function (f) { drawFlower(f, t); });
        flies.slice().sort(function (a, b) { return a.depth - b.depth; }).forEach(drawFly);
        petals.forEach(function (p) {
            ctx.save();
            ctx.globalAlpha = Math.max(0, p.life);
            ctx.translate(p.x, p.y);
            ctx.rotate(p.r);
            ctx.fillStyle = p.c;
            ctx.beginPath();
            ctx.ellipse(0, 0, p.s, p.s * 0.55, 0, 0, 6.2832);
            ctx.fill();
            ctx.restore();
        });
    }

    /* ---------- gerak ---------- */
    function update(dt, t) {
        var pointerOn = pointer.active && t - pointer.last < 2.5;

        flies.forEach(function (f) {
            if (pointerOn) {
                f.orbit += dt * f.orbitSpeed;
                f.tx = pointer.x + Math.cos(f.orbit) * f.orbitR;
                f.ty = pointer.y + Math.sin(f.orbit) * f.orbitR * 0.7;
            } else if (t > f.next) {
                f.tx = rand(24, Math.max(40, W - 24));
                f.ty = rand(24, Math.max(40, H * 0.72));
                f.next = t + rand(2.2, 5);
            }

            var dx = f.tx - f.x, dy = f.ty - f.y;
            var dist = Math.hypot(dx, dy) || 1;
            var max = (pointerOn ? 120 : 52) * f.depth;
            var want = Math.min(max, dist * 1.4);
            f.vx += ((dx / dist) * want - f.vx) * Math.min(1, dt * 1.7);
            f.vy += ((dy / dist) * want - f.vy) * Math.min(1, dt * 1.7);
            f.vy += Math.sin(t * 3.1 + f.phase) * 26 * dt; // gerak naik-turun khas kupu-kupu

            f.x += f.vx * dt;
            f.y += f.vy * dt;
            f.x = Math.max(8, Math.min(W - 8, f.x));
            f.y = Math.max(8, Math.min(H - 14, f.y));

            var speed = Math.hypot(f.vx, f.vy);
            if (speed > 6) {
                var target = Math.atan2(f.vy, f.vx);
                var d = Math.atan2(Math.sin(target - f.angle), Math.cos(target - f.angle));
                f.angle += d * Math.min(1, dt * 3);
            }
            f.phase += dt * (6 + speed * 0.04);
            f.flap = 0.35 + 0.65 * Math.abs(Math.cos(f.phase));
        });

        dust.forEach(function (d) {
            d.y -= d.s * dt;
            if (d.y < -4) { d.y = H + 4; d.x = rand(0, W); }
        });

        petals = petals.filter(function (p) { return p.life > 0; });
        petals.forEach(function (p) {
            p.x += p.vx * dt; p.y += p.vy * dt; p.vy += 60 * dt; p.vx *= 1 - dt * 0.8;
            p.r += p.vr * dt; p.life -= dt * 0.7;
        });
    }

    /* ---------- loop ---------- */
    var running = false, visible = true, last = 0, raf = 0;

    function frame(now) {
        raf = 0;
        if (!running) return;
        var t = now / 1000;
        var dt = Math.min(0.05, last ? t - last : 0.016);
        last = t;
        update(dt, t);
        draw(t);
        raf = requestAnimationFrame(frame);
    }
    function start() {
        if (reduce || running || !visible || document.hidden) return;
        running = true;
        last = 0;
        raf = requestAnimationFrame(frame);
    }
    function stop() {
        running = false;
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    }

    /* ---------- interaksi ---------- */
    function pos(e) {
        var r = cv.getBoundingClientRect();
        pointer.x = e.clientX - r.left;
        pointer.y = e.clientY - r.top;
        pointer.active = true;
        pointer.last = performance.now() / 1000;
    }
    if (!reduce) {
        host.addEventListener("pointermove", pos);
        host.addEventListener("pointerleave", function () { pointer.active = false; });
        host.addEventListener("pointerdown", function (e) {
            pos(e);
            for (var i = 0; i < 9; i++) {
                var a = rand(0, 6.28), s = rand(30, 110);
                petals.push({
                    x: pointer.x, y: pointer.y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 40,
                    r: rand(0, 3), vr: rand(-5, 5), s: rand(4, 8), life: 1, c: FLOWERS[i % FLOWERS.length]
                });
            }
        });
    }

    if ("IntersectionObserver" in window) {
        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            visible ? start() : stop();
        }, { threshold: 0.05 }).observe(host);
    }
    document.addEventListener("visibilitychange", function () { document.hidden ? stop() : start(); });

    var resizeTimer = 0;
    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resize, 150);
    });

    resize();
    start();
})();
