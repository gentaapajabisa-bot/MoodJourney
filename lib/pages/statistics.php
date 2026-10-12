<?php
$user = require_member();
$userId = (int) $user["id"];

$ranges = [7 => "7 hari", 30 => "30 hari", 90 => "90 hari", 365 => "1 tahun"];
$range = (int) ($_GET["range"] ?? 30);
if (!isset($ranges[$range])) {
    $range = 30;
}

$to = date("Y-m-d");
$from = date("Y-m-d", strtotime("-" . ($range - 1) . " days"));

$entries = db_all(
    "SELECT mood, intensity, tags, activity, diary_date FROM diary
     WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC, id ASC",
    "iss",
    [$userId, $from, $to]
);

$total = count($entries);
$daily = daily_scores($entries);
$activeDays = count($daily);
$avg = $total ? array_sum(array_map(fn($e) => mood_score($e["mood"]), $entries)) / $total : null;
$avgIntensity = $total ? array_sum(array_map(fn($e) => (int) $e["intensity"], $entries)) / $total : null;

/* Sebaran mood */
$dist = [];
foreach ($entries as $e) {
    $dist[$e["mood"]] = ($dist[$e["mood"]] ?? 0) + 1;
}
arsort($dist);
$maxDist = $dist ? max($dist) : 1;

/* Rata-rata per hari dalam seminggu */
$weekdaySum = array_fill(1, 7, 0.0);
$weekdayCnt = array_fill(1, 7, 0);
foreach ($daily as $date => $score) {
    $n = (int) date("N", strtotime($date));
    $weekdaySum[$n] += $score;
    $weekdayCnt[$n]++;
}
$weekdayItems = [];
foreach (DAYS_ID as $n => $name) {
    $v = $weekdayCnt[$n] ? $weekdaySum[$n] / $weekdayCnt[$n] : 0;
    $weekdayItems[] = [
        "label"   => mb_substr($name, 0, 3),
        "value"   => round($v, 2),
        "display" => $weekdayCnt[$n] ? number_format($v, 1) : "–",
        "title"   => $weekdayCnt[$n] ? $name . ": rata-rata " . number_format($v, 1) . " dari " . $weekdayCnt[$n] . " hari" : $name . ": belum ada data",
    ];
}

/* Aktivitas dan mood: skor rata-rata saat aktivitas itu dicatat */
$byTag = [];
foreach ($entries as $e) {
    $score = mood_score($e["mood"]);
    $keys = [];
    foreach (tags_to_array($e["tags"]) as $t) {
        $keys[TAGS[$t]] = true;
    }
    $act = mb_strtolower(trim((string) $e["activity"]));
    if ($act !== "" && mb_strlen($act) <= 30) {
        $keys[mb_convert_case($act, MB_CASE_TITLE)] = true;
    }
    foreach (array_keys($keys) as $k) {
        $byTag[$k]["sum"] = ($byTag[$k]["sum"] ?? 0) + $score;
        $byTag[$k]["n"] = ($byTag[$k]["n"] ?? 0) + 1;
    }
}
$tagStats = [];
foreach ($byTag as $name => $d) {
    if ($d["n"] >= 3 && $avg !== null) {
        $tagStats[] = ["name" => $name, "n" => $d["n"], "avg" => $d["sum"] / $d["n"], "diff" => $d["sum"] / $d["n"] - $avg];
    }
}
usort($tagStats, fn($a, $b) => $b["diff"] <=> $a["diff"]);
$bestTags = array_values(array_filter($tagStats, fn($t) => $t["diff"] > 0.15));
$bestTags = array_slice($bestTags, 0, 4);
$lowTags = array_values(array_filter(array_reverse($tagStats), fn($t) => $t["diff"] < -0.15));
$lowTags = array_slice($lowTags, 0, 4);

/* Data untuk lanskap 3D: satu elemen per hari dalam rentang */
$days3d = [];
for ($i = 0; $i < $range; $i++) {
    $d = date("Y-m-d", strtotime($from . " +$i days"));
    $has = isset($daily[$d]);
    $days3d[] = [
        "s" => $has ? round($daily[$d], 2) : null,
        "w" => (int) date("N", strtotime($d)) - 1,
        "t" => date_id($d, true) . ": " . ($has ? score_label($daily[$d]) . " (" . number_format($daily[$d], 1) . ")" : "belum ada catatan"),
    ];
}

/* Kalender bulanan */
$ym = (string) ($_GET["m"] ?? date("Y-m"));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
    $ym = date("Y-m");
}
$monthStart = $ym . "-01";
$monthEnd = date("Y-m-t", strtotime($monthStart));
$monthEntries = db_all(
    "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC, id ASC",
    "iss",
    [$userId, $monthStart, $monthEnd]
);
$monthDaily = daily_scores($monthEntries);
$lastPerDay = [];
foreach ($monthEntries as $e) {
    $lastPerDay[$e["diary_date"]] = $e["mood"];
}
$calDays = [];
foreach ($monthDaily as $d => $s) {
    $calDays[$d] = ["emoji" => $lastPerDay[$d], "score" => $s];
}
$prevYm = date("Y-m", strtotime($monthStart . " -1 month"));
$nextYm = date("Y-m", strtotime($monthStart . " +1 month"));
$isCurrentMonth = $ym >= date("Y-m");

page_header("Statistik", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("stats"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Statistik mood</h1>
            <p class="muted">Lihat pola perasaanmu dari waktu ke waktu.</p>
        </div>
        <nav class="segmented" aria-label="Rentang waktu">
            <?php foreach ($ranges as $r => $label): ?>
                <a href="/statistics.php?range=<?= $r ?>&m=<?= h($ym) ?>" <?= $r === $range ? 'aria-current="true"' : "" ?>><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <?php if ($total === 0): ?>
        <section class="card">
            <div class="empty">
                <p>Belum ada catatan dalam <?= h($ranges[$range]) ?> terakhir, jadi belum ada yang bisa ditampilkan.</p>
                <a class="btn" href="/diary.php">Catat mood</a>
            </div>
        </section>
    <?php else: ?>

    <section class="grid-3">
        <div class="stat-card" data-tilt="6">
            <span class="muted">Mood rata-rata</span>
            <span class="number"><?= h(score_to_emoji($avg)) ?> <small><?= h(score_label($avg)) ?></small></span>
            <span class="stat-note"><?= number_format($avg, 1) ?> dari 5</span>
        </div>
        <div class="stat-card" data-tilt="6">
            <span class="muted">Hari tercatat</span>
            <span class="number"><?= $activeDays ?> <small>dari <?= $range ?></small></span>
            <span class="stat-note"><?= $total ?> catatan</span>
        </div>
        <div class="stat-card" data-tilt="6">
            <span class="muted">Intensitas rata-rata</span>
            <span class="number"><?= number_format($avgIntensity, 1) ?> <small>dari 5</small></span>
            <span class="stat-note"><?= h(INTENSITY_LABELS[(int) round($avgIntensity)]) ?></span>
        </div>
    </section>

    <section class="card">
        <h2>Tren mood</h2>
        <p class="muted">Rata-rata mood per hari, <?= h(short_date($from)) ?> sampai <?= h(short_date($to)) ?>.</p>
        <div class="chart-wrap"><?= svg_trend($daily, $from, $to) ?></div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2>Lanskap mood 3D</h2>
                <p class="muted">Setiap batang adalah satu hari, makin tinggi makin baik. Baris mewakili minggu.</p>
            </div>
            <button class="btn btn-outline btn-small" type="button" data-3d-toggle aria-expanded="false" aria-controls="landscape-panel">Tampilkan 3D</button>
        </div>
        <div id="landscape-panel" hidden data-days='<?= h(json_encode($days3d, JSON_UNESCAPED_UNICODE)) ?>'>
            <div id="landscape-3d" class="landscape-3d" role="img" aria-label="Lanskap 3D mood harian. Ringkasan yang sama tersedia pada grafik tren di atas."></div>
            <p class="landscape-caption muted" id="landscape-caption" aria-live="polite">Seret untuk memutar. Arahkan kursor ke batang untuk melihat harinya.</p>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <h2>Mood yang paling sering</h2>
            <?php foreach ($dist as $emoji => $count): ?>
                <div class="bar-row">
                    <span class="bar-face" title="<?= h(mood_label($emoji)) ?>"><?= h($emoji) ?></span>
                    <div class="bar-bg"><div class="bar-fill" style="width: <?= round($count / $maxDist * 100) ?>%"></div></div>
                    <strong><?= $count ?> <small class="muted">(<?= round($count / $total * 100) ?>%)</small></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Hari dalam seminggu</h2>
            <p class="muted">Rata-rata mood per hari.</p>
            <?= html_columns($weekdayItems, 5) ?>
        </div>
    </section>

    <section class="card">
        <h2>Aktivitas dan mood</h2>
        <?php if (!$bestTags && !$lowTags): ?>
            <p class="muted">
                Belum cukup data. Tandai aktivitas di catatanmu. Setelah suatu aktivitas muncul minimal 3 kali,
                kamu bisa melihat apakah mood-mu cenderung naik atau turun saat melakukannya.
            </p>
        <?php else: ?>
            <p class="muted">Dibandingkan rata-rata mood-mu (<?= number_format($avg, 1) ?>). Ini hanya pola, bukan sebab-akibat.</p>
            <div class="grid-2">
                <div>
                    <h3>Mood cenderung lebih baik saat</h3>
                    <?php if (!$bestTags): ?><p class="muted">Belum ada yang menonjol.</p><?php endif; ?>
                    <ul class="tag-list">
                        <?php foreach ($bestTags as $t): ?>
                            <li><span><?= h($t["name"]) ?></span><span class="delta up">+<?= number_format($t["diff"], 1) ?> <small>(<?= $t["n"] ?>×)</small></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3>Mood cenderung lebih rendah saat</h3>
                    <?php if (!$lowTags): ?><p class="muted">Belum ada yang menonjol.</p><?php endif; ?>
                    <ul class="tag-list">
                        <?php foreach ($lowTags as $t): ?>
                            <li><span><?= h($t["name"]) ?></span><span class="delta down"><?= number_format($t["diff"], 1) ?> <small>(<?= $t["n"] ?>×)</small></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>

    <section class="card">
        <div class="section-head">
            <h2>Kalender <?= h(month_id($ym)) ?></h2>
            <div class="header-actions">
                <a class="btn btn-outline btn-small" href="/setahun.php">Lihat setahun</a>
                <a class="btn btn-outline btn-small" href="/statistics.php?range=<?= $range ?>&m=<?= h($prevYm) ?>" aria-label="Bulan sebelumnya">←</a>
                <?php if (!$isCurrentMonth): ?>
                    <a class="btn btn-outline btn-small" href="/statistics.php?range=<?= $range ?>&m=<?= h($nextYm) ?>" aria-label="Bulan berikutnya">→</a>
                <?php endif; ?>
            </div>
        </div>
        <?= html_calendar($ym, $calDays) ?>
        <div class="legend" aria-hidden="true">
            <span>Berat</span>
            <?php for ($i = 1; $i <= 5; $i++): ?><i class="legend-swatch" data-score="<?= $i ?>"></i><?php endfor; ?>
            <span>Baik</span>
        </div>
    </section>

</div>
<?php page_footer();
