<?php
$user = require_member();
$userId = (int) $user["id"];

$today = date("Y-m-d");
$thisWeek = week_start($today);

$param = (string) ($_GET["w"] ?? "");
$ws = week_start(valid_date($param) ? $param : $today);
if ($ws > $thisWeek) {
    $ws = $thisWeek;
}
$we = week_end($ws);
$prevWs = date("Y-m-d", strtotime($ws . " -7 days"));
$nextWs = date("Y-m-d", strtotime($ws . " +7 days"));
$isCurrent = $ws === $thisWeek;

$fetch = fn(string $from, string $to) => db_all(
    "SELECT mood, intensity, tags, activity, diary_date FROM diary
     WHERE user_id = ? AND diary_date BETWEEN ? AND ? ORDER BY diary_date ASC, id ASC",
    "iss",
    [$userId, $from, $to]
);

$entries = $fetch($ws, $we);
$prevEntries = $fetch($prevWs, week_end($prevWs));

$daily = daily_scores($entries);
$prevDaily = daily_scores($prevEntries);
$n = count($entries);
$activeDays = count($daily);

$avg = $n ? array_sum(array_map(fn($e) => mood_score($e["mood"]), $entries)) / $n : null;
$prevN = count($prevEntries);
$prevAvg = $prevN ? array_sum(array_map(fn($e) => mood_score($e["mood"]), $prevEntries)) / $prevN : null;

$lastMood = [];
foreach ($entries as $e) {
    $lastMood[$e["diary_date"]] = $e["mood"];
}

/* Hari terbaik dan terberat (hanya bermakna bila ada 2 hari atau lebih) */
$bestDay = null;
$hardDay = null;
if ($activeDays >= 2) {
    $max = max($daily);
    $min = min($daily);
    if ($max > $min) {
        $bestDay = array_search($max, $daily, true);
        $hardDay = array_search($min, $daily, true);
    }
}

/* Mood paling sering */
$moodCount = [];
foreach ($entries as $e) {
    $moodCount[$e["mood"]] = ($moodCount[$e["mood"]] ?? 0) + 1;
}
arsort($moodCount);
$topMood = $moodCount ? array_key_first($moodCount) : null;

/* Aktivitas paling sering */
$tagCount = [];
foreach ($entries as $e) {
    foreach (tags_to_array($e["tags"]) as $t) {
        $tagCount[TAGS[$t]] = ($tagCount[TAGS[$t]] ?? 0) + 1;
    }
}
arsort($tagCount);
$topTags = array_slice($tagCount, 0, 4, true);

/* Aktivitas pada hari terbaik */
$bestDayTags = [];
if ($bestDay) {
    foreach ($entries as $e) {
        if ($e["diary_date"] === $bestDay) {
            foreach (tags_to_array($e["tags"]) as $t) {
                $bestDayTags[TAGS[$t]] = true;
            }
        }
    }
}

/* Kalimat ringkasan */
$delta = ($avg !== null && $prevAvg !== null) ? $avg - $prevAvg : null;
$summary = "";
if ($n === 0) {
    $summary = $isCurrent ? "Belum ada catatan minggu ini. Cukup satu catatan singkat untuk memulai." : "Tidak ada catatan pada minggu ini.";
} else {
    $summary = ($isCurrent ? "Sejauh ini minggu ini" : "Minggu ini") . " kamu mencatat " . $n . " kali di " . $activeDays . " hari. "
        . "Mood-mu rata-rata terasa " . mb_strtolower(score_label($avg)) . " (" . number_format($avg, 1) . " dari 5).";
    if ($delta !== null) {
        if ($delta >= 0.3) {
            $summary .= " Itu naik " . number_format($delta, 1) . " dibanding minggu sebelumnya.";
        } elseif ($delta <= -0.3) {
            $summary .= " Itu turun " . number_format(abs($delta), 1) . " dibanding minggu sebelumnya.";
        } else {
            $summary .= " Itu hampir sama dengan minggu sebelumnya.";
        }
    }
}

$goals = goals_with_progress($userId, $ws);
$showSupport = $avg !== null && $avg <= 2.0 && $activeDays >= 3;

page_header("Ringkasan mingguan", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("weekly"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Ringkasan mingguan</h1>
            <p class="muted"><?= h(week_label($ws)) ?><?= $isCurrent ? " · minggu ini" : "" ?></p>
        </div>
        <div class="header-actions">
            <a class="btn btn-outline btn-small" href="/ringkasan.php?w=<?= h($prevWs) ?>" aria-label="Minggu sebelumnya">← Sebelumnya</a>
            <?php if (!$isCurrent): ?>
                <a class="btn btn-outline btn-small" href="/ringkasan.php?w=<?= h($nextWs) ?>" aria-label="Minggu berikutnya">Berikutnya →</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="card summary-card">
        <p class="summary-text"><?= h($summary) ?></p>
        <?php if ($n === 0 && $isCurrent): ?>
            <a class="btn" href="/diary.php">Catat mood</a>
        <?php endif; ?>
    </section>

    <?php if ($showSupport): ?>
        <aside class="support-card" aria-label="Dukungan">
            <p class="support-title">Minggu ini terasa cukup berat.</p>
            <p>Kamu tidak harus melewatinya sendirian. Bicara dengan orang yang kamu percaya atau tenaga profesional bisa sangat membantu.</p>
            <a class="btn btn-small" href="/bantuan.php">Lihat layanan bantuan</a>
        </aside>
    <?php endif; ?>

    <?php if ($n > 0): ?>

    <section class="grid-3">
        <div class="stat-card" data-tilt="6">
            <span class="muted">Mood rata-rata</span>
            <span class="number"><?= h(score_to_emoji($avg)) ?> <small><?= h(score_label($avg)) ?></small></span>
            <span class="stat-note">
                <?php if ($delta !== null): ?>
                    <?= $delta > 0.05 ? "▲" : ($delta < -0.05 ? "▼" : "•") ?> <?= ($delta >= 0 ? "+" : "") . number_format($delta, 1) ?> dari minggu lalu
                <?php else: ?>
                    Belum ada pembanding
                <?php endif; ?>
            </span>
        </div>
        <div class="stat-card" data-tilt="6">
            <span class="muted">Hari tercatat</span>
            <span class="number"><?= $activeDays ?> <small>dari 7</small></span>
            <span class="stat-note"><?= $n ?> catatan</span>
        </div>
        <div class="stat-card" data-tilt="6">
            <span class="muted">Mood paling sering</span>
            <span class="number"><?= h($topMood) ?> <small><?= h(mood_label($topMood)) ?></small></span>
            <span class="stat-note"><?= (int) $moodCount[$topMood] ?> kali</span>
        </div>
    </section>

    <section class="card">
        <h2>Hari demi hari</h2>
        <div class="week-strip">
            <?php for ($i = 0; $i < 7; $i++):
                $d = date("Y-m-d", strtotime($ws . " +$i days"));
                $has = isset($lastMood[$d]);
                $score = $has ? (int) round($daily[$d]) : 0;
                $future = $d > $today;
            ?>
                <a class="week-day <?= $has ? "filled" : "" ?>" <?= $has ? 'data-score="' . $score . '"' : "" ?>
                   href="<?= $has ? "/history.php?from=$d&to=$d" : ($future ? "#" : "/diary.php?date=$d") ?>"
                   title="<?= h(date_id($d, true)) ?>" <?= $future ? 'aria-disabled="true" tabindex="-1"' : "" ?>>
                    <span class="week-name"><?= h(mb_substr(DAYS_ID[$i + 1], 0, 3)) ?></span>
                    <span class="week-emoji"><?= $has ? h($lastMood[$d]) : "·" ?></span>
                    <span class="week-num"><?= (int) date("j", strtotime($d)) ?></span>
                </a>
            <?php endfor; ?>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <h2>Sorotan</h2>
            <?php if ($bestDay): ?>
                <p><strong>Hari terbaik:</strong> <?= h(date_id($bestDay, true)) ?> (<?= h(score_label($daily[$bestDay])) ?>)</p>
                <?php if ($bestDayTags): ?>
                    <p class="muted">Hari itu kamu mencatat: <?= h(implode(", ", array_keys($bestDayTags))) ?>.</p>
                <?php endif; ?>
                <p><strong>Hari terberat:</strong> <?= h(date_id($hardDay, true)) ?> (<?= h(score_label($daily[$hardDay])) ?>)</p>
            <?php else: ?>
                <p class="muted">Sorotan muncul kalau ada catatan di dua hari berbeda atau lebih dengan mood yang berbeda.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Aktivitas minggu ini</h2>
            <?php if (!$topTags): ?>
                <p class="muted">Tandai aktivitas di catatanmu untuk melihat apa yang paling sering kamu lakukan.</p>
            <?php else: ?>
                <ul class="tag-list">
                    <?php foreach ($topTags as $name => $count): ?>
                        <li><span><?= h($name) ?></span><strong><?= (int) $count ?>×</strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <?php endif; ?>

    <?php if ($goals): ?>
    <section class="card">
        <div class="section-head">
            <h2>Target minggu ini</h2>
            <a class="btn btn-outline btn-small" href="/target.php">Kelola target</a>
        </div>
        <?php foreach ($goals as $g): $pct = min(100, round($g["done"] / max(1, $g["target"]) * 100)); ?>
            <div class="goal-row">
                <div class="goal-head">
                    <strong><?= h($g["label"]) ?></strong>
                    <span class="<?= $g["met"] ? "delta up" : "muted" ?>"><?= $g["met"] ? "Tercapai ✓ " : "" ?><?= (int) $g["done"] ?>/<?= (int) $g["target"] ?> hari</span>
                </div>
                <div class="bar-bg" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $g["target"] ?>" aria-valuenow="<?= (int) $g["done"] ?>" aria-label="<?= h($g["label"]) ?>">
                    <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

</div>
<?php page_footer();
