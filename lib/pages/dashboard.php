<?php
$user = require_member();
$userId = (int) $user["id"];

$total = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ?", "i", [$userId], 0);

$dates = array_column(
    db_all(
        "SELECT DISTINCT diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date DESC",
        "is",
        [$userId, date("Y-m-d", strtotime("-3650 days"))]
    ),
    "diary_date"
);
$streak = compute_streaks($dates);

// 7 hari terakhir
$weekFrom = date("Y-m-d", strtotime("-6 days"));
$weekEntries = db_all(
    "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date ASC, id ASC",
    "is",
    [$userId, $weekFrom]
);
$weekScores = daily_scores($weekEntries);
$lastMoodPerDay = [];
foreach ($weekEntries as $e) {
    $lastMoodPerDay[$e["diary_date"]] = $e["mood"];
}
$weekAvg = $weekScores ? array_sum($weekScores) / count($weekScores) : null;

$recent = db_all(
    "SELECT * FROM diary WHERE user_id = ? ORDER BY diary_date DESC, id DESC LIMIT 5",
    "i",
    [$userId]
);

attach_photo_ids($recent, $userId);

$showSupport = needs_support_hint($userId);
$journey = journey_load($userId);

$goals = array_slice(goals_with_progress($userId), 0, 3);
$badgeRows = db_all(
    "SELECT code, seen FROM achievements WHERE user_id = ? ORDER BY earned_at DESC, id DESC LIMIT 4",
    "i",
    [$userId]
);
$badgeCount = (int) db_value("SELECT COUNT(*) FROM achievements WHERE user_id = ?", "i", [$userId], 0);

page_header("Dashboard", ["active" => "dashboard"]);
?>
<div class="container">

    <section class="dashboard-header">
        <div>
            <h1>Halo, <?= h($user["name"]) ?> 👋</h1>
            <p class="muted">Gimana perasaanmu hari ini?</p>
        </div>
        <a href="/diary.php" class="btn">+ Catat mood</a>
    </section>

    <?php if (!$streak["today_done"]): ?>
        <div class="notice notice-info reminder">
            <span>
                <?php if ($streak["current"] > 0): ?>
                    Kamu sudah mencatat <?= $streak["current"] ?> hari berturut-turut. Catat hari ini untuk melanjutkan.
                <?php else: ?>
                    Kamu belum mencatat mood hari ini. Cukup satu menit.
                <?php endif; ?>
            </span>
            <a class="btn btn-small" href="/diary.php">Catat sekarang</a>
        </div>
    <?php endif; ?>

    <?php if ($showSupport): ?>
        <aside class="support-card" aria-label="Dukungan">
            <p class="support-title">Beberapa hari terakhir terasa berat.</p>
            <p>
                Itu wajar dan kamu tidak perlu menghadapinya sendirian. Ngobrol dengan orang yang kamu percaya
                atau tenaga profesional bisa membantu. Ada layanan gratis yang bisa dihubungi kapan saja.
            </p>
            <a class="btn btn-small" href="/bantuan.php">Lihat layanan bantuan</a>
        </aside>
    <?php endif; ?>

    <section class="grid-3">
        <a class="stat-card journey-mini" data-tilt="6" href="/metamorfosis.php" aria-label="Lihat metamorfosis kupu-kupumu">
            <span class="journey-mini-art"><?= butterfly_svg($journey["display"], $journey["colors"], ["animate" => false, "label" => BUTTERFLY_STAGES[$journey["display"]]["label"]]) ?></span>
            <span class="journey-mini-body">
                <span class="muted">Perjalananmu</span>
                <span class="number"><?= $journey["days"] ?> <small>hari</small></span>
                <span class="stat-note"><?= h($journey["resting"] ? "Kepompong, sedang istirahat" : BUTTERFLY_STAGES[$journey["display"]]["label"]) ?> · beruntun <?= $streak["current"] ?> hari</span>
            </span>
        </a>

        <div class="stat-card" data-tilt="6">
            <span class="muted">Mood 7 hari terakhir</span>
            <?php if ($weekAvg !== null): ?>
                <span class="number"><?= h(score_to_emoji($weekAvg)) ?> <small><?= h(score_label($weekAvg)) ?></small></span>
                <span class="stat-note">Rata-rata <?= number_format($weekAvg, 1) ?> dari 5</span>
            <?php else: ?>
                <span class="number">–</span>
                <span class="stat-note">Belum ada catatan minggu ini</span>
            <?php endif; ?>
        </div>

        <div class="stat-card" data-tilt="6">
            <span class="muted">Total catatan</span>
            <span class="number"><?= $total ?></span>
            <span class="stat-note"><a class="link" href="/history.php">Lihat semua</a></span>
        </div>
    </section>

    <section class="card week-card">
        <h2>7 hari terakhir</h2>
        <div class="week-strip">
            <?php for ($i = 6; $i >= 0; $i--):
                $d = date("Y-m-d", strtotime("-$i days"));
                $has = isset($lastMoodPerDay[$d]);
                $score = $has ? (int) round($weekScores[$d]) : 0;
                $dayName = mb_substr(DAYS_ID[(int) date("N", strtotime($d))], 0, 3);
            ?>
                <a class="week-day <?= $has ? "filled" : "" ?>" <?= $has ? 'data-score="' . $score . '"' : "" ?>
                   href="<?= $has ? "/history.php?from=$d&to=$d" : "/diary.php?date=$d" ?>"
                   title="<?= h(date_id($d)) ?>">
                    <span class="week-name"><?= h($dayName) ?></span>
                    <span class="week-emoji"><?= $has ? h($lastMoodPerDay[$d]) : "·" ?></span>
                    <span class="week-num"><?= (int) date("j", strtotime($d)) ?></span>
                </a>
            <?php endfor; ?>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <div class="section-head">
                <h2>Target minggu ini</h2>
                <a class="btn btn-outline btn-small" href="/target.php">Kelola</a>
            </div>
            <?php if (!$goals): ?>
                <p class="muted">Belum ada target. Pilih satu kebiasaan yang ingin kamu jaga.</p>
                <a class="btn btn-small" href="/target.php">Buat target</a>
            <?php endif; ?>
            <?php foreach ($goals as $g): $pct = min(100, round($g["done"] / max(1, $g["target"]) * 100)); ?>
                <div class="goal-row">
                    <div class="goal-head">
                        <strong><?= h($g["label"]) ?></strong>
                        <span class="<?= $g["met"] ? "delta up" : "muted" ?>"><?= $g["met"] ? "✓ " : "" ?><?= (int) $g["done"] ?>/<?= (int) $g["target"] ?> hari</span>
                    </div>
                    <div class="bar-bg" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $g["target"] ?>" aria-valuenow="<?= (int) $g["done"] ?>" aria-label="<?= h($g["label"]) ?>">
                        <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="section-head">
                <h2>Pencapaian</h2>
                <a class="btn btn-outline btn-small" href="/pencapaian.php"><?= $badgeCount ?>/<?= count(ACHIEVEMENTS) ?></a>
            </div>
            <?php if (!$badgeRows): ?>
                <p class="muted">Catat mood pertamamu untuk membuka pencapaian pertama.</p>
            <?php else: ?>
                <div class="badge-strip">
                    <?php foreach ($badgeRows as $b): [$icon, $title] = ACHIEVEMENTS[$b["code"]] ?? ["🏅", $b["code"]]; ?>
                        <a class="badge-mini <?= (int) $b["seen"] === 0 ? "is-new" : "" ?>" href="/pencapaian.php" title="<?= h($title) ?>">
                            <span aria-hidden="true"><?= h($icon) ?></span>
                            <small><?= h($title) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="muted card-foot"><a class="link" href="/ringkasan.php">Lihat ringkasan mingguan →</a></p>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2>Catatan terbaru</h2>
                <p class="muted">Lima catatan mood terakhir kamu.</p>
            </div>
            <a class="btn btn-outline btn-small" href="/history.php">Semua riwayat</a>
        </div>

        <?php if (!$recent): ?>
            <div class="empty">
                <p>Belum ada catatan. Mulai dari yang kecil: satu emoji dan satu kalimat.</p>
                <a href="/diary.php" class="btn">Buat catatan pertama</a>
            </div>
        <?php else: ?>
            <?php foreach ($recent as $entry): ?>
                <?= render_diary_item($entry, true, "/dashboard.php") ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div>
<?php page_footer();
