<?php
/**
 * Formulir "Bicara dengan konselor" (pengganti tombol ajukan satu langkah).
 * Pengguna mengisi form bertahap, lalu sistem mengarahkan ke konselor yang tersedia.
 */
$user = require_member();
$userId = (int) $user["id"];

$topics = counselor_topics();
$durations = ["baru" => "Baru beberapa hari", "minggu" => "Beberapa minggu", "bulan" => "Beberapa bulan", "lama" => "Lebih dari setengah tahun"];
$times = ["pagi" => "Pagi (06.00–11.59)", "siang" => "Siang (12.00–16.59)", "malam" => "Malam (17.00–21.00)", "bebas" => "Kapan saja"];
$feelings = [1 => "Sangat berat", 2 => "Berat", 3 => "Sedang", 4 => "Cukup ringan", 5 => "Baik-baik saja"];

$open = db_one(
    "SELECT sr.*, c.name AS counselor_name
     FROM support_requests sr LEFT JOIN users c ON c.id = sr.counselor_id
     WHERE sr.user_id = ? AND sr.status IN ('waiting','active')
     ORDER BY sr.id DESC LIMIT 1",
    "i",
    [$userId]
);

$old = [];
$errors = [];

if (is_post() && $open === null) {
    csrf_verify("/formulir-konselor.php");

    $old = [
        "topic"      => (string) ($_POST["topic"] ?? ""),
        "feeling"    => (int) ($_POST["feeling"] ?? 0),
        "duration"   => (string) ($_POST["duration"] ?? ""),
        "message"    => trim((string) ($_POST["message"] ?? "")),
        "time"       => (string) ($_POST["time"] ?? ""),
        "first_time" => (string) ($_POST["first_time"] ?? ""),
        "pref"       => (int) ($_POST["pref"] ?? 0),
        "consent"    => isset($_POST["consent"]),
    ];

    if (!isset($topics[$old["topic"]])) {
        $errors["topic"] = "Pilih salah satu topik.";
    }
    if ($old["feeling"] < 1 || $old["feeling"] > 5) {
        $errors["feeling"] = "Pilih seberapa berat yang kamu rasakan sekarang.";
    }
    if (!isset($durations[$old["duration"]])) {
        $errors["duration"] = "Pilih sudah berapa lama kamu merasakannya.";
    }
    if (mb_strlen($old["message"]) < 10) {
        $errors["message"] = "Ceritakan singkat dengan beberapa kalimat (minimal 10 karakter).";
    } elseif (mb_strlen($old["message"]) > 1500) {
        $errors["message"] = "Maksimal 1.500 karakter.";
    }
    if (!isset($times[$old["time"]])) {
        $errors["time"] = "Pilih waktu yang nyaman untukmu.";
    }
    if (!in_array($old["first_time"], ["1", "0"], true)) {
        $errors["first_time"] = "Pilih salah satu.";
    }
    if (!$old["consent"]) {
        $errors["consent"] = "Centang persetujuan ini untuk melanjutkan.";
    }

    $bucket = "csr:" . $userId;
    if ($errors === [] && rate_count($bucket, 3600) >= 5) {
        $errors["form"] = "Terlalu sering mengirim. Coba lagi nanti.";
    }

    if ($errors === []) {
        rate_hit($bucket);
        $urgent = (crisis_detect($old["message"]) || $old["feeling"] === 1) ? 1 : 0;
        $preferred = $old["pref"] > 0 ? $old["pref"] : null;
        $assigned = counselor_match($old["topic"], $preferred);

        db_exec(
            "INSERT INTO support_requests
                (user_id, topic, message, status, counselor_id, urgent, feeling_level, duration, preferred_time, first_time, preferred_counselor_id, updated_at)
             VALUES (?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, NOW())",
            "issiiissii",
            [$userId, $old["topic"], $old["message"], $assigned, $urgent, $old["feeling"], $old["duration"], $old["time"], (int) $old["first_time"], $preferred]
        );

        $_SESSION["csr_done"] = ["assigned" => $assigned !== null, "urgent" => $urgent === 1];
        redirect("/formulir-konselor.php");
    }
}

$done = $_SESSION["csr_done"] ?? null;
unset($_SESSION["csr_done"]);

$available = counselors_available();
$topicChosen = $old["topic"] ?? "";

page_header("Bicara dengan konselor", ["active" => "support"]);
?>
<div class="container narrow">

    <section class="page-title">
        <h1>Bicara dengan konselor</h1>
        <p class="muted">Isi formulir singkat ini (sekitar 2 menit). Jawabanmu dipakai untuk mengarahkanmu ke konselor yang tersedia dan paling sesuai.</p>
    </section>

    <?php if (is_array($done)): ?>
        <section class="card cform-done" role="status">
            <h2>Formulir terkirim 🤍</h2>
            <p>
                <?php if ($done["assigned"]): ?>
                    Terima kasih sudah berani bercerita. Permintaanmu sudah diarahkan ke konselor yang tersedia. Kamu akan menerima balasan lewat chat di halaman Dukungan.
                <?php else: ?>
                    Terima kasih sudah berani bercerita. Saat ini belum ada konselor yang tersedia untuk topikmu, jadi permintaanmu masuk antrean dan akan diambil begitu ada yang siap.
                <?php endif; ?>
            </p>
            <?php if ($done["urgent"]): ?>
                <div class="notice notice-error">
                    <?= h(crisis_message()) ?> Kalau kamu merasa tidak aman sekarang, jangan menunggu balasan: buka <a href="/bantuan.php">layanan bantuan darurat</a>.
                </div>
            <?php endif; ?>
            <p><a class="btn" href="/dukungan.php">Buka Dukungan</a></p>
        </section>

    <?php elseif ($open !== null): ?>
        <section class="card">
            <h2>Permintaanmu sedang diproses</h2>
            <p>
                Topik: <strong><?= h($topics[$open["topic"]] ?? $open["topic"]) ?></strong><br>
                Status:
                <?php if ($open["status"] === "active"): ?>
                    <strong>sedang ditangani<?= $open["counselor_name"] ? " oleh " . h($open["counselor_name"]) : "" ?></strong>
                <?php elseif ($open["counselor_id"]): ?>
                    <strong>diarahkan ke <?= h($open["counselor_name"]) ?>, menunggu diterima</strong>
                <?php else: ?>
                    <strong>menunggu konselor tersedia</strong>
                <?php endif; ?>
            </p>
            <p class="muted">Kamu bisa mengisi formulir baru setelah permintaan ini selesai atau dibatalkan.</p>
            <a class="btn" href="/dukungan.php">Buka Dukungan</a>
        </section>

    <?php else: ?>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert">
                <?= h($errors["form"] ?? "Ada isian yang belum lengkap. Periksa bagian yang ditandai.") ?>
            </div>
        <?php endif; ?>

        <section class="card cform-avail" aria-label="Ketersediaan konselor">
            <h2>Konselor yang tersedia</h2>
            <?php if ($available === []): ?>
                <p class="muted">Saat ini belum ada konselor yang menandai dirinya tersedia. Kamu tetap bisa mengisi formulir; permintaanmu masuk antrean.</p>
            <?php else: ?>
                <ul class="cform-counselors">
                    <?php foreach ($available as $c): ?>
                        <li>
                            <strong><?= h($c["name"]) ?></strong>
                            <?php if ($c["counselor_hours"]): ?><small class="muted"> · <?= h($c["counselor_hours"]) ?></small><?php endif; ?>
                            <div class="chip-row">
                                <?php if ($c["topic_keys"] === []): ?>
                                    <span class="chip-static">Semua topik</span>
                                <?php else: foreach ($c["topic_keys"] as $k): if (isset($topics[$k])): ?>
                                    <span class="chip-static"><?= h($topics[$k]) ?></span>
                                <?php endif; endforeach; endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <form method="POST" class="card cform" id="cform" novalidate>
            <?= csrf_field() ?>

            <div class="cform-progress" aria-hidden="true"><div class="cform-progress-bar" id="cform-bar"></div></div>
            <p class="muted cform-step-label" id="cform-step-label" aria-live="polite"></p>

            <!-- Bagian 1 -->
            <div class="cform-step" data-step="1">
                <h2>1. Apa yang ingin kamu bicarakan?</h2>
                <fieldset class="form-group">
                    <legend>Topik utama <span class="req" aria-hidden="true">*</span></legend>
                    <div class="cform-options">
                        <?php foreach ($topics as $k => $label): ?>
                            <label class="cform-opt">
                                <input type="radio" name="topic" value="<?= h($k) ?>" <?= $topicChosen === $k ? "checked" : "" ?> required>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors["topic"])): ?><p class="cform-error"><?= h($errors["topic"]) ?></p><?php endif; ?>
                </fieldset>

                <fieldset class="form-group">
                    <legend>Pernahkah kamu bicara dengan konselor sebelumnya? <span class="req" aria-hidden="true">*</span></legend>
                    <div class="cform-options">
                        <label class="cform-opt"><input type="radio" name="first_time" value="1" <?= ($old["first_time"] ?? "") === "1" ? "checked" : "" ?> required><span>Belum, ini pertama kali</span></label>
                        <label class="cform-opt"><input type="radio" name="first_time" value="0" <?= ($old["first_time"] ?? "") === "0" ? "checked" : "" ?>><span>Pernah</span></label>
                    </div>
                    <?php if (isset($errors["first_time"])): ?><p class="cform-error"><?= h($errors["first_time"]) ?></p><?php endif; ?>
                </fieldset>
            </div>

            <!-- Bagian 2 -->
            <div class="cform-step" data-step="2">
                <h2>2. Bagaimana perasaanmu sekarang?</h2>
                <fieldset class="form-group">
                    <legend>Seberapa berat yang kamu rasakan saat ini? <span class="req" aria-hidden="true">*</span></legend>
                    <div class="cform-options">
                        <?php foreach ($feelings as $n => $label): ?>
                            <label class="cform-opt">
                                <input type="radio" name="feeling" value="<?= $n ?>" <?= ($old["feeling"] ?? 0) === $n ? "checked" : "" ?> required>
                                <span><?= $n ?> · <?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors["feeling"])): ?><p class="cform-error"><?= h($errors["feeling"]) ?></p><?php endif; ?>
                </fieldset>

                <div class="form-group">
                    <label for="cf-duration">Sudah berapa lama kamu merasakannya? <span class="req" aria-hidden="true">*</span></label>
                    <select class="form-control" id="cf-duration" name="duration" required>
                        <option value="">Pilih…</option>
                        <?php foreach ($durations as $k => $label): ?>
                            <option value="<?= h($k) ?>" <?= ($old["duration"] ?? "") === $k ? "selected" : "" ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors["duration"])): ?><p class="cform-error"><?= h($errors["duration"]) ?></p><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="cf-message">Ceritakan singkat <span class="req" aria-hidden="true">*</span></label>
                    <textarea class="form-control" id="cf-message" name="message" rows="6" maxlength="1500" required placeholder="Tulis sebebas dan sejujur yang kamu nyaman. Tidak ada jawaban yang salah."><?= h($old["message"] ?? "") ?></textarea>
                    <small class="muted"><span id="cf-count">0</span>/1500</small>
                    <?php if (isset($errors["message"])): ?><p class="cform-error"><?= h($errors["message"]) ?></p><?php endif; ?>
                </div>
            </div>

            <!-- Bagian 3 -->
            <div class="cform-step" data-step="3">
                <h2>3. Preferensi sesi</h2>
                <fieldset class="form-group">
                    <legend>Waktu yang paling nyaman <span class="req" aria-hidden="true">*</span></legend>
                    <div class="cform-options">
                        <?php foreach ($times as $k => $label): ?>
                            <label class="cform-opt"><input type="radio" name="time" value="<?= h($k) ?>" <?= ($old["time"] ?? "") === $k ? "checked" : "" ?> required><span><?= h($label) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors["time"])): ?><p class="cform-error"><?= h($errors["time"]) ?></p><?php endif; ?>
                </fieldset>

                <fieldset class="form-group">
                    <legend>Pilih konselor <small class="muted">(opsional)</small></legend>
                    <div class="cform-options cform-options-col" id="cf-pref">
                        <label class="cform-opt"><input type="radio" name="pref" value="0" <?= ($old["pref"] ?? 0) === 0 ? "checked" : "" ?>><span>Arahkan otomatis ke yang paling sesuai (disarankan)</span></label>
                        <?php foreach ($available as $c): ?>
                            <label class="cform-opt" data-topics="<?= h(implode(",", $c["topic_keys"])) ?>">
                                <input type="radio" name="pref" value="<?= (int) $c["id"] ?>" <?= ($old["pref"] ?? 0) === (int) $c["id"] ? "checked" : "" ?>>
                                <span><?= h($c["name"]) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="muted">Kalau konselor pilihanmu tidak menangani topik ini, kami arahkan ke yang paling sesuai.</small>
                </fieldset>
            </div>

            <!-- Bagian 4 -->
            <div class="cform-step" data-step="4">
                <h2>4. Persetujuan</h2>
                <div class="notice">
                    Isi formulir dan percakapan dengan konselor bisa dibaca konselor yang menanganimu. Catatan diary dan fotomu tetap tidak terlihat. Kalau ada tanda bahaya terhadap dirimu, permintaan diberi penanda agar ditangani lebih dulu.
                </div>
                <div class="form-group">
                    <label class="cform-check">
                        <input type="checkbox" name="consent" value="1" <?= !empty($old["consent"]) ? "checked" : "" ?> required>
                        <span>Saya mengerti dan setuju formulir ini dibaca konselor yang menangani saya. <span class="req" aria-hidden="true">*</span></span>
                    </label>
                    <?php if (isset($errors["consent"])): ?><p class="cform-error"><?= h($errors["consent"]) ?></p><?php endif; ?>
                </div>
                <p class="muted">Kalau kamu dalam bahaya sekarang, jangan menunggu: buka <a href="/bantuan.php">layanan bantuan darurat</a>.</p>
            </div>

            <div class="cform-nav">
                <button type="button" class="btn btn-outline" id="cform-prev" hidden>Kembali</button>
                <button type="button" class="btn" id="cform-next" hidden>Lanjut</button>
                <button type="submit" class="btn" id="cform-submit">Kirim formulir</button>
            </div>
        </form>

    <?php endif; ?>
</div>
<?php page_footer(["/assets/js/counselor_form.js"]);
