<?php
$user = require_member();
$userId = (int) $user["id"];

$j = journey_load($userId);
$stage = $j["stage"];
$meta = BUTTERFLY_STAGES[$j["display"]];
$stageNames = array_keys(BUTTERFLY_STAGES);
$moodLabel = $j["mood"] !== null ? mood_label($j["mood"]) : null;

page_header("Metamorfosis", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("butterfly"); ?>

    <section class="page-title">
        <h1>Metamorfosis</h1>
        <p class="muted">Setiap hari kamu mencatat, kupu-kupumu tumbuh sedikit. Tidak ada yang buru-buru, dan terlewat sesekali itu manusiawi.</p>
    </section>

    <section class="card journey-hero" data-stage="<?= h($j["display"]) ?>" style="--wing-1: <?= h($j["colors"][0]) ?>; --wing-2: <?= h($j["colors"][1]) ?>">
        <div class="journey-art">
            <?= butterfly_svg($j["display"], $j["colors"], ["resting" => $j["resting"], "label" => $meta["label"]]) ?>
        </div>

        <div class="journey-info">
            <span class="journey-chip">Tahap <?= $j["stage_index"] + 1 ?> dari <?= count($stageNames) ?></span>
            <h2><?= h($j["resting"] ? "Kepompong (istirahat)" : $meta["label"]) ?></h2>
            <p class="journey-tagline"><?= h($j["resting"] ? "Beristirahat sebentar" : $meta["tagline"]) ?></p>
            <p class="journey-text">
                <?php if ($j["resting"]): ?>
                    Kupu-kupumu sedang beristirahat di kepompong. Perjalananmu tidak hilang: kamu masih punya <?= $j["days"] ?> hari. Catat mood hari ini dan dia akan keluar lagi.
                <?php elseif ($j["total_logged"] === 0): ?>
                    Catat mood pertamamu untuk menetaskan telur ini.
                <?php else: ?>
                    <?= h($meta["text"]) ?>
                <?php endif; ?>
            </p>

            <div class="journey-count">
                <strong><?= $j["days"] ?></strong> <span>hari perjalanan</span>
            </div>

            <?php if ($j["next"] !== null): ?>
                <div class="bar-bg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $j["progress"] ?>"
                     aria-label="Menuju <?= h(BUTTERFLY_STAGES[$j["next"]]["label"]) ?>">
                    <div class="bar-fill" style="width: <?= $j["progress"] ?>%"></div>
                </div>
                <p class="muted journey-next">
                    <?= $j["days_left"] ?> hari lagi menuju <strong><?= h(BUTTERFLY_STAGES[$j["next"]]["label"]) ?></strong>
                </p>
            <?php else: ?>
                <p class="muted journey-next">Kamu sudah di tahap akhir. Tinggal terbang dan menikmati perjalanannya.</p>
            <?php endif; ?>

            <?php if ($j["grace_available"] && $j["total_logged"] > 0): ?>
                <p class="journey-note">🍃 Minggu ini kamu masih punya 1 hari angin: satu hari terlewat tidak akan mengurangi apa pun.</p>
            <?php endif; ?>

            <?php if (!$j["today_done"]): ?>
                <a class="btn" href="/diary.php"><?= $j["resting"] ? "Bangunkan kupu-kupumu" : "Catat mood hari ini" ?></a>
            <?php else: ?>
                <p class="journey-done">✓ Hari ini sudah tercatat. Kupu-kupumu senang.</p>
            <?php endif; ?>
            <p class="journey-demo"><button type="button" class="link-btn" data-stageup-open="stage-up-demo">✨ Lihat animasi naik tahap</button></p>
        </div>
    </section>

    <section class="card">
        <h2>Lima tahap</h2>
        <ol class="stage-track">
            <?php foreach (BUTTERFLY_STAGES as $key => $s):
                $idx = array_search($key, $stageNames, true);
                $state = $idx < $j["stage_index"] ? "done" : ($idx === $j["stage_index"] ? "current" : "locked");
            ?>
                <li class="stage-item is-<?= $state ?>" <?= $state === "current" ? 'aria-current="step"' : "" ?>>
                    <span class="stage-art"><?= butterfly_svg($key, $j["colors"], ["animate" => false, "label" => $s["label"]]) ?></span>
                    <strong><?= h($s["label"]) ?></strong>
                    <small><?= $s["min"] === 0 ? "Mulai" : $s["min"] . "+ hari" ?></small>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="card">
        <h2>Cara kerjanya</h2>
        <ul class="journey-rules">
            <li><strong>Mencatat = tumbuh.</strong> Setiap hari yang kamu catat menambah satu hari perjalanan. Mood apa pun boleh, tidak ada yang salah.</li>
            <li><strong>Satu hari angin per minggu.</strong> Dalam seminggu (Senin sampai Minggu), satu hari yang terlewat dimaafkan. Perjalananmu tetap utuh.</li>
            <li><strong>Terlewat lagi? Tidak mulai dari nol.</strong> Perjalananmu turun separuh (paling banyak sekali per minggu), dan kupu-kupu beristirahat di kepompong sampai kamu kembali mencatat.</li>
            <li><strong>Warna sayap milikmu sendiri.</strong>
                <?php if ($moodLabel !== null): ?>
                    Saat ini warnanya mengikuti mood yang paling sering kamu catat 14 hari terakhir: <?= h($j["mood"]) ?> <?= h($moodLabel) ?>.
                <?php else: ?>
                    Warnanya akan mengikuti mood yang paling sering kamu catat dalam 14 hari terakhir.
                <?php endif; ?>
            </li>
        </ul>
    </section>

</div>
<?php
$demoTo = $j["stage_index"] >= 1 ? $j["stage"] : "larva";
$demoFrom = $stageNames[array_search($demoTo, $stageNames, true) - 1];
echo stage_up_overlay_html($demoFrom, $demoTo, $j["colors"], false, "stage-up-demo");
page_footer();
