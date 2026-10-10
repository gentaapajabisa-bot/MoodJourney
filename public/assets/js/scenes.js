/**
 * Adegan 3D MoodJourney (membutuhkan THREE r128 yang dimuat oleh app.js).
 *
 *  MJScenes.hero(el)          planet mood dengan emoji yang mengorbit (halaman depan)
 *  MJScenes.landscape(el, days, caption)   lanskap mood 3D (halaman statistik)
 *
 * Prinsip: ringan, berhenti saat tidak terlihat, dan tetap tenang untuk pengguna
 * yang meminta "kurangi gerakan".
 */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    function cssColor(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function emojiTexture(THREE, emoji) {
        var c = document.createElement("canvas");
        c.width = 128;
        c.height = 128;
        var ctx = c.getContext("2d");
        ctx.font = "92px 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(emoji, 64, 70);
        return new THREE.CanvasTexture(c);
    }

    /**
     * Pengatur render: hanya menggambar saat elemen terlihat dan tab aktif.
     * Dengan reduceMotion, hanya menggambar satu bingkai (dan saat ukuran berubah).
     */
    function createRunner(el, renderFrame) {
        var running = false;
        var visible = true;
        var last = performance.now();
        var raf = 0;

        function tick(now) {
            if (!running) return;
            var dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            renderFrame(dt, now / 1000);
            raf = requestAnimationFrame(tick);
        }

        function start() {
            if (running || reduceMotion) return;
            running = true;
            last = performance.now();
            raf = requestAnimationFrame(tick);
        }

        function stop() {
            running = false;
            cancelAnimationFrame(raf);
        }

        function sync() {
            if (visible && !document.hidden) start();
            else stop();
        }

        var io = null;
        if ("IntersectionObserver" in window) {
            io = new IntersectionObserver(function (entries) {
                visible = entries[0].isIntersecting;
                sync();
            });
            io.observe(el);
        }
        document.addEventListener("visibilitychange", sync);

        return {
            start: sync,
            stop: stop,
            once: function () { renderFrame(0, 0); },
            destroy: function () {
                stop();
                if (io) io.disconnect();
                document.removeEventListener("visibilitychange", sync);
            }
        };
    }

    function makeRenderer(THREE, el) {
        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: "low-power" });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(el.clientWidth, el.clientHeight);
        el.appendChild(renderer.domElement);
        return renderer;
    }

    /* ------------------------------------------------------------------ */
    /* 1. Planet mood                                                      */
    /* ------------------------------------------------------------------ */

    function hero(el) {
        var THREE = window.THREE;
        var moods = [];
        try { moods = JSON.parse(el.dataset.moods || "[]"); } catch (e) { moods = []; }
        if (!moods.length) moods = ["😄", "🙂", "😐", "😔"];

        var renderer = makeRenderer(THREE, el);
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(38, el.clientWidth / el.clientHeight, 0.1, 50);
        camera.position.set(0, 0.5, 7.4);

        scene.add(new THREE.AmbientLight(0xffffff, 0.75));
        var key = new THREE.DirectionalLight(0xffffff, 0.85);
        key.position.set(3, 4, 5);
        scene.add(key);

        var primary = new THREE.Color(cssColor("--primary", "#6d5ce8"));

        var world = new THREE.Group();
        scene.add(world);

        var core = new THREE.Mesh(
            new THREE.IcosahedronGeometry(1.2, 2),
            new THREE.MeshStandardMaterial({ color: primary, flatShading: true, roughness: 0.5, metalness: 0.1 })
        );
        world.add(core);

        var orbit = new THREE.Group();
        orbit.rotation.x = 0.42;
        orbit.rotation.z = -0.16;
        world.add(orbit);

        var ringMat = new THREE.MeshBasicMaterial({ color: primary, transparent: true, opacity: 0.35 });
        var ring = new THREE.Mesh(new THREE.TorusGeometry(2.25, 0.012, 8, 160), ringMat);
        ring.rotation.x = Math.PI / 2;
        orbit.add(ring);

        var sprites = moods.map(function (emoji, i) {
            var s = new THREE.Sprite(new THREE.SpriteMaterial({
                map: emojiTexture(THREE, emoji),
                transparent: true,
                depthWrite: false
            }));
            s.scale.set(0.78, 0.78, 1);
            s.userData.angle = (i / moods.length) * Math.PI * 2;
            orbit.add(s);
            return s;
        });

        function placeSprites() {
            sprites.forEach(function (s) {
                s.position.set(Math.cos(s.userData.angle) * 2.25, 0, Math.sin(s.userData.angle) * 2.25);
            });
        }
        placeSprites();

        var target = { x: 0, y: 0 };
        var current = { x: 0, y: 0 };

        function onPointer(e) {
            var r = el.getBoundingClientRect();
            target.y = ((e.clientX - (r.left + r.width / 2)) / window.innerWidth) * 0.9;
            target.x = ((e.clientY - (r.top + r.height / 2)) / window.innerHeight) * 0.5;
        }
        if (!reduceMotion) window.addEventListener("pointermove", onPointer, { passive: true });

        var runner = createRunner(el, function (dt, t) {
            core.rotation.y += dt * 0.25;
            core.position.y = Math.sin(t * 0.9) * 0.08;
            sprites.forEach(function (s) {
                s.userData.angle += dt * 0.32;
            });
            placeSprites();
            current.x += (target.x - current.x) * 0.06;
            current.y += (target.y - current.y) * 0.06;
            world.rotation.x = current.x;
            world.rotation.y = current.y;
            renderer.render(scene, camera);
        });

        function resize() {
            var w = el.clientWidth, h = el.clientHeight;
            if (!w || !h) return;
            renderer.setSize(w, h);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            if (reduceMotion) runner.once();
        }
        var ro = "ResizeObserver" in window ? new ResizeObserver(resize) : null;
        if (ro) ro.observe(el);

        // ikuti pergantian tema terang/gelap
        var mo = new MutationObserver(function () {
            primary.set(cssColor("--primary", "#6d5ce8"));
            core.material.color.copy(primary);
            ringMat.color.copy(primary);
            if (reduceMotion) runner.once();
        });
        mo.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });

        runner.once();
        runner.start();

        return {
            destroy: function () {
                runner.destroy();
                if (ro) ro.disconnect();
                mo.disconnect();
                window.removeEventListener("pointermove", onPointer);
                renderer.dispose();
                if (renderer.domElement.parentNode) renderer.domElement.parentNode.removeChild(renderer.domElement);
            }
        };
    }

    /* ------------------------------------------------------------------ */
    /* 2. Lanskap mood                                                     */
    /* ------------------------------------------------------------------ */

    var PALETTE = [0xf08f8a, 0xf2b46f, 0xb8b4d0, 0x7cc9a0, 0x3fb381];

    /**
     * @param {HTMLElement} el      wadah canvas
     * @param {Array<{s:number|null,w:number,t:string}>} days  s = skor 1-5 atau null, w = hari (0=Senin), t = teks
     * @param {HTMLElement|null} caption  elemen teks untuk info saat disorot
     */
    function landscape(el, days, caption) {
        var THREE = window.THREE;
        var renderer = makeRenderer(THREE, el);
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(36, el.clientWidth / el.clientHeight, 0.1, 200);

        scene.add(new THREE.AmbientLight(0xffffff, 0.8));
        var key = new THREE.DirectionalLight(0xffffff, 0.8);
        key.position.set(4, 8, 5);
        scene.add(key);

        var gap = 0.95;
        var first = days.length ? days[0].w : 0;
        var rows = Math.max(1, Math.ceil((first + days.length) / 7));
        var width = 7 * gap;
        var depth = rows * gap;

        var geo = new THREE.BoxGeometry(0.78, 1, 0.78);
        geo.translate(0, 0.5, 0); // alas di y = 0, sehingga scale.y = tinggi batang

        var mesh = new THREE.InstancedMesh(
            geo,
            new THREE.MeshStandardMaterial({ roughness: 0.6, metalness: 0.05 }),
            Math.max(1, days.length)
        );

        var matrix = new THREE.Matrix4();
        var pos = new THREE.Vector3();
        var quat = new THREE.Quaternion();
        var scale = new THREE.Vector3();
        var emptyColor = new THREE.Color(cssColor("--border", "#e8e6ef"));
        var color = new THREE.Color();

        days.forEach(function (d, i) {
            var idx = first + i;
            var col = idx % 7;
            var row = Math.floor(idx / 7);
            var h = d.s === null || d.s === undefined ? 0.06 : d.s * 0.55;
            pos.set((col - 3) * gap, 0, (row - (rows - 1) / 2) * gap);
            scale.set(1, h, 1);
            matrix.compose(pos, quat, scale);
            mesh.setMatrixAt(i, matrix);
            if (d.s === null || d.s === undefined) {
                color.copy(emptyColor);
            } else {
                color.setHex(PALETTE[Math.max(0, Math.min(4, Math.round(d.s) - 1))]);
            }
            mesh.setColorAt(i, color);
        });
        mesh.instanceMatrix.needsUpdate = true;
        if (mesh.instanceColor) mesh.instanceColor.needsUpdate = true;

        var floor = new THREE.Mesh(
            new THREE.PlaneGeometry(width + 1, depth + 1),
            new THREE.MeshStandardMaterial({ color: new THREE.Color(cssColor("--surface-2", "#faf9fd")), roughness: 1 })
        );
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -0.01;

        var world = new THREE.Group();
        world.rotation.y = -0.55;
        world.add(floor);
        world.add(mesh);
        scene.add(world);

        var dist = Math.max(11, Math.max(width, depth) * 1.2);
        camera.position.set(0, dist * 0.75, dist);
        camera.lookAt(0, 0.8, 0);

        /* Interaksi: seret untuk memutar, sorot untuk membaca */
        var dragging = false;
        var lastX = 0;
        var lastY = 0;
        var interacted = false;
        var raycaster = new THREE.Raycaster();
        var ndc = new THREE.Vector2();
        var dom = renderer.domElement;
        dom.style.touchAction = "pan-y";
        dom.style.cursor = "grab";

        function onDown(e) {
            dragging = true;
            interacted = true;
            lastX = e.clientX;
            lastY = e.clientY;
            dom.style.cursor = "grabbing";
            if (dom.setPointerCapture) dom.setPointerCapture(e.pointerId);
        }

        function onMove(e) {
            if (dragging) {
                world.rotation.y += (e.clientX - lastX) * 0.01;
                world.rotation.x = Math.max(-0.25, Math.min(0.5, world.rotation.x + (e.clientY - lastY) * 0.004));
                lastX = e.clientX;
                lastY = e.clientY;
                if (reduceMotion) runner.once();
                return;
            }
            if (!caption) return;
            var r = dom.getBoundingClientRect();
            ndc.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
            raycaster.setFromCamera(ndc, camera);
            var hit = raycaster.intersectObject(mesh)[0];
            if (hit && hit.instanceId !== undefined && days[hit.instanceId]) {
                caption.textContent = days[hit.instanceId].t;
            }
        }

        function onUp(e) {
            dragging = false;
            dom.style.cursor = "grab";
            if (dom.releasePointerCapture && e.pointerId !== undefined) {
                try { dom.releasePointerCapture(e.pointerId); } catch (err) { /* abaikan */ }
            }
        }

        dom.addEventListener("pointerdown", onDown);
        dom.addEventListener("pointermove", onMove);
        dom.addEventListener("pointerup", onUp);
        dom.addEventListener("pointercancel", onUp);

        var runner = createRunner(el, function (dt) {
            if (!interacted) world.rotation.y += dt * 0.12;
            renderer.render(scene, camera);
        });

        function resize() {
            var w = el.clientWidth, h = el.clientHeight;
            if (!w || !h) return;
            renderer.setSize(w, h);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            if (reduceMotion) runner.once();
        }
        var ro = "ResizeObserver" in window ? new ResizeObserver(resize) : null;
        if (ro) ro.observe(el);

        runner.once();
        runner.start();

        return {
            destroy: function () {
                runner.destroy();
                if (ro) ro.disconnect();
                dom.removeEventListener("pointerdown", onDown);
                dom.removeEventListener("pointermove", onMove);
                dom.removeEventListener("pointerup", onUp);
                dom.removeEventListener("pointercancel", onUp);
                geo.dispose();
                mesh.material.dispose();
                renderer.dispose();
                if (dom.parentNode) dom.parentNode.removeChild(dom);
            }
        };
    }

    window.MJScenes = { hero: hero, landscape: landscape };
})();
