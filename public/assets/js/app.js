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
    const photoInput = document.getElementById("photo-input");
    if (photoInput) {
        const preview = document.getElementById("photo-preview");
        const previewImg = document.getElementById("photo-preview-img");
        const clearBtn = document.getElementById("photo-clear");
        const status = document.getElementById("photo-status");
        const MAX_SIDE = 1280;
        let objectUrl = null;

        const reset = () => {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            preview.hidden = true;
        };

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

        photoInput.addEventListener("change", async () => {
            const file = photoInput.files && photoInput.files[0];
            reset();
            if (!file) { status.textContent = ""; return; }

            if (!file.type.startsWith("image/")) {
                status.textContent = "File harus berupa foto.";
                photoInput.value = "";
                return;
            }

            status.textContent = "Memproses foto…";
            try {
                const bmp = await loadBitmap(file);
                const w = bmp.width || bmp.naturalWidth;
                const h = bmp.height || bmp.naturalHeight;
                const ratio = Math.min(1, MAX_SIDE / Math.max(w, h));
                const canvas = document.createElement("canvas");
                canvas.width = Math.max(1, Math.round(w * ratio));
                canvas.height = Math.max(1, Math.round(h * ratio));
                const ctx2d = canvas.getContext("2d");
                ctx2d.fillStyle = "#ffffff"; // latar putih untuk PNG transparan
                ctx2d.fillRect(0, 0, canvas.width, canvas.height);
                ctx2d.drawImage(bmp, 0, 0, canvas.width, canvas.height);
                if (bmp.close) bmp.close();

                let blob = await toBlob(canvas, 0.82);
                if (blob && blob.size > 1400000) blob = await toBlob(canvas, 0.6);
                if (!blob || blob.size > 1450000) throw new Error("terlalu besar");

                const out = new File([blob], "foto.jpg", { type: "image/jpeg" });
                const dt = new DataTransfer();
                dt.items.add(out);
                photoInput.files = dt.files;

                objectUrl = URL.createObjectURL(blob);
                previewImg.src = objectUrl;
                preview.hidden = false;
                status.textContent = "Siap diunggah (" + Math.max(1, Math.round(blob.size / 1024)) + " KB).";
            } catch (e) {
                status.textContent = "Foto tidak bisa diproses. Coba format JPG atau PNG yang lebih kecil.";
                photoInput.value = "";
            }
        });

        clearBtn.addEventListener("click", () => {
            photoInput.value = "";
            reset();
            status.textContent = "";
        });
    }
});
