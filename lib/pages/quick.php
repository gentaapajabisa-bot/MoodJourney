<?php
/** Check-in cepat: satu ketukan untuk mencatat mood hari ini. */
$user = require_member();
$userId = (int) $user["id"];
$today = date("Y-m-d");

if (is_post()) {
    csrf_verify("/cepat.php");
    $mood = (string) ($_POST["mood"] ?? "");
    if (!isset(MOODS[$mood])) {
        flash("error", "Pilih salah satu mood.");
        redirect("/cepat.php");
    }
    if (rate_count("quick:" . $userId, 60) >= 10) {
        flash("error", "Pelan-pelan ya. Coba lagi sebentar lagi.");
        redirect("/dashboard.php");
    }
    rate_hit("quick:" . $userId);

    $stageBefore = journey_load($userId)["stage"];
    [, $savedId] = db_exec(
        "INSERT INTO diary (user_id, mood, intensity, tags, activity, note, diary_date) VALUES (?, ?, 3, '', '', '', ?)",
        "iss",
        [$userId, $mood, $today]
    );
    flash("success", "Check-in tersimpan. Mau menambahkan cerita? Buka Riwayat lalu klik Ubah kapan saja.");
    diary_after_save($userId, $stageBefore, $mood);
    redirect("/dashboard.php");
}

$doneToday = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ? AND diary_date = ?", "is", [$userId, $today], 0) > 0;

page_header("Check-in cepat", ["active" => "diary"]);
?>
<div class="container narrow">
    <section class="card quick-card">
        <h1>Gimana perasaanmu sekarang?</h1>
        <p class="muted">Satu ketukan saja. Cerita dan aktivitas bisa ditambahkan nanti.</p>
        <?php if ($doneToday): ?>
            <div class="notice notice-info">Hari ini sudah ada catatan. Check-in ini akan ditambahkan sebagai catatan baru.</div>
        <?php endif; ?>

        <form method="POST" class="quick-grid">
            <?= csrf_field() ?>
            <?php foreach (MOODS as $emoji => $m): ?>
                <button class="quick-mood" type="submit" name="mood" value="<?= h($emoji) ?>" aria-label="<?= h($m["label"]) ?>">
                    <span class="quick-emoji" aria-hidden="true"><?= h($emoji) ?></span>
                    <span class="quick-label"><?= h($m["label"]) ?></span>
                </button>
            <?php endforeach; ?>
        </form>
        <p class="muted card-foot">Mau menulis lengkap? <a class="link" href="/diary.php">Buka formulir catatan</a>.</p>
    </section>
</div>
<?php page_footer();
