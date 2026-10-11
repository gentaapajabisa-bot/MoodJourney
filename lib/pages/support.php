<?php
$user = require_member();
$userId = (int) $user["id"];

const SUPPORT_TOPICS = [
    "Stres kuliah atau kerja",
    "Rasa cemas",
    "Perasaan sedih berkepanjangan",
    "Hubungan dengan orang lain",
    "Sulit tidur",
    "Lainnya",
];

$counselorCount = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'counselor' AND is_active = 1", "", [], 0);
$hours = trim((string) env_value("COUNSELOR_HOURS", ""));

if (is_post()) {
    csrf_verify("/dukungan.php");
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "request") {
        $topic = (string) ($_POST["topic"] ?? "");
        $message = trim((string) ($_POST["message"] ?? ""));
        $bucket = "support:" . $userId;

        if (!in_array($topic, SUPPORT_TOPICS, true)) {
            flash("error", "Pilih topik yang paling mendekati.");
        } elseif (mb_strlen($message) > 1000) {
            flash("error", "Pesan maksimal 1000 karakter.");
        } elseif (empty($_POST["consent"])) {
            flash("error", "Centang persetujuan bahwa pesanmu akan dibaca konselor dan admin.");
        } elseif ($counselorCount === 0) {
            flash("error", "Belum ada konselor yang tersedia. Lihat layanan di halaman Bantuan.");
        } elseif (db_one("SELECT id FROM support_requests WHERE user_id = ? AND status IN ('waiting','active') LIMIT 1", "i", [$userId])) {
            flash("error", "Kamu masih punya permintaan yang berjalan.");
        } elseif (rate_count($bucket, 86400) >= 3) {
            flash("error", "Batas permintaan hari ini sudah tercapai. Coba lagi besok atau lihat halaman Bantuan.");
        } else {
            rate_hit($bucket);
            $urgent = crisis_detect($message) ? 1 : 0;
            db_exec(
                "INSERT INTO support_requests (user_id, topic, message, urgent) VALUES (?, ?, ?, ?)",
                "issi",
                [$userId, $topic, $message, $urgent]
            );

            // pesan pembuka masuk ke ruang chat supaya konselor punya konteks
            $room = chat_get_room($userId);
            $text = "[Permintaan konselor] Topik: " . $topic . ($message !== "" ? "\n" . $message : "");
            chat_send((int) $room["id"], $userId, $text, true, true);

            if ($urgent) {
                flash("info", crisis_message());
            }
            flash("success", "Permintaanmu sudah diterima. Konselor akan menanggapi di chat" . ($hours !== "" ? " sesuai jam layanan (" . $hours . ")" : "") . ". Balasan mungkin tidak langsung.");
        }
        redirect("/dukungan.php#konselor");
    }

    if ($action === "cancel") {
        db_exec(
            "UPDATE support_requests SET status = 'cancelled', updated_at = NOW()
             WHERE id = ? AND user_id = ? AND status IN ('waiting','active')",
            "ii",
            [(int) ($_POST["id"] ?? 0), $userId]
        );
        flash("success", "Permintaan dibatalkan.");
        redirect("/dukungan.php#konselor");
    }

    redirect("/dukungan.php");
}

$open = db_one(
    "SELECT sr.id, sr.topic, sr.status, sr.created_at, c.name AS counselor_name
     FROM support_requests sr LEFT JOIN users c ON c.id = sr.counselor_id
     WHERE sr.user_id = ? AND sr.status IN ('waiting','active') ORDER BY sr.id DESC LIMIT 1",
    "i",
    [$userId]
);
$past = db_all(
    "SELECT topic, status, created_at FROM support_requests
     WHERE user_id = ? AND status IN ('done','cancelled') ORDER BY id DESC LIMIT 3",
    "i",
    [$userId]
);
$unread = unread_count_for($user);

page_header("Dukungan", ["active" => "support"]);
?>
<div class="container">

    <section class="page-title">
        <h1>Dukungan</h1>
        <p class="muted">Tanya soal aplikasi, hubungi tim, atau ajukan bicara dengan konselor.</p>
    </section>

    <aside class="support-card" aria-label="Bantuan segera">
        <p class="support-title">Butuh bantuan segera?</p>
        <p>
            Kalau kamu dalam bahaya atau berpikir untuk menyakiti dirimu, hubungi <a class="link" href="tel:112"><strong>112</strong></a>
            atau <a class="link" href="tel:119"><strong>119</strong></a> lalu tekan 8 (Healing119.id, Kementerian Kesehatan).
            Layanan di halaman ini tidak selalu membalas langsung.
        </p>
        <a class="btn btn-small" href="/bantuan.php">Lihat semua layanan bantuan</a>
    </aside>

    <section class="grid-2 support-grid">

        <div class="card" id="asisten">
            <h2>Asisten MoodJourney</h2>
            <p class="muted">Menjawab pertanyaan seputar penggunaan aplikasi. Ini panduan otomatis, bukan AI, dan percakapan ini tidak disimpan.</p>

            <div class="bot" id="bot" data-endpoint="/bot.php" data-csrf="<?= h(csrf_token()) ?>">
                <div class="bot-messages" id="bot-messages" aria-live="polite" tabindex="0" aria-label="Percakapan dengan asisten">
                    <div class="bot-msg bot">Halo! Tanyakan apa saja soal cara memakai MoodJourney, misalnya langkah mencatat mood atau membaca statistik. Atau pilih salah satu pertanyaan di bawah.</div>
                </div>
                <div class="chip-row bot-suggestions">
                    <?php foreach (bot_suggestions() as $q): ?>
                        <button type="button" class="chip-btn" data-bot-q="<?= h($q) ?>"><?= h($q) ?></button>
                    <?php endforeach; ?>
                </div>
                <form class="bot-form" id="bot-form" autocomplete="off">
                    <label class="sr-only" for="bot-input">Pertanyaanmu</label>
                    <input class="form-control" type="text" id="bot-input" maxlength="300" placeholder="Tulis pertanyaanmu…">
                    <button class="btn" type="submit">Kirim</button>
                </form>
            </div>
        </div>

        <div class="stack">
            <div class="card" id="konselor">
                <h2>Bicara dengan konselor</h2>

                <?php if ($open): ?>
                    <div class="request-status status-<?= h($open["status"]) ?>">
                        <?php if ($open["status"] === "waiting"): ?>
                            <strong>Menunggu konselor</strong>
                            <p class="muted">Topik: <?= h($open["topic"]) ?> · diajukan <?= h(date_id($open["created_at"])) ?>.
                                Kamu akan melihat pesan di chat saat konselor mengambil permintaanmu.</p>
                        <?php else: ?>
                            <strong>Ditangani oleh <?= h($open["counselor_name"] ?? "konselor") ?></strong>
                            <p class="muted">Topik: <?= h($open["topic"]) ?>. Lanjutkan percakapan di chat.</p>
                            <a class="btn btn-small" href="/chat.php">Buka chat</a>
                        <?php endif; ?>
                    </div>
                    <form method="POST" data-confirm="Batalkan permintaan ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="<?= (int) $open["id"] ?>">
                        <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Batalkan permintaan</button>
                    </form>
                <?php elseif ($counselorCount === 0): ?>
                    <p class="muted">Belum ada konselor yang tersedia di aplikasi ini. Untuk bicara dengan tenaga profesional, gunakan layanan di halaman Bantuan.</p>
                    <a class="btn btn-small" href="/bantuan.php">Lihat layanan bantuan</a>
                <?php else: ?>
                    <p class="muted">
                        Ajukan permintaan dan konselor akan menanggapi lewat chat.
                        <?= $hours !== "" ? "Jam layanan: " . h($hours) . "." : "" ?>
                        Balasan tidak selalu langsung, jadi untuk keadaan darurat gunakan 112.
                    </p>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request">

                        <div class="form-group">
                            <label for="topic">Topik</label>
                            <select class="form-control" id="topic" name="topic" required>
                                <?php foreach (SUPPORT_TOPICS as $t): ?>
                                    <option value="<?= h($t) ?>"><?= h($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">Ceritakan singkat <span class="muted">(opsional)</span></label>
                            <textarea class="form-control" id="message" name="message" rows="4" maxlength="1000" data-counter="req-count"></textarea>
                            <small class="muted"><span id="req-count">0</span>/1000</small>
                        </div>
                        <label class="check-row">
                            <input type="checkbox" name="consent" value="1" required>
                            <span>Aku paham pesan ini akan dibaca konselor yang menangani dan admin.</span>
                        </label>
                        <button class="btn" type="submit">Kirim permintaan</button>
                    </form>
                <?php endif; ?>

                <?php if ($past): ?>
                    <hr class="divider">
                    <h3>Sebelumnya</h3>
                    <ul class="tag-list">
                        <?php foreach ($past as $p): ?>
                            <li><span><?= h($p["topic"]) ?></span>
                                <span class="muted"><?= $p["status"] === "done" ? "Selesai" : "Dibatalkan" ?> · <?= h(date_id($p["created_at"])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Chat dengan tim</h2>
                <p class="muted">Untuk pertanyaan yang tidak terjawab asisten, atau soal akun. Pesan dibaca admin.</p>
                <a class="btn btn-outline" href="/chat.php">Buka chat<?= $unread > 0 ? ' <span class="badge">' . $unread . "</span>" : "" ?></a>
            </div>
        </div>

    </section>

</div>
<?php page_footer();
