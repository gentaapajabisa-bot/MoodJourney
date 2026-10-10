/**
 * Ruang tenang: suara ambient dari Web Audio (tanpa file audio) + pemandu napas.
 */
(function () {
    "use strict";

    var root = document.getElementById("calm");
    if (!root) return;

    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var AC = window.AudioContext || window.webkitAudioContext;

    var playBtn = document.getElementById("calm-play");
    var statusEl = document.getElementById("calm-status");
    var timerSel = document.getElementById("calm-timer");
    var masterVol = document.getElementById("vol-master");

    var NAMES = ["rain", "waves", "wind", "pad", "bowl"];
    var state = {};
    NAMES.forEach(function (n) {
        var on = root.querySelector('[data-on="' + n + '"]');
        var vol = root.querySelector('[data-vol="' + n + '"]');
        state[n] = { on: !!(on && on.checked), vol: vol ? vol.value / 100 : 0.5 };
    });

    var PRESETS = {
        rain:  { rain: 0.65, waves: 0,    wind: 0.15, pad: 0,    bowl: 0 },
        beach: { rain: 0,    waves: 0.7,  wind: 0.25, pad: 0.15, bowl: 0 },
        focus: { rain: 0.3,  waves: 0,    wind: 0,    pad: 0.4,  bowl: 0 },
        sleep: { rain: 0.35, waves: 0.25, wind: 0,    pad: 0.3,  bowl: 0.3 }
    };

    /* ---------------------------------------------------------- audio */

    var ctx = null;
    var master = null;
    var reverb = null;
    var active = {};      // nama -> instance suara yang sedang berjalan
    var playing = false;
    var endAt = 0;
    var tickId = 0;
    var fadeStarted = false;

    function makeNoise(type) {
        var sr = ctx.sampleRate;
        var len = sr * 6;
        var xf = Math.floor(sr * 0.25);           // crossfade agar tidak ada "klik" di sambungan loop
        var buf = ctx.createBuffer(2, len, sr);
        for (var ch = 0; ch < 2; ch++) {
            var raw = new Float32Array(len + xf);
            var b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0, last = 0;
            for (var i = 0; i < raw.length; i++) {
                var w = Math.random() * 2 - 1;
                if (type === "pink") {
                    b0 = 0.99886 * b0 + w * 0.0555179;
                    b1 = 0.99332 * b1 + w * 0.0750759;
                    b2 = 0.96900 * b2 + w * 0.1538520;
                    b3 = 0.86650 * b3 + w * 0.3104856;
                    b4 = 0.55000 * b4 + w * 0.5329522;
                    b5 = -0.7616 * b5 - w * 0.0168980;
                    raw[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + w * 0.5362) * 0.11;
                    b6 = w * 0.115926;
                } else if (type === "brown") {
                    last = (last + 0.02 * w) / 1.02;
                    raw[i] = last * 3.5;
                } else {
                    raw[i] = w;
                }
            }
            var out = buf.getChannelData(ch);
            for (var j = 0; j < len; j++) out[j] = raw[j];
            for (var k = 0; k < xf; k++) {
                var t = k / xf;
                out[k] = raw[k] * t + raw[len + k] * (1 - t);
            }
        }
        return buf;
    }

    var noiseCache = {};
    function noiseSource(type) {
        if (!noiseCache[type]) noiseCache[type] = makeNoise(type);
        var src = ctx.createBufferSource();
        src.buffer = noiseCache[type];
        src.loop = true;
        return src;
    }

    function makeReverb() {
        var sr = ctx.sampleRate;
        var len = Math.floor(sr * 3.2);
        var ir = ctx.createBuffer(2, len, sr);
        for (var ch = 0; ch < 2; ch++) {
            var d = ir.getChannelData(ch);
            for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.6);
        }
        var conv = ctx.createConvolver();
        conv.buffer = ir;
        var wet = ctx.createGain();
        wet.gain.value = 0.55;
        conv.connect(wet);
        wet.connect(master);
        return conv;
    }

    /* Setiap pembangun mengembalikan { gain, stop } dan terhubung ke master. */

    function buildRain() {
        var src = noiseSource("pink");
        var hp = ctx.createBiquadFilter(); hp.type = "highpass"; hp.frequency.value = 500;
        var lp = ctx.createBiquadFilter(); lp.type = "lowpass"; lp.frequency.value = 9000;
        var g = ctx.createGain(); g.gain.value = 0;
        src.connect(hp); hp.connect(lp); lp.connect(g); g.connect(master);
        src.start();

        // tetesan acak
        var alive = true;
        function drop() {
            if (!alive) return;
            var s = ctx.createBufferSource();
            s.buffer = noiseCache.white || (noiseCache.white = makeNoise("white"));
            var bp = ctx.createBiquadFilter(); bp.type = "bandpass";
            bp.frequency.value = 1800 + Math.random() * 3200; bp.Q.value = 4;
            var dg = ctx.createGain();
            var t = ctx.currentTime;
            dg.gain.setValueAtTime(0, t);
            dg.gain.linearRampToValueAtTime(0.18 * Math.random(), t + 0.005);
            dg.gain.exponentialRampToValueAtTime(0.0001, t + 0.07);
            s.connect(bp); bp.connect(dg); dg.connect(g);
            s.start(t, Math.random() * 4, 0.1);
            setTimeout(drop, 60 + Math.random() * 220);
        }
        drop();

        return { gain: g, stop: function () { alive = false; try { src.stop(); } catch (e) {} } };
    }

    function buildWaves() {
        var src = noiseSource("brown");
        var lp = ctx.createBiquadFilter(); lp.type = "lowpass"; lp.frequency.value = 700;
        var swell = ctx.createGain(); swell.gain.value = 0.65;
        var g = ctx.createGain(); g.gain.value = 0;
        src.connect(lp); lp.connect(swell); swell.connect(g); g.connect(master);

        var lfo = ctx.createOscillator(); lfo.type = "sine"; lfo.frequency.value = 0.1;
        var depth = ctx.createGain(); depth.gain.value = 0.35;
        lfo.connect(depth); depth.connect(swell.gain);

        var lfo2 = ctx.createOscillator(); lfo2.type = "sine"; lfo2.frequency.value = 0.1;
        var fdepth = ctx.createGain(); fdepth.gain.value = 450;
        lfo2.connect(fdepth); fdepth.connect(lp.frequency);

        src.start(); lfo.start(); lfo2.start();
        return { gain: g, stop: function () { try { src.stop(); lfo.stop(); lfo2.stop(); } catch (e) {} } };
    }

    function buildWind() {
        var src = noiseSource("pink");
        var bp = ctx.createBiquadFilter(); bp.type = "bandpass"; bp.frequency.value = 500; bp.Q.value = 0.8;
        var g = ctx.createGain(); g.gain.value = 0;
        src.connect(bp); bp.connect(g); g.connect(master);

        var lfo = ctx.createOscillator(); lfo.type = "sine"; lfo.frequency.value = 0.06;
        var depth = ctx.createGain(); depth.gain.value = 280;
        lfo.connect(depth); depth.connect(bp.frequency);

        src.start(); lfo.start();
        return { gain: g, stop: function () { try { src.stop(); lfo.stop(); } catch (e) {} } };
    }

    var CHORDS = [
        [130.81, 196.00, 246.94, 329.63],   // Cmaj7
        [110.00, 164.81, 196.00, 261.63],   // Am7
        [87.31, 130.81, 164.81, 220.00],    // Fmaj7
        [98.00, 146.83, 196.00, 246.94]     // G
    ];

    function buildPad() {
        var g = ctx.createGain(); g.gain.value = 0;
        var lp = ctx.createBiquadFilter(); lp.type = "lowpass"; lp.frequency.value = 900;
        lp.connect(g);
        g.connect(master);
        g.connect(reverb);

        var voices = [];
        var tremolo = ctx.createGain(); tremolo.gain.value = 0.8;
        tremolo.connect(lp);
        var lfo = ctx.createOscillator(); lfo.frequency.value = 0.15;
        var lfoDepth = ctx.createGain(); lfoDepth.gain.value = 0.2;
        lfo.connect(lfoDepth); lfoDepth.connect(tremolo.gain);
        lfo.start();

        for (var i = 0; i < 4; i++) {
            [-4, 4].forEach(function (detune) {
                var o = ctx.createOscillator();
                o.type = i % 2 ? "triangle" : "sine";
                o.frequency.value = CHORDS[0][i];
                o.detune.value = detune;
                var vg = ctx.createGain(); vg.gain.value = 0.11;
                o.connect(vg); vg.connect(tremolo);
                o.start();
                voices.push({ o: o, idx: i });
            });
        }

        var step = 0;
        var timer = setInterval(function () {
            step = (step + 1) % CHORDS.length;
            var t = ctx.currentTime;
            voices.forEach(function (v) { v.o.frequency.setTargetAtTime(CHORDS[step][v.idx], t, 2.5); });
        }, 14000);

        return {
            gain: g,
            stop: function () {
                clearInterval(timer);
                try { voices.forEach(function (v) { v.o.stop(); }); lfo.stop(); } catch (e) {}
            }
        };
    }

    function buildBowl() {
        var g = ctx.createGain(); g.gain.value = 0;
        g.connect(master);
        g.connect(reverb);
        var alive = true;
        var notes = [196, 220, 261.63, 293.66];

        function strike() {
            if (!alive) return;
            var base = notes[Math.floor(Math.random() * notes.length)];
            var t = ctx.currentTime;
            [[1, 0.5], [2.71, 0.22], [5.15, 0.08]].forEach(function (p) {
                var o = ctx.createOscillator(); o.type = "sine"; o.frequency.value = base * p[0];
                var og = ctx.createGain();
                og.gain.setValueAtTime(0, t);
                og.gain.linearRampToValueAtTime(p[1], t + 0.02);
                og.gain.exponentialRampToValueAtTime(0.0001, t + 9);
                o.connect(og); og.connect(g);
                o.start(t); o.stop(t + 9.5);
            });
            setTimeout(strike, 8000 + Math.random() * 6000);
        }
        strike();

        return { gain: g, stop: function () { alive = false; } };
    }

    var BUILDERS = { rain: buildRain, waves: buildWaves, wind: buildWind, pad: buildPad, bowl: buildBowl };

    function ensureContext() {
        if (!ctx) {
            ctx = new AC();
            var comp = ctx.createDynamicsCompressor();
            master = ctx.createGain();
            master.gain.value = 0;
            master.connect(comp);
            comp.connect(ctx.destination);
            reverb = makeReverb();
        }
        if (ctx.state === "suspended") ctx.resume();
    }

    function setMaster(v) {
        if (master) master.gain.setTargetAtTime(v, ctx.currentTime, 0.3);
    }

    function masterLevel() {
        return (masterVol ? masterVol.value / 100 : 0.7) * 0.9;
    }

    function syncSounds() {
        if (!ctx || !playing) return;
        NAMES.forEach(function (n) {
            var s = state[n];
            var inst = active[n];
            if (s.on && s.vol > 0) {
                if (!inst) {
                    inst = active[n] = BUILDERS[n]();
                }
                inst.gain.gain.setTargetAtTime(s.vol * 0.8, ctx.currentTime, 0.4);
            } else if (inst) {
                inst.gain.gain.setTargetAtTime(0, ctx.currentTime, 0.2);
                delete active[n];
                setTimeout(function () { inst.stop(); }, 1500);
            }
        });
    }

    function start() {
        if (!AC) return;
        ensureContext();
        playing = true;
        fadeStarted = false;
        syncSounds();
        setMaster(masterLevel());
        playBtn.textContent = "⏸ Hentikan";
        playBtn.setAttribute("aria-pressed", "true");

        var mins = parseInt(timerSel.value, 10) || 0;
        endAt = mins ? Date.now() + mins * 60000 : 0;
        clearInterval(tickId);
        tickId = setInterval(tick, 1000);
        tick();

        if ("mediaSession" in navigator && window.MediaMetadata) {
            navigator.mediaSession.metadata = new MediaMetadata({ title: "Ruang tenang", artist: "MoodJourney" });
            navigator.mediaSession.setActionHandler("pause", stop);
            navigator.mediaSession.setActionHandler("play", start);
        }
    }

    function stop(message) {
        playing = false;
        clearInterval(tickId);
        if (ctx && master) {
            master.gain.setTargetAtTime(0, ctx.currentTime, 0.25);
            var current = active;
            active = {};
            setTimeout(function () {
                Object.keys(current).forEach(function (k) { current[k].stop(); });
            }, 1200);
        }
        playBtn.textContent = "▶ Putar";
        playBtn.setAttribute("aria-pressed", "false");
        statusEl.textContent = typeof message === "string" ? message : "";
    }

    function tick() {
        if (!playing) return;
        if (!endAt) {
            statusEl.textContent = "Sedang diputar.";
            return;
        }
        var remaining = Math.max(0, Math.round((endAt - Date.now()) / 1000));
        var mm = Math.floor(remaining / 60);
        var ss = String(remaining % 60).padStart(2, "0");
        statusEl.textContent = "Mati otomatis dalam " + mm + ":" + ss;
        if (remaining <= 20 && !fadeStarted && master) {
            fadeStarted = true;
            master.gain.cancelScheduledValues(ctx.currentTime);
            master.gain.setValueAtTime(master.gain.value, ctx.currentTime);
            master.gain.linearRampToValueAtTime(0, ctx.currentTime + remaining);
        }
        if (remaining <= 0) stop("Selesai. Semoga terasa lebih tenang.");
    }

    /* ---------------------------------------------------------- kontrol */

    if (!AC) {
        var warn = root.querySelector("[data-unsupported]");
        if (warn) warn.hidden = false;
        if (playBtn) playBtn.disabled = true;
    } else {
        playBtn.addEventListener("click", function () { playing ? stop() : start(); });

        NAMES.forEach(function (n) {
            var on = root.querySelector('[data-on="' + n + '"]');
            var vol = root.querySelector('[data-vol="' + n + '"]');
            if (on) on.addEventListener("change", function () { state[n].on = on.checked; syncSounds(); });
            if (vol) vol.addEventListener("input", function () { state[n].vol = vol.value / 100; syncSounds(); });
        });

        if (masterVol) masterVol.addEventListener("input", function () { if (playing) setMaster(masterLevel()); });

        if (timerSel) timerSel.addEventListener("change", function () {
            if (!playing) return;
            var mins = parseInt(timerSel.value, 10) || 0;
            endAt = mins ? Date.now() + mins * 60000 : 0;
            fadeStarted = false;
            if (master) setMaster(masterLevel());
            tick();
        });

        root.querySelectorAll("[data-preset]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var p = PRESETS[btn.dataset.preset];
                if (!p) return;
                NAMES.forEach(function (n) {
                    var on = root.querySelector('[data-on="' + n + '"]');
                    var vol = root.querySelector('[data-vol="' + n + '"]');
                    state[n].on = p[n] > 0;
                    state[n].vol = p[n] > 0 ? p[n] : state[n].vol;
                    if (on) on.checked = state[n].on;
                    if (vol && p[n] > 0) vol.value = Math.round(p[n] * 100);
                });
                if (!playing) start(); else syncSounds();
            });
        });
    }

    /* ---------------------------------------------------------- napas */

    var PATTERNS = {
        relax: [["Tarik napas", 4, "in"], ["Hembuskan pelan", 6, "out"]],
        box:   [["Tarik napas", 4, "in"], ["Tahan", 4, "hold"], ["Hembuskan", 4, "out"], ["Tahan", 4, "hold"]],
        "478": [["Tarik napas", 4, "in"], ["Tahan", 7, "hold"], ["Hembuskan", 8, "out"]]
    };

    var orb = document.getElementById("breath-orb");
    var bText = document.getElementById("breath-text");
    var bCount = document.getElementById("breath-count");
    var bToggle = document.getElementById("breath-toggle");
    var bPattern = document.getElementById("breath-pattern");

    var breathing = false;
    var phaseTimer = 0;
    var countTimer = 0;
    var cycles = 0;

    function setOrb(kind, secs) {
        if (!orb) return;
        if (reduceMotion) {
            orb.style.transition = "none";
        } else {
            orb.style.transition = "transform " + secs + "s ease-in-out, box-shadow " + secs + "s ease-in-out";
        }
        if (kind === "in") orb.style.transform = "scale(1)";
        else if (kind === "out") orb.style.transform = "scale(0.55)";
    }

    function runPhase(pattern, i) {
        if (!breathing) return;
        var phase = pattern[i % pattern.length];
        if (i % pattern.length === 0) {
            cycles += 1;
        }
        bText.textContent = phase[0];
        setOrb(phase[2], phase[1]);

        var left = phase[1];
        bCount.textContent = left + " · putaran " + cycles;
        clearInterval(countTimer);
        countTimer = setInterval(function () {
            left -= 1;
            if (left > 0) bCount.textContent = left + " · putaran " + cycles;
        }, 1000);

        phaseTimer = setTimeout(function () { runPhase(pattern, i + 1); }, phase[1] * 1000);
    }

    function breathStart() {
        breathing = true;
        cycles = 0;
        bToggle.textContent = "Berhenti";
        if (orb) { orb.style.transition = "none"; orb.style.transform = "scale(0.55)"; void orb.offsetWidth; }
        runPhase(PATTERNS[bPattern.value] || PATTERNS.relax, 0);
    }

    function breathStop() {
        breathing = false;
        clearTimeout(phaseTimer);
        clearInterval(countTimer);
        bToggle.textContent = "Mulai";
        bText.textContent = cycles > 0 ? "Bagus. Kamu menyelesaikan " + cycles + " putaran." : "Siap?";
        bCount.textContent = "";
        if (orb) { orb.style.transition = "transform 1s ease-out"; orb.style.transform = "scale(0.75)"; }
    }

    if (bToggle) {
        bToggle.addEventListener("click", function () { breathing ? breathStop() : breathStart(); });
        bPattern.addEventListener("change", function () { if (breathing) { breathStop(); breathStart(); } });
    }
    if (orb) orb.style.transform = "scale(0.75)";

    // hentikan semuanya saat halaman ditinggalkan
    window.addEventListener("pagehide", function () { if (playing) stop(); breathStop(); });
})();
