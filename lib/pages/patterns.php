<?php
$user = require_member();
$userId = (int) $user["id"];

$entries = db_all(
    "SELECT mood, tags, diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date ASC, id ASC",
    "is",
    [$userId, date("Y-m-d", strtotime("-180 days"))]
);
$p = patterns_compute($entries);
$sentences = $p["enough"] ? patterns_sentences($p) : [];
$dayNames = [1 => "Sen", 2 => "Sel", 3 => "Rab", 4 => "Kam", 5 => "Jum", 6 => "Sab", 7 => "Min"];

page_header("Pola", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("pattern"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Pola moodmu</h1>
            <p class="muted">Kecenderungan dari catatanmu 6 bulan terakhir. Dihitung langsung dari angka, tanpa membaca isi ceritamu.</p>
        </div>
    </section>

    <?php if (!$p["enough"]): ?>
        <section class="card pattern-empty">
            <div class="pattern-empty-art" aria-hidden="true">🔍</div>
            <h2>Polanya masih terkumpul</h2>
            <p class="muted">Dibutuhkan sekitar <?= PATTERN_MIN_DAYS ?> hari tercatat supaya kecenderungannya cukup berarti. Kamu sudah mencatat <strong><?= (int) $p["days"] ?></strong> hari.</p>
            <span class="onboard-bar" aria-hidden="true"><i style="width: <?= min(100, round($p["days"] / PATTERN_MIN_DAYS * 100)) ?>%"></i></span>
            <p><a class="btn" href="/diary.php">Catat mood</a></p>
            <p class="muted card-foot">Tandai aktivitas di tiap catatan (tidur, olahraga, teman, dan lainnya) agar wawasan ini lebih kaya.</p>
        </section>
    <?php else: ?>

        <section class="card pattern-insights">
            <h2>Yang kami perhatikan</h2>
            <?php if (!$sentences): ?>
                <p class="muted">Belum ada kecenderungan yang menonjol. Terus catat, dan tandai aktivitasmu.</p>
            <?php else: ?>
            <ul class="pattern-list">
                <?php foreach (array_slice($sentences, 0, 6) as $s): ?>
                    <li class="pattern-item is-<?= h($s["tone"]) ?>">
                        <span class="pattern-icon" aria-hidden="true"><?= $s["icon"] ?></span>
                        <span><?= h($s["text"]) ?><small class="muted"><?= h($s["note"]) ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="muted card-foot">Ini hanya hubungan yang terlihat di datamu, bukan sebab-akibat dan bukan penilaian atau diagnosis.</p>
        </section>

        <section class="grid-2">
            <div class="card">
                <h2>Rata-rata per hari</h2>
                <p class="muted">Skor 1 (berat) sampai 5 (baik).</p>
                <div class="yip-months pattern-week" role="img" aria-label="Rata-rata skor mood tiap hari dalam seminggu">
                    <?php foreach ($dayNames as $w => $label): $v = $p["weekdays"][$w]; ?>
                        <div class="yip-month">
                            <span class="yip-month-bar">
                                <?php if ($v !== null): ?>
                                    <i style="height: <?= round($v["avg"] / 5 * 100) ?>%" data-score="<?= (int) round($v["avg"]) ?>" title="<?= h($label . ": " . number_format($v["avg"], 1)) ?>"></i>
                                <?php endif; ?>
                            </span>
                            <small><?= h($label) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2>Aktivitas dan moodmu</h2>
                <p class="muted">Selisih rata-rata skor di hari dengan aktivitas itu.</p>
                <?php if (!$p["tags"]): ?>
                    <p class="muted">Belum cukup data per aktivitas (minimal <?= PATTERN_MIN_TAG_DAYS ?> hari dengan dan tanpa aktivitas itu).</p>
                <?php else: foreach (array_slice($p["tags"], 0, 7) as $t):
                    $w = min(100, round(abs($t["diff"]) / 1.5 * 100)); ?>
                    <div class="pattern-row">
                        <span class="pattern-row-label"><?= h(TAGS[$t["tag"]] ?? $t["tag"]) ?></span>
                        <span class="pattern-diff" aria-hidden="true">
                            <i class="<?= $t["diff"] >= 0 ? "is-up" : "is-down" ?>" style="width: <?= $w ?>%"></i>
                        </span>
                        <span class="pattern-row-n"><?= ($t["diff"] >= 0 ? "+" : "−") . number_format(abs($t["diff"]), 1) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

    <?php endif; ?>

</div>
<?php page_footer();
