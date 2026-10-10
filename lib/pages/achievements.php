<?php
$user = require_member();
$userId = (int) $user["id"];

// pastikan pencapaian lama (sebelum fitur ini ada) ikut tercatat
check_achievements($userId);

$earnedRows = db_all("SELECT code, earned_at, seen FROM achievements WHERE user_id = ?", "i", [$userId]);
$earned = [];
foreach ($earnedRows as $r) {
    $earned[$r["code"]] = $r;
}
$metrics = achievement_metrics($userId);

$metricUnit = ["entries" => "catatan", "streak" => "hari beruntun", "tags" => "aktivitas", "goals" => "target tercapai"];
$total = count(ACHIEVEMENTS);
$got = count($earned);

page_header("Pencapaian", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("badges"); ?>

    <section class="page-title">
        <h1>Pencapaian</h1>
        <p class="muted"><?= $got ?> dari <?= $total ?> pencapaian diraih. Ini bukan kompetisi, hanya penanda bahwa kamu sudah berjalan sejauh ini.</p>
    </section>

    <section class="badge-grid">
        <?php foreach (ACHIEVEMENTS as $code => [$icon, $title, $desc, $metric, $threshold]):
            $isEarned = isset($earned[$code]);
            $isNew = $isEarned && (int) $earned[$code]["seen"] === 0;
            $have = min($threshold, (int) $metrics[$metric]);
            $pct = round($have / $threshold * 100);
        ?>
            <button type="button" class="badge-card <?= $isEarned ? "earned" : "locked" ?> <?= $isNew ? "is-new" : "" ?>"
                    aria-label="<?= h($title . ($isEarned ? ", sudah diraih" : ", belum diraih") . ". " . $desc) ?>">
                <span class="badge-inner">
                    <span class="badge-face badge-front">
                        <?php if ($isNew): ?><span class="badge-new">Baru</span><?php endif; ?>
                        <span class="badge-icon" aria-hidden="true"><?= h($icon) ?></span>
                        <span class="badge-title"><?= h($title) ?></span>
                    </span>
                    <span class="badge-face badge-back">
                        <span class="badge-desc"><?= h($desc) ?></span>
                        <?php if ($isEarned): ?>
                            <span class="badge-date">Diraih <?= h(date_id($earned[$code]["earned_at"])) ?></span>
                        <?php else: ?>
                            <span class="badge-progress">
                                <span class="bar-bg"><span class="bar-fill" style="width: <?= $pct ?>%; display:block"></span></span>
                                <span class="badge-date"><?= $have ?>/<?= $threshold ?> <?= h($metricUnit[$metric]) ?></span>
                            </span>
                        <?php endif; ?>
                    </span>
                </span>
            </button>
        <?php endforeach; ?>
    </section>

    <p class="muted hint">Sentuh atau arahkan kursor ke kartu untuk melihat detailnya.</p>

</div>
<?php
// tandai sudah dilihat setelah halaman dirender
db_exec("UPDATE achievements SET seen = 1 WHERE user_id = ? AND seen = 0", "i", [$userId]);
page_footer();
