document.addEventListener("DOMContentLoaded", () => {
    /* Menu mobile */
    const menuButton = document.querySelector(".menu-toggle");
    const navLinks = document.querySelector(".nav-links");
    if (menuButton && navLinks) {
        menuButton.addEventListener("click", () => {
            const open = navLinks.classList.toggle("show");
            menuButton.setAttribute("aria-expanded", open ? "true" : "false");
        });
    }

    /* Tema terang/gelap */
    const themeButton = document.querySelector(".theme-toggle");
    if (themeButton) {
        themeButton.addEventListener("click", () => {
            const next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
            document.documentElement.setAttribute("data-theme", next);
            try { localStorage.setItem("mj-theme", next); } catch (e) {}
        });
    }

    /* Pesan sukses hilang sendiri */
    document.querySelectorAll(".auto-hide").forEach((el) => {
        setTimeout(() => {
            el.style.opacity = "0";
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });

    /* Konfirmasi sebelum aksi berbahaya */
    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (e) => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    /* Penghitung karakter */
    document.querySelectorAll("textarea[data-counter]").forEach((area) => {
        const out = document.getElementById(area.dataset.counter);
        const update = () => { if (out) out.textContent = area.value.length; };
        area.addEventListener("input", update);
        update();
    });

    /* Label intensitas */
    const range = document.getElementById("intensity");
    const rangeOut = document.getElementById("intensity-out");
    if (range && rangeOut) {
        let labels = [];
        try { labels = JSON.parse(range.dataset.labels || "[]"); } catch (e) {}
        range.addEventListener("input", () => {
            rangeOut.textContent = labels[range.value - 1] || range.value;
        });
    }

    /* Tombol salin */
    document.querySelectorAll("[data-copy]").forEach((btn) => {
        btn.addEventListener("click", async () => {
            const input = document.querySelector(btn.dataset.copy);
            if (!input) return;
            try {
                await navigator.clipboard.writeText(input.value);
            } catch (e) {
                input.select();
                document.execCommand("copy");
            }
            const old = btn.textContent;
            btn.textContent = "Tersalin";
            setTimeout(() => (btn.textContent = old), 1500);
        });
    });

    /* Cetak */
    document.querySelectorAll("[data-print]").forEach((btn) => {
        btn.addEventListener("click", () => window.print());
    });

    /* Badge chat belum dibaca */
    const badges = document.querySelectorAll("[data-unread-badge]");
    const setBadges = (n) => {
        badges.forEach((b) => {
            b.textContent = n;
            b.hidden = !(n > 0);
        });
    };

    /* Chat: kirim dengan Enter, polling pesan baru */
    const box = document.getElementById("chat-messages");
    const input = document.getElementById("chat-input");

    if (input) {
        const grow = () => {
            input.style.height = "auto";
            input.style.height = Math.min(input.scrollHeight, 140) + "px";
        };
        input.addEventListener("input", grow);
        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter" && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                if (input.value.trim() !== "") input.form.requestSubmit();
            }
        });
    }

    if (box) {
        let lastId = parseInt(box.dataset.lastId || "0", 10);
        const pollBase = box.dataset.poll;
        box.scrollTop = box.scrollHeight;

        const poll = async () => {
            if (document.hidden) return;
            try {
                const sep = pollBase.includes("?") ? "&" : "?";
                const res = await fetch(`${pollBase}${sep}after=${lastId}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
                if (!res.ok) return;
                const data = await res.json();
                if (data.messages && data.messages.length) {
                    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
                    const empty = box.querySelector("[data-chat-empty]");
                    if (empty) empty.remove();
                    data.messages.forEach((html) => box.insertAdjacentHTML("beforeend", html));
                    lastId = data.last_id;
                    if (nearBottom) box.scrollTop = box.scrollHeight;
                }
                setBadges(data.unread || 0);
            } catch (e) { /* abaikan, coba lagi nanti */ }
        };
        setInterval(poll, 5000);
    } else if (badges.length) {
        const refresh = async () => {
            if (document.hidden) return;
            try {
                const res = await fetch("/chat/unread.php", { credentials: "same-origin" });
                if (res.ok) setBadges(parseInt(await res.text(), 10) || 0);
            } catch (e) {}
        };
        setInterval(refresh, 30000);
    }
});

/* ==========================================================
   Efek 3D
   ========================================================== */
document.addEventListener("DOMContentLoaded", () => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

    /* 1. Kartu miring mengikuti kursor (hanya perangkat dengan mouse) */
    if (!reduceMotion && finePointer) {
        document.querySelectorAll("[data-tilt]").forEach((card) => {
            const max = parseFloat(card.dataset.tilt) || 6;
            let frame = 0;

            card.addEventListener("pointermove", (e) => {
                const r = card.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width - 0.5;
                const py = (e.clientY - r.top) / r.height - 0.5;
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => {
                    card.classList.add("tilting");
                    card.style.transform =
                        `perspective(900px) rotateX(${(-py * max).toFixed(2)}deg) rotateY(${(px * max).toFixed(2)}deg) translateZ(0)`;
                });
            });

            card.addEventListener("pointerleave", () => {
                cancelAnimationFrame(frame);
                card.classList.remove("tilting");
                card.style.transform = "";
            });
        });
    }

    /* 2. Emoji berputar saat mood dipilih (dipicu pengguna) */
    document.querySelectorAll(".mood-option input").forEach((radio) => {
        radio.addEventListener("change", () => {
            if (reduceMotion) return;
            const option = radio.closest(".mood-option");
            if (!option) return;
            option.classList.remove("pop");
            void option.offsetWidth; // mulai ulang animasi
            option.classList.add("pop");
            option.addEventListener("animationend", () => option.classList.remove("pop"), { once: true });
        });
    });

    /* 3. Three.js dimuat hanya jika perlu dan perangkat mampu */
    const THREE_URL = "https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js";

    const canRun3D = () => {
        try {
            const conn = navigator.connection || {};
            if (conn.saveData) return false;
            if ((navigator.hardwareConcurrency || 4) <= 2) return false;
            if (navigator.deviceMemory && navigator.deviceMemory <= 2) return false;
            const c = document.createElement("canvas");
            return !!(window.WebGLRenderingContext && (c.getContext("webgl") || c.getContext("experimental-webgl")));
        } catch (e) {
            return false;
        }
    };

    const loadScript = (src) =>
        new Promise((resolve, reject) => {
            const s = document.createElement("script");
            s.src = src;
            s.async = true;
            s.onload = resolve;
            s.onerror = () => reject(new Error("gagal memuat " + src));
            document.head.appendChild(s);
        });

    let scenesPromise = null;
    const ensureScenes = () => {
        if (!scenesPromise) {
            scenesPromise = (window.THREE ? Promise.resolve() : loadScript(THREE_URL))
                .then(() => (window.MJScenes ? null : loadScript("/assets/js/scenes.js")))
                .then(() => window.MJScenes);
        }
        return scenesPromise;
    };

    /* 3a. Planet mood di halaman depan */
    const stage = document.getElementById("hero-3d");
    if (stage && canRun3D()) {
        const start = () => {
            ensureScenes()
                .then((scenes) => {
                    scenes.hero(stage);
                    stage.classList.add("is-live");
                })
                .catch(() => { /* tetap tampilkan emoji cadangan */ });
        };
        // tunggu browser tidak sibuk agar tidak mengganggu muat halaman
        if ("requestIdleCallback" in window) requestIdleCallback(start, { timeout: 2500 });
        else setTimeout(start, 600);
    }

    /* 3b. Lanskap mood di halaman statistik (dimuat saat tombol diklik) */
    const toggle = document.querySelector("[data-3d-toggle]");
    const panel = document.getElementById("landscape-panel");
    if (toggle && panel) {
        const host = document.getElementById("landscape-3d");
        const caption = document.getElementById("landscape-caption");
        let instance = null;

        if (!canRun3D()) {
            toggle.disabled = true;
            toggle.textContent = "3D tidak tersedia di perangkat ini";
        }

        toggle.addEventListener("click", async () => {
            if (instance) {
                instance.destroy();
                instance = null;
                panel.hidden = true;
                toggle.textContent = "Tampilkan 3D";
                toggle.setAttribute("aria-expanded", "false");
                return;
            }

            toggle.disabled = true;
            toggle.textContent = "Memuat…";
            try {
                const scenes = await ensureScenes();
                const days = JSON.parse(panel.dataset.days || "[]");
                panel.hidden = false;
                instance = scenes.landscape(host, days, caption);
                toggle.textContent = "Sembunyikan 3D";
                toggle.setAttribute("aria-expanded", "true");
            } catch (e) {
                panel.hidden = true;
                toggle.textContent = "Gagal memuat 3D. Coba lagi";
            } finally {
                toggle.disabled = false;
            }
        });
    }
});

/* ==========================================================
   Pertanyaan refleksi, PWA, pengingat, kartu pencapaian
   ========================================================== */
document.addEventListener("DOMContentLoaded", () => {
    /* 1. Pertanyaan refleksi di form diary */
    const box = document.getElementById("prompt-box");
    if (box) {
        let prompts = {};
        try { prompts = JSON.parse(box.dataset.prompts || "{}"); } catch (e) {}
        const seed = parseInt(box.dataset.seed || "0", 10);
        const textEl = document.getElementById("prompt-text");
        const noteEl = document.getElementById("note");
        let offset = 0;

        const group = () => {
            const checked = document.querySelector('.mood-option input[name="mood"]:checked');
            const score = checked ? parseInt(checked.dataset.score || "3", 10) : 3;
            return score <= 2 ? "low" : score === 3 ? "mid" : "high";
        };

        const current = () => {
            const list = prompts[group()] || [];
            return list.length ? list[(seed + offset) % list.length] : "";
        };

        const render = () => { if (textEl) textEl.textContent = current(); };

        document.querySelectorAll('.mood-option input[name="mood"]').forEach((r) =>
            r.addEventListener("change", () => { offset = 0; render(); })
        );

        const next = document.getElementById("prompt-next");
        if (next) next.addEventListener("click", () => { offset += 1; render(); });

        const use = document.getElementById("prompt-use");
        if (use && noteEl) {
            use.addEventListener("click", () => {
                const text = current();
                if (!text) return;
                const existing = noteEl.value.trim();
                noteEl.value = existing ? existing + "\n\n" + text + "\n" : text + "\n";
                noteEl.dispatchEvent(new Event("input", { bubbles: true }));
                noteEl.focus();
                noteEl.setSelectionRange(noteEl.value.length, noteEl.value.length);
            });
        }

        render();
    }

    /* 2. Service worker (hanya lewat HTTPS) */
    if ("serviceWorker" in navigator && (location.protocol === "https:" || location.hostname === "localhost")) {
        navigator.serviceWorker.register("/sw.js").catch(() => { /* abaikan */ });
    }

    /* 3. Tombol "Pasang aplikasi" */
    const installBtn = document.getElementById("install-btn");
    let deferredInstall = null;
    window.addEventListener("beforeinstallprompt", (e) => {
        e.preventDefault();
        deferredInstall = e;
        if (installBtn) installBtn.hidden = false;
    });
    if (installBtn) {
        installBtn.addEventListener("click", async () => {
            if (!deferredInstall) return;
            deferredInstall.prompt();
            await deferredInstall.userChoice.catch(() => {});
            deferredInstall = null;
            installBtn.hidden = true;
        });
    }
    window.addEventListener("appinstalled", () => { if (installBtn) installBtn.hidden = true; });

    /* 4. Pengingat di browser (aktif saat aplikasi terbuka) */
    const KEY_TIME = "mj-remind-time";
    const KEY_LAST = "mj-remind-last";
    const todayStr = () => {
        const d = new Date();
        return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    };
    const getTime = () => { try { return localStorage.getItem(KEY_TIME); } catch (e) { return null; } };

    const notify = async () => {
        const title = "Sudah sempat mencatat mood hari ini?";
        const options = {
            body: "Cukup satu emoji dan satu kalimat.",
            icon: "/assets/icons/icon-192.png",
            badge: "/assets/icons/icon-192.png",
            tag: "mj-daily-reminder",
        };
        try {
            const reg = "serviceWorker" in navigator ? await navigator.serviceWorker.getRegistration() : null;
            if (reg) await reg.showNotification(title, options);
            else new Notification(title, options);
        } catch (e) { /* abaikan */ }
    };

    const checkReminder = () => {
        const time = getTime();
        if (!time || !("Notification" in window) || Notification.permission !== "granted") return;
        if (document.body.dataset.loggedToday !== "0") return; // sudah mencatat, atau bukan akun pengguna
        const now = new Date();
        const [h, m] = time.split(":").map(Number);
        const due = now.getHours() > h || (now.getHours() === h && now.getMinutes() >= m);
        let last = null;
        try { last = localStorage.getItem(KEY_LAST); } catch (e) {}
        if (due && last !== todayStr()) {
            try { localStorage.setItem(KEY_LAST, todayStr()); } catch (e) {}
            notify();
        }
    };

    if (document.body.dataset.loggedToday !== undefined) {
        checkReminder();
        setInterval(checkReminder, 60000);
    }

    /* Pengaturan di halaman Profil */
    const remindBox = document.getElementById("browser-reminder");
    if (remindBox) {
        const timeInput = document.getElementById("remind-time");
        const onBtn = document.getElementById("remind-on");
        const offBtn = document.getElementById("remind-off");
        const status = document.getElementById("remind-status");

        const refresh = () => {
            const saved = getTime();
            if (!("Notification" in window)) {
                status.textContent = "Browser ini tidak mendukung notifikasi.";
                onBtn.disabled = true;
                return;
            }
            if (Notification.permission === "denied") {
                status.textContent = "Notifikasi diblokir di browser. Ubah izinnya di pengaturan situs, lalu coba lagi.";
                onBtn.disabled = true;
                return;
            }
            const active = !!saved && Notification.permission === "granted";
            onBtn.hidden = active;
            offBtn.hidden = !active;
            timeInput.disabled = active;
            if (saved) timeInput.value = saved;
            status.textContent = active ? "Aktif: pengingat pukul " + saved + "." : "";
        };

        onBtn.addEventListener("click", async () => {
            if (!timeInput.value) return;
            const perm = await Notification.requestPermission();
            if (perm === "granted") {
                try {
                    localStorage.setItem(KEY_TIME, timeInput.value);
                    localStorage.removeItem(KEY_LAST);
                } catch (e) {}
            }
            refresh();
        });

        offBtn.addEventListener("click", () => {
            try { localStorage.removeItem(KEY_TIME); } catch (e) {}
            timeInput.disabled = false;
            refresh();
        });

        refresh();
    }

    /* 5. Kartu pencapaian: ketuk untuk membalik (perangkat sentuh) */
    document.querySelectorAll(".badge-card").forEach((card) => {
        card.addEventListener("click", () => card.classList.toggle("flipped"));
    });
});

/* ==========================================================
   Asisten FAQ dan unggah foto
   ========================================================== */
document.addEventListener("DOMContentLoaded", () => {
    /* 1. Widget asisten */
    const bot = document.getElementById("bot");
    if (bot) {
        const endpoint = bot.dataset.endpoint;
        const csrf = bot.dataset.csrf;
        const list = document.getElementById("bot-messages");
        const form = document.getElementById("bot-form");
        const input = document.getElementById("bot-input");
        let busy = false;

        const scroll = () => { list.scrollTop = list.scrollHeight; };

        const addMsg = (text, who) => {
            const div = document.createElement("div");
            div.className = "bot-msg " + who;
            div.textContent = text;
            list.appendChild(div);
            scroll();
            return div;
        };

        const addLinks = (links, crisis) => {
            if (!links || !links.length) return;
            const wrap = document.createElement("div");
            wrap.className = "bot-links";
            links.forEach(([label, href]) => {
                if (typeof href !== "string" || !(href.startsWith("/") || href.startsWith("tel:"))) return;
                const a = document.createElement("a");
                a.className = "btn btn-small" + (crisis ? "" : " btn-outline");
                a.href = href;
                a.textContent = label;
                wrap.appendChild(a);
            });
            list.appendChild(wrap);
            scroll();
        };

        const ask = async (q) => {
            if (busy || !q) return;
            busy = true;
            addMsg(q, "user");
            const typing = addMsg("…", "bot typing");
            try {
                const fd = new FormData();
                fd.append("_csrf", csrf);
                fd.append("q", q);
                const res = await fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin" });
                const data = await res.json();
                typing.remove();
                const msg = addMsg(data.reply || "Maaf, ada gangguan. Coba lagi.", "bot" + (data.crisis ? " crisis" : ""));
                if (data.crisis) msg.setAttribute("role", "alert");
                if (data.ai) {
                    const note = document.createElement("small");
                    note.className = "bot-ai-note";
                    note.textContent = "Dijawab AI, bisa keliru.";
                    msg.appendChild(note);
                }
                addLinks(data.links, !!data.crisis);
            } catch (e) {
                typing.remove();
                addMsg("Maaf, asisten sedang tidak bisa dihubungi. Kamu bisa mengirim pesan lewat chat dengan tim.", "bot");
                addLinks([["Chat dengan tim", "/chat.php"]], false);
            } finally {
                busy = false;
            }
        };

        form.addEventListener("submit", (e) => {
            e.preventDefault();
            const q = input.value.trim();
            input.value = "";
            ask(q);
        });

        document.querySelectorAll("[data-bot-q]").forEach((btn) =>
            btn.addEventListener("click", () => ask(btn.dataset.botQ))
        );
    }

    /* 2. Foto: dikecilkan di browser (metadata lokasi/EXIF otomatis hilang) */
    const loadBitmap = async (file) => {
        if (window.createImageBitmap) {
            try { return await createImageBitmap(file, { imageOrientation: "from-image" }); } catch (e) { /* lanjut ke Image */ }
        }
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error("decode")); };
            img.src = url;
        });
    };

    const toBlob = (canvas, quality) => new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", quality));

    /**
     * Kecilkan gambar jadi JPEG di bawah maxBytes.
     * opts: maxSide (px), maxBytes, square (potong tengah jadi persegi)
     */
    const processImage = async (file, opts) => {
        const bmp = await loadBitmap(file);
        const w = bmp.width || bmp.naturalWidth;
        const h = bmp.height || bmp.naturalHeight;

        const draw = (side) => {
            const canvas = document.createElement("canvas");
            const ctx2d = canvas.getContext("2d");
            if (opts.square) {
                const crop = Math.min(w, h);
                const out = Math.min(side, crop);
                canvas.width = canvas.height = out;
                ctx2d.fillStyle = "#ffffff";
                ctx2d.fillRect(0, 0, out, out);
                ctx2d.drawImage(bmp, (w - crop) / 2, (h - crop) / 2, crop, crop, 0, 0, out, out);
            } else {
                const ratio = Math.min(1, side / Math.max(w, h));
                canvas.width = Math.max(1, Math.round(w * ratio));
                canvas.height = Math.max(1, Math.round(h * ratio));
                ctx2d.fillStyle = "#ffffff"; // latar putih untuk PNG transparan
                ctx2d.fillRect(0, 0, canvas.width, canvas.height);
                ctx2d.drawImage(bmp, 0, 0, canvas.width, canvas.height);
            }
            return canvas;
        };

        let blob = null;
        for (const [side, quality] of [[opts.maxSide, 0.82], [opts.maxSide, 0.65], [opts.maxSide, 0.5], [Math.round(opts.maxSide * 0.75), 0.6]]) {
            blob = await toBlob(draw(side), quality);
            if (blob && blob.size <= opts.maxBytes) break;
        }
        if (bmp.close) bmp.close();
        if (!blob || blob.size > opts.maxBytes) throw new Error("terlalu besar");
        return blob;
    };

    const setFiles = (input, files) => {
        const dt = new DataTransfer();
        files.forEach((f) => dt.items.add(f));
        input.files = dt.files;
    };

    /* 2a. Foto catatan: beberapa foto sekaligus */
    const photoInput = document.getElementById("photo-input");
    if (photoInput) {
        const preview = document.getElementById("photo-preview");
        const status = document.getElementById("photo-status");
        const MAX = parseInt(photoInput.dataset.max || "4", 10);
        const existing = parseInt(photoInput.dataset.existing || "0", 10);
        const MAX_PHOTO_BYTES = 900000;      // per foto
        const MAX_TOTAL_BYTES = 3800000;     // batas permintaan Vercel ~4,5 MB
        let items = [];                      // {file, url}

        const slots = () => {
            const removed = document.querySelectorAll('input[name="remove_photos[]"]:checked').length;
            return Math.max(0, MAX - existing + removed);
        };

        const render = () => {
            preview.replaceChildren();
            items.forEach((it, i) => {
                const fig = document.createElement("figure");
                fig.className = "photo-thumb";
                const img = document.createElement("img");
                img.src = it.url;
                img.alt = "Pratinjau foto " + (i + 1);
                const btn = document.createElement("button");
                btn.type = "button";
                btn.className = "btn btn-outline btn-small";
                btn.textContent = "Batalkan";
                btn.addEventListener("click", () => {
                    URL.revokeObjectURL(it.url);
                    items = items.filter((x) => x !== it);
                    setFiles(photoInput, items.map((x) => x.file));
                    render();
                    status.textContent = items.length ? items.length + " foto siap diunggah." : "";
                });
                fig.append(img, btn);
                preview.appendChild(fig);
            });
            preview.hidden = items.length === 0;
        };

        photoInput.addEventListener("change", async () => {
            const picked = Array.from(photoInput.files || []);
            setFiles(photoInput, items.map((x) => x.file)); // pertahankan yang sudah diproses
            if (!picked.length) return;

            const room = slots() - items.length;
            if (room <= 0) {
                status.textContent = "Sudah mencapai maksimal " + MAX + " foto per catatan.";
                return;
            }

            const notes = [];
            if (picked.length > room) notes.push("Hanya " + room + " foto lagi yang muat (maksimal " + MAX + " per catatan).");
            status.textContent = "Memproses foto…";

            let failed = 0;
            for (const file of picked.slice(0, room)) {
                if (!file.type.startsWith("image/")) { failed++; continue; }
                try {
                    const blob = await processImage(file, { maxSide: 1280, maxBytes: MAX_PHOTO_BYTES });
                    const total = items.reduce((n, x) => n + x.file.size, 0) + blob.size;
                    if (total > MAX_TOTAL_BYTES) { notes.push("Total ukuran foto terlalu besar, sebagian tidak ditambahkan."); break; }
                    const out = new File([blob], "foto" + (items.length + 1) + ".jpg", { type: "image/jpeg" });
                    items.push({ file: out, url: URL.createObjectURL(blob) });
                } catch (e) {
                    failed++;
                }
            }
            if (failed) notes.push(failed + " foto tidak bisa diproses (coba JPG atau PNG yang lebih kecil).");

            setFiles(photoInput, items.map((x) => x.file));
            render();
            const kb = Math.max(1, Math.round(items.reduce((n, x) => n + x.file.size, 0) / 1024));
            status.textContent = [items.length ? items.length + " foto siap diunggah (" + kb + " KB)." : "", ...notes].filter(Boolean).join(" ");
        });
    }

    /* 2b. Foto profil: dipotong persegi */
    const avatarInput = document.getElementById("avatar-input");
    if (avatarInput) {
        const status = document.getElementById("avatar-status");
        const saveBtn = document.getElementById("avatar-save");
        const wrap = document.getElementById("avatar-preview-wrap");
        let url = null;

        avatarInput.addEventListener("change", async () => {
            const file = avatarInput.files && avatarInput.files[0];
            saveBtn.disabled = true;
            if (!file) { status.textContent = ""; return; }
            if (!file.type.startsWith("image/")) {
                status.textContent = "File harus berupa foto.";
                avatarInput.value = "";
                return;
            }
            status.textContent = "Memproses foto…";
            try {
                const blob = await processImage(file, { maxSide: 320, maxBytes: 250000, square: true });
                setFiles(avatarInput, [new File([blob], "profil.jpg", { type: "image/jpeg" })]);
                if (url) URL.revokeObjectURL(url);
                url = URL.createObjectURL(blob);
                const img = document.createElement("img");
                img.className = "avatar avatar-lg";
                img.style.setProperty("--avatar-size", "96px");
                img.src = url;
                img.alt = "Pratinjau foto profil";
                wrap.replaceChildren(img);
                status.textContent = "Siap disimpan.";
                saveBtn.disabled = false;
            } catch (e) {
                status.textContent = "Foto tidak bisa diproses. Coba format JPG atau PNG yang lain.";
                avatarInput.value = "";
            }
        });
    }

    /* 3. Animasi naik tahap (kepompong menetas) */
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let suRaf = 0;
    let suTimer = 0;
    let suReturnFocus = null;

    const stageUpBurst = (el) => {
        const canvas = el.querySelector(".stageup-canvas");
        if (!canvas || reduceMotion) return;
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        const w = window.innerWidth, h = window.innerHeight;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        const c = canvas.getContext("2d");
        c.setTransform(dpr, 0, 0, dpr, 0, 0);

        const art = el.querySelector(".stageup-art").getBoundingClientRect();
        const cx = art.left + art.width / 2, cy = art.top + art.height / 2;
        const colors = ["#ffc2d9", "#bfe3ff", "#c4ecd9", "#fff0b8", "#dcd2ff", "#ffd6c2"];
        const parts = Array.from({ length: 130 }, () => {
            const a = Math.random() * Math.PI * 2;
            const sp = 120 + Math.random() * 360;
            return {
                x: cx, y: cy, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - 140,
                r: Math.random() * 3, vr: (Math.random() - 0.5) * 9, size: 5 + Math.random() * 9,
                color: colors[Math.floor(Math.random() * colors.length)],
                star: Math.random() < 0.28, life: 2.2 + Math.random() * 1.8, t: 0
            };
        });

        let last = performance.now();
        const frame = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            c.clearRect(0, 0, w, h);
            let alive = false;
            for (const p of parts) {
                p.t += dt;
                if (p.t >= p.life) continue;
                alive = true;
                const drag = 1 - 1.7 * dt;
                p.vx *= drag;
                p.vy = p.vy * drag + 170 * dt;
                p.x += p.vx * dt;
                p.y += p.vy * dt;
                p.r += p.vr * dt;
                c.save();
                c.globalAlpha = 1 - Math.pow(p.t / p.life, 2);
                c.translate(p.x, p.y);
                c.rotate(p.r);
                c.fillStyle = p.color;
                c.beginPath();
                if (p.star) {
                    for (let i = 0; i < 8; i++) {
                        const rad = i % 2 ? p.size * 0.28 : p.size * 0.8;
                        const ang = (i / 8) * Math.PI * 2;
                        c.lineTo(Math.cos(ang) * rad, Math.sin(ang) * rad);
                    }
                    c.closePath();
                } else {
                    c.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2);
                }
                c.fill();
                c.restore();
            }
            if (alive) suRaf = requestAnimationFrame(frame);
            else c.clearRect(0, 0, w, h);
        };
        suRaf = requestAnimationFrame(frame);
    };

    const closeStageUp = (el) => {
        clearTimeout(suTimer);
        cancelAnimationFrame(suRaf);
        el.hidden = true;
        el.classList.remove("is-playing");
        document.body.classList.remove("has-stageup");
        if (el.dataset.autoshow === "1") el.remove();
        if (suReturnFocus && suReturnFocus.focus) suReturnFocus.focus();
        suReturnFocus = null;
    };

    const openStageUp = (el) => {
        suReturnFocus = document.activeElement;
        el.hidden = false;
        el.classList.remove("is-playing");
        void el.offsetWidth; // mulai ulang animasi CSS
        el.classList.add("is-playing");
        document.body.classList.add("has-stageup");
        const btn = el.querySelector("[data-stageup-close]");
        if (btn) btn.focus({ preventScroll: true });
        clearTimeout(suTimer);
        suTimer = setTimeout(() => stageUpBurst(el), reduceMotion ? 0 : 1050);

        if (!el.dataset.bound) {
            el.dataset.bound = "1";
            el.addEventListener("click", (e) => {
                if (e.target === el || e.target.closest("[data-stageup-close]")) closeStageUp(el);
            });
            el.addEventListener("keydown", (e) => {
                if (e.key === "Escape") closeStageUp(el);
            });
        }
    };

    document.querySelectorAll("[data-stageup-open]").forEach((btn) =>
        btn.addEventListener("click", () => {
            const el = document.getElementById(btn.dataset.stageupOpen);
            if (el) openStageUp(el);
        })
    );
    const autoStage = document.querySelector('.stageup[data-autoshow="1"]');
    if (autoStage) openStageUp(autoStage);
});
