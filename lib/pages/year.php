<?php
$user = require_member();
$userId = (int) $user["id"];

$thisYear = (int) date("Y");
$firstDate = (string) db_value("SELECT MIN(diary_date) FROM diary WHERE user_id = ?", "i", [$userId], "");
$firstYear = $firstDate !== "" ? (int) substr($firstDate, 0, 4) : $thisYear;

$year = (int) ($_GET["y"] ?? $thisYear);
if ($year < $firstYear || $year > $thisYear) {
    $year = $thisYear;
}

$entries = db_all(
    "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC, id ASC",
    "iss",
    [$userId, "$year-01-01", "$year-12-31"]
);

$perDay = [];     // tanggal => jumlah catatan
$lastMood = [];   // tanggal => emoji terakhir
$moodCount = [];  // emoji => jumlah catatan
foreach ($entries as $e) {
    $d = $e["diary_date"];
    $perDay[$d] = ($perDay[$d] ?? 0) + 1;
    $lastMood[$d] = $e["mood"];
    $moodCount[$e["mood"]] = ($moodCount[$e["mood"]] ?? 0) + 1;
}
$scores = daily_scores($entries);
arsort($moodCount);

$daysLogged = count($perDay);
$daysInYear = (int) date("L", mktime(0, 0, 0, 1, 1, $year)) ? 366 : 365;
$elapsed = $year < $thisYear ? $daysInYear : (int) date("z") + 1;
$streaks = compute_streaks(array_keys($perDay));
$topMood = $moodCount ? array_key_first($moodCount) : null;
$yearAvg = $scores ? array_sum($scores) / count($scores) : null;

// rata-rata skor per bulan
$monthAvg = [];
for ($m = 1; $m <= 12; $m++) {
    $prefix = sprintf("%04d-%02d-", $year, $m);
    $vals = [];
    foreach ($scores as $d => $s) {
        if (str_starts_with($d, $prefix)) {
            $vals[] = $s;
        }
    }
    $monthAvg[$m] = $vals ? array_sum($vals) / count($vals) : null;
}

$today = date("Y-m-d");
$monthShort = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];

page_header("Setahun", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("year"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Setahun dalam piksel</h1>
            <p class="muted">Satu kotak untuk setiap hari. Lihat sekilas warna tahunmu.</p>
        </div>
        <div class="header-actions">
            <?php if ($year > $firstYear): ?>
                <a class="btn btn-outline btn-small" href="/setahun.php?y=<?= $year - 1 ?>" aria-label="Tahun sebelumnya">←</a>
            <?php endif; ?>
            <strong class="yip-year"><?= $year ?></strong>
            <?php if ($year < $thisYear): ?>
                <a class="btn btn-outline btn-small" href="/setahun.php?y=<?= $year + 1 ?>" aria-label="Tahun berikutnya">→</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid-3 yip-stats">
        <div class="stat-card">
            <span class="muted">Hari tercatat</span>
            <span class="number"><?= $daysLogged ?> <small>dari <?= $elapsed ?> hari</small></span>
            <span class="stat-note"><?= $elapsed > 0 ? round($daysLogged / $elapsed * 100) : 0 ?>% dari tahun ini</span>
        </div>
        <div class="stat-card">
            <span class="muted">Streak terpanjang</span>
            <span class="number"><?= (int) $streaks["longest"] ?> <small>hari</small></span>
            <span class="stat-note">berturut-turut di <?= $year ?></span>
        </div>
        <div class="stat-card">
            <span class="muted">Mood tersering</span>
            <?php if ($topMood !== null): ?>
                <span class="number"><?= h($topMood) ?> <small><?= h(mood_label($topMood)) ?></small></span>
                <span class="stat-note"><?= (int) $moodCount[$topMood] ?> catatan<?= $yearAvg !== null ? " · rata-rata " . number_format($yearAvg, 1) . " dari 5" : "" ?></span>
            <?php else: ?>
                <span class="number">–</span>
                <span class="stat-note">Belum ada catatan di <?= $year ?></span>
            <?php endif; ?>
        </div>
    </section>

    <section class="card yip-card">
        <div class="section-head">
            <h2>Kalender <?= $year ?></h2>
            <div class="header-actions">
                <nav class="segmented yip-mode" aria-label="Cara pewarnaan">
                    <button type="button" class="yip-mode-btn" data-yip-mode="score" aria-pressed="true">Skor</button>
                    <button type="button" class="yip-mode-btn" data-yip-mode="mood" aria-pressed="false">Mood</button>
                </nav>
                <button type="button" class="btn btn-outline btn-small" id="yip-download">Unduh gambar</button>
            </div>
        </div>

        <div class="yip-scroll">
            <div class="yip" id="yip" data-mode="score" data-year="<?= $year ?>" role="grid" aria-label="Kalender mood setahun <?= $year ?>">
                <?php foreach ($monthShort as $i => $label): ?>
                    <span class="yip-mlabel" style="--m: <?= $i + 1 ?>" aria-hidden="true"><?= $label ?></span>
                <?php endforeach; ?>
                <?php for ($d = 1; $d <= 31; $d++): ?>
                    <span class="yip-dlabel" style="--d: <?= $d ?>" aria-hidden="true"><?= $d % 5 === 0 || $d === 1 ? $d : "" ?></span>
                <?php endfor; ?>

                <?php for ($m = 1; $m <= 12; $m++):
                    $dim = (int) date("t", mktime(0, 0, 0, $m, 1, $year));
                    for ($d = 1; $d <= $dim; $d++):
                        $date = sprintf("%04d-%02d-%02d", $year, $m, $d);
                        $has = isset($perDay[$date]);
                        $future = $date > $today;
                        $score = $has ? (int) round($scores[$date]) : 0;
                        $emoji = $has ? $lastMood[$date] : "";
                        $pal = $has ? butterfly_pastel(BUTTERFLY_PALETTE[$emoji] ?? BUTTERFLY_DEFAULT_COLORS, 0.3) : null;
                        $cls = "yip-cell" . ($has ? " has" : "") . ($future ? " is-future" : "") . ($date === $today ? " is-today" : "");
                        $label = date_id($date, true) . ($has ? ": " . mood_label($emoji) . ", " . $perDay[$date] . " catatan" : ($future ? "" : ": belum ada catatan"));
                ?>
                    <button type="button" class="<?= $cls ?>" role="gridcell"
                            style="--m: <?= $m ?>; --d: <?= $d ?><?= $has ? "; --mc: " . h($pal[0]) : "" ?>"
                            <?= $has ? 'data-score="' . $score . '"' : "" ?>
                            data-date="<?= $date ?>" data-label="<?= h(date_id($date, true)) ?>"
                            data-emoji="<?= h($emoji) ?>" data-mood="<?= h($has ? mood_label($emoji) : "") ?>"
                            data-n="<?= (int) ($perDay[$date] ?? 0) ?>" data-future="<?= $future ? "1" : "0" ?>"
                            aria-label="<?= h($label) ?>" tabindex="-1"></button>
                <?php endfor; endfor; ?>
            </div>
        </div>

        <div class="yip-info" id="yip-info" aria-live="polite">
            <span class="muted">Arahkan kursor atau sentuh sebuah kotak untuk melihat harinya.</span>
        </div>

        <div class="legend yip-legend" aria-hidden="true">
            <span>Berat</span>
            <?php for ($i = 1; $i <= 5; $i++): ?><i class="legend-swatch" data-score="<?= $i ?>"></i><?php endfor; ?>
            <span>Baik</span>
            <i class="legend-swatch yip-legend-empty"></i><span>Belum dicatat</span>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <h2>Mood sepanjang <?= $year ?></h2>
            <?php if (!$moodCount): ?>
                <p class="muted">Belum ada catatan di tahun ini.</p>
            <?php else:
                $max = max($moodCount);
                foreach ($moodCount as $emoji => $n):
                    $pal = butterfly_pastel(BUTTERFLY_PALETTE[$emoji] ?? BUTTERFLY_DEFAULT_COLORS, 0.2);
            ?>
                <div class="yip-bar-row">
                    <span class="yip-bar-label"><?= h($emoji) ?> <?= h(mood_label($emoji)) ?></span>
                    <span class="yip-bar-track"><span class="yip-bar-fill" style="width: <?= round($n / $max * 100) ?>%; background: <?= h($pal[0]) ?>"></span></span>
                    <span class="yip-bar-n"><?= (int) $n ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h2>Rata-rata per bulan</h2>
            <div class="yip-months" role="img" aria-label="Rata-rata skor mood tiap bulan">
                <?php foreach ($monthAvg as $m => $avg): ?>
                    <div class="yip-month">
                        <span class="yip-month-bar">
                            <?php if ($avg !== null): ?>
                                <i style="height: <?= round($avg / 5 * 100) ?>%" data-score="<?= (int) round($avg) ?>" title="<?= h($monthShort[$m - 1] . ": " . number_format($avg, 1)) ?>"></i>
                            <?php endif; ?>
                        </span>
                        <small><?= h($monthShort[$m - 1]) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="muted card-foot">Tinggi batang menunjukkan rata-rata skor 1 (berat) sampai 5 (baik). Hanya kecenderungan, bukan penilaian.</p>
        </div>
    </section>

</div>
<?php page_footer(["/assets/js/year.js"]);
