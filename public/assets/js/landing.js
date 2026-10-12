/**
 * Halaman depan: parallax pastel (mengikuti kursor dan scroll) dan kemunculan bagian saat di-scroll.
 * Dimatikan untuk pengguna yang meminta "kurangi gerakan".
 */
(function () {
    "use strict";

    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---------- muncul saat di-scroll ---------- */
    var items = document.querySelectorAll(".reveal");
    if (reduce || !("IntersectionObserver" in window)) {
        items.forEach(function (el) { el.classList.add("is-in"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add("is-in");
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
        items.forEach(function (el) { io.observe(el); });
    }

    /* ---------- parallax hero ---------- */
    var root = document.querySelector("[data-parallax-root]");
    if (!root || reduce) return;

    var layers = Array.prototype.slice.call(root.querySelectorAll(".sc-l")).map(function (el) {
        return { el: el, depth: parseFloat(el.dataset.depth || "0.3") };
    });

    var tx = 0, ty = 0, cx = 0, cy = 0, scrollY = 0;
    var visible = true, raf = 0;

    root.addEventListener("pointermove", function (e) {
        if (e.pointerType === "touch") return;
        var r = root.getBoundingClientRect();
        tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
        ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
        kick();
    });
    root.addEventListener("pointerleave", function () { tx = 0; ty = 0; kick(); });
    window.addEventListener("scroll", function () { scrollY = window.scrollY; kick(); }, { passive: true });

    if ("IntersectionObserver" in window) {
        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            if (visible) kick();
        }, { threshold: 0 }).observe(root);
    }

    function kick() {
        if (!raf && visible) raf = requestAnimationFrame(frame);
    }

    function frame() {
        raf = 0;
        // gerak halus menuju target (easing)
        cx += (tx - cx) * 0.08;
        cy += (ty - cy) * 0.08;

        layers.forEach(function (l) {
            var x = -cx * 22 * l.depth;
            var y = -cy * 14 * l.depth + scrollY * 0.10 * l.depth;
            l.el.style.transform = "translate3d(" + x.toFixed(1) + "px," + y.toFixed(1) + "px,0)";
        });

        if (Math.abs(tx - cx) > 0.002 || Math.abs(ty - cy) > 0.002) kick();
    }

    frame();
})();
