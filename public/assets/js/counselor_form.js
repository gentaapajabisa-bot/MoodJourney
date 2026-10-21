/* Formulir konselor: bertahap seperti Google Form. Tanpa JS, semua bagian tampil sekaligus. */
(function () {
    var form = document.getElementById("cform");
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll(".cform-step"));
    var prev = document.getElementById("cform-prev");
    var next = document.getElementById("cform-next");
    var submit = document.getElementById("cform-submit");
    var bar = document.getElementById("cform-bar");
    var label = document.getElementById("cform-step-label");
    var msg = document.getElementById("cf-message");
    var count = document.getElementById("cf-count");
    var current = 0;

    form.classList.add("is-stepped");
    prev.hidden = false;
    next.hidden = false;

    function checked(name) {
        return !!form.querySelector('input[name="' + name + '"]:checked');
    }

    function clearErrors(step) {
        Array.prototype.forEach.call(step.querySelectorAll(".cform-error.is-js"), function (e) { e.remove(); });
    }

    function showError(el, text) {
        var p = document.createElement("p");
        p.className = "cform-error is-js";
        p.setAttribute("role", "alert");
        p.textContent = text;
        el.appendChild(p);
    }

    function validate(i) {
        var step = steps[i];
        clearErrors(step);
        var ok = true;
        var first = null;
        function fail(target, text) {
            ok = false;
            showError(target, text);
            if (!first) first = target;
        }
        if (i === 0) {
            if (!checked("topic")) fail(step.querySelectorAll("fieldset")[0], "Pilih salah satu topik.");
            if (!checked("first_time")) fail(step.querySelectorAll("fieldset")[1], "Pilih salah satu.");
        } else if (i === 1) {
            if (!checked("feeling")) fail(step.querySelectorAll("fieldset")[0], "Pilih seberapa berat yang kamu rasakan.");
            var d = form.querySelector("#cf-duration");
            if (!d.value) fail(d.parentNode, "Pilih sudah berapa lama.");
            if (msg.value.trim().length < 10) fail(msg.parentNode, "Ceritakan singkat (minimal 10 karakter).");
        } else if (i === 2) {
            if (!checked("time")) fail(step.querySelectorAll("fieldset")[0], "Pilih waktu yang nyaman.");
            var ph = form.querySelector("#cf-phone");
            var clean = ph.value.replace(/[\s().\-]/g, "");
            if (!/^\+?\d{9,15}$/.test(clean) || !/^(0|62|\+62)/.test(clean)) {
                fail(ph.parentNode, "Masukkan nomor telepon/WhatsApp yang valid, mis. 081234567890.");
            }
        } else if (i === 3) {
            var c = form.querySelector('input[name="consent"]');
            if (!c.checked) fail(c.closest(".form-group"), "Centang persetujuan ini untuk melanjutkan.");
        }
        if (first) first.scrollIntoView({ behavior: "smooth", block: "center" });
        return ok;
    }

    function show(i) {
        current = i;
        steps.forEach(function (s, idx) { s.hidden = idx !== i; });
        prev.hidden = i === 0;
        next.hidden = i === steps.length - 1;
        submit.hidden = i !== steps.length - 1;
        bar.style.width = ((i + 1) / steps.length * 100) + "%";
        label.textContent = "Bagian " + (i + 1) + " dari " + steps.length;
        var h2 = steps[i].querySelector("h2");
        if (h2) { h2.setAttribute("tabindex", "-1"); h2.focus({ preventScroll: true }); }
        form.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    /* sembunyikan konselor pilihan yang tidak menangani topik terpilih */
    function filterCounselors() {
        var t = form.querySelector('input[name="topic"]:checked');
        var topic = t ? t.value : "";
        Array.prototype.forEach.call(form.querySelectorAll("#cf-pref [data-topics]"), function (el) {
            var list = el.getAttribute("data-topics");
            var match = !topic || list === "" || list.split(",").indexOf(topic) !== -1;
            el.hidden = !match;
            if (!match) {
                var r = el.querySelector("input");
                if (r.checked) form.querySelector('#cf-pref input[value="0"]').checked = true;
            }
        });
    }

    next.addEventListener("click", function () {
        if (validate(current)) show(Math.min(current + 1, steps.length - 1));
    });
    prev.addEventListener("click", function () { show(Math.max(current - 1, 0)); });

    form.addEventListener("change", function (e) {
        if (e.target.name === "topic") filterCounselors();
    });
    form.addEventListener("submit", function (e) {
        for (var i = 0; i < steps.length; i++) {
            if (!validate(i)) { e.preventDefault(); show(i); return; }
        }
        submit.disabled = true;
    });
    form.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && e.target.tagName !== "TEXTAREA" && current < steps.length - 1) {
            e.preventDefault();
            next.click();
        }
    });

    if (msg && count) {
        var upd = function () { count.textContent = msg.value.length; };
        msg.addEventListener("input", upd);
        upd();
    }

    filterCounselors();
    /* kalau server mengembalikan galat, mulai dari bagian pertama yang bermasalah */
    var firstErr = form.querySelector(".cform-error");
    show(firstErr ? steps.indexOf(firstErr.closest(".cform-step")) : 0);
})();
