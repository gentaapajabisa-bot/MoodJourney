<?php
$user = require_member();

$sounds = [
    "rain"  => ["Hujan",   "Rintik hujan yang rata dan lembut"],
    "waves" => ["Ombak",   "Deburan ombak yang datang dan pergi"],
    "wind"  => ["Angin",   "Hembusan angin yang berayun pelan"],
    "pad"   => ["Dengung", "Nada panjang yang hangat dan berganti perlahan"],
    "bowl"  => ["Mangkuk", "Dentingan mangkuk nyanyi yang jarang dan panjang"],
];

page_header("Tenang", ["active" => "calm"]);
?>
<div class="container">

    <section class="page-title">
        <h1>Ruang tenang</h1>
        <p class="muted">Campur suara yang menenangkan dan ikuti pemandu napas. Semuanya dibuat langsung di perangkatmu, tanpa unduhan dan tanpa mengirim data ke mana pun.</p>
    </section>

    <section class="grid-2 calm-grid" id="calm">

        <div class="card">
            <h2>Suara</h2>
            <div class="notice notice-info" data-unsupported hidden>Browser ini belum mendukung audio yang dibutuhkan.</div>

            <div class="preset-row" role="group" aria-label="Pilihan cepat">
                <button type="button" class="chip-btn" data-preset="rain">🌧️ Hujan malam</button>
                <button type="button" class="chip-btn" data-preset="beach">🌊 Pantai</button>
                <button type="button" class="chip-btn" data-preset="focus">🍃 Fokus</button>
                <button type="button" class="chip-btn" data-preset="sleep">🌙 Tidur</button>
            </div>

            <?php foreach ($sounds as $key => [$label, $desc]): ?>
                <div class="sound-row">
                    <label class="switch">
                        <input type="checkbox" data-on="<?= h($key) ?>" <?= $key === "rain" ? "checked" : "" ?>>
                        <span class="switch-ui" aria-hidden="true"></span>
                        <span class="switch-text"><strong><?= h($label) ?></strong><small class="muted block"><?= h($desc) ?></small></span>
                    </label>
                    <label class="sr-only" for="vol-<?= h($key) ?>">Volume <?= h($label) ?></label>
                    <input class="range" type="range" id="vol-<?= h($key) ?>" data-vol="<?= h($key) ?>" min="0" max="100" value="60">
                </div>
            <?php endforeach; ?>

            <div class="sound-row master-row">
                <label for="vol-master"><strong>Volume utama</strong></label>
                <input class="range" type="range" id="vol-master" min="0" max="100" value="70">
            </div>

            <div class="calm-controls">
                <button type="button" class="btn" id="calm-play" aria-pressed="false">▶ Putar</button>
                <label class="timer-label" for="calm-timer">Matikan otomatis</label>
                <select class="form-control select-small" id="calm-timer">
                    <option value="0">Tidak</option>
                    <option value="5">5 menit</option>
                    <option value="10">10 menit</option>
                    <option value="20" selected>20 menit</option>
                    <option value="30">30 menit</option>
                    <option value="60">60 menit</option>
                </select>
            </div>
            <p class="muted" id="calm-status" aria-live="polite"></p>
            <p class="muted calm-note">Di sebagian perangkat, suara bisa berhenti saat layar terkunci. Kalau ingin musik favoritmu, buka aplikasi musik yang kamu pakai. Kami sengaja tidak menyematkan pemutar pihak ketiga demi privasimu.</p>
        </div>

        <div class="card breath-card" id="breath">
            <h2>Pemandu napas</h2>
            <p class="muted">Ikuti lingkaran. Hembusan yang lebih panjang dari tarikan membantu tubuh melambat. Berhenti kapan saja kalau terasa tidak nyaman.</p>

            <div class="breath-stage" aria-hidden="true">
                <div class="breath-orb" id="breath-orb"></div>
            </div>
            <p class="breath-text" id="breath-text" role="status" aria-live="polite">Siap?</p>
            <p class="muted breath-count" id="breath-count"></p>

            <div class="calm-controls">
                <button type="button" class="btn" id="breath-toggle">Mulai</button>
                <label class="timer-label" for="breath-pattern">Pola</label>
                <select class="form-control select-small" id="breath-pattern">
                    <option value="relax">Santai 4–6</option>
                    <option value="box">Kotak 4–4–4–4</option>
                    <option value="478">4–7–8</option>
                </select>
            </div>
        </div>

    </section>

    <aside class="support-card">
        <p class="support-title">Kalau rasa cemas atau sedihnya terasa berat</p>
        <p>Suara dan napas membantu menenangkan, tetapi bukan pengganti bicara dengan orang lain. Kamu bisa mengajukan bicara dengan konselor atau melihat layanan bantuan.</p>
        <div class="form-actions">
            <a class="btn btn-small" href="/dukungan.php#konselor">Bicara dengan konselor</a>
            <a class="btn btn-outline btn-small" href="/bantuan.php">Layanan bantuan</a>
        </div>
    </aside>

</div>
<?php page_footer(["/assets/js/calm.js"]);
