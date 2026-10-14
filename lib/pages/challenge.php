<?php
$user = require_member();
$userId = (int) $user["id"];

challenge_sync($userId);
$c = challenge_current($userId);
$def = $c["def"];
$history = challenge_history($userId);
$pct = $c["target"] > 0 ? (int) round($c["progress"] / $c["target"] * 100) : 0;

page_header("Tantangan", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("challenge"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Tantangan minggu ini</h1>
            <p class="muted">Satu tantangan ringan tiap minggu, berganti setiap Senin. Tidak ada hukuman kalau terlewat.</p>
        </div>
    </section>

    <section class="card challenge-hero <?= $c["done"] ? "is-done" : "" ?>">
        <div class="challenge-icon" aria-hidden="true"><?= $def["icon"] ?></div>
        <div class="challenge-body">
            <h2><?= h($def["title"]) ?></h2>
            <p class="muted"><?= h($def["text"]) ?></p>
            <span class="onboard-bar" aria-hidden="true"><i style="width: <?= $pct ?>%"></i></span>
            <p class="challenge-meta">
                <strong><?= $c["progress"] ?></strong> dari <?= $c["target"] ?>
                <?php if ($c["done"]): ?> · <span class="challenge-ok">Selesai! 🎉</span>
                <?php else: ?> · <span class="muted"><?= $c["days_left"] === 0 ? "hari terakhir" : $c["days_left"] . " hari lagi" ?></span><?php endif; ?>
            </p>
            <?php if (!$c["done"]): ?><a class="btn btn-small" href="/diary.php">Catat sekarang</a><?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h2>Lencana tantanganmu</h2>
        <?php if (!$history): ?>
            <p class="muted">Belum ada tantangan yang selesai. Lencana pertamamu menunggu.</p>
        <?php else: ?>
            <ul class="challenge-badges">
                <?php foreach ($history as $h): ?>
                    <li>
                        <span class="challenge-badge" aria-hidden="true"><?= $h["icon"] ?></span>
                        <strong><?= h($h["title"]) ?></strong>
                        <small class="muted">Minggu <?= h(date_id($h["week_start"])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>
<?php page_footer();
