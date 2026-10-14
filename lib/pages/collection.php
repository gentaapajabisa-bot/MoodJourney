<?php
$user = require_member();
$userId = (int) $user["id"];

$journey = journey_load($userId);
collection_record($userId, $journey); // data lama: masukkan kupu-kupu yang sudah ada
$items = collection_all($userId);

$owned = [];
foreach ($items as $it) {
    if ($it["stage"] === "adult") {
        $owned[(string) $it["mood"]] = true;
    }
}
$species = BUTTERFLY_SPECIES;

page_header("Koleksi", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("collection"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Koleksi kupu-kupu</h1>
            <p class="muted">Setiap kupu-kupu dewasa membawa warna mood yang paling sering kamu catat saat itu. Ada <?= count($species) ?> jenis untuk dikumpulkan.</p>
        </div>
        <strong class="yip-year"><?= count($owned) ?> / <?= count($species) ?></strong>
    </section>

    <section class="card">
        <h2>Jenis kupu-kupu</h2>
        <ul class="collection-grid">
            <?php foreach ($species as $emoji => $name):
                $has = isset($owned[$emoji]);
                $pal = BUTTERFLY_PALETTE[$emoji] ?? BUTTERFLY_DEFAULT_COLORS; ?>
                <li class="collection-item <?= $has ? "is-owned" : "is-locked" ?>">
                    <span class="collection-art">
                        <?= butterfly_svg("adult", $has ? $pal : ["#c9c6d6", "#aaa6bd"], ["animate" => false, "label" => $name]) ?>
                    </span>
                    <strong><?= $has ? h($name) : "Belum ditemukan" ?></strong>
                    <small class="muted"><?= $has ? h(mood_label($emoji)) . " " . h($emoji) : "Dapat saat dewasa dengan mood " . h(mood_label($emoji)) . " " . h($emoji) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h2>Riwayat koleksimu</h2>
        <?php if (!$items): ?>
            <p class="muted">Kupu-kupu pertama masuk koleksi saat perjalananmu mencapai tahap Kupu-kupu muda (14 hari).</p>
        <?php else: ?>
            <ul class="adm-list">
                <?php foreach ($items as $it):
                    $label = $it["stage"] === "adult" ? "Kupu-kupu dewasa" : "Kupu-kupu muda"; ?>
                    <li>
                        <span><?= h(species_name($it["mood"])) ?> · <?= h($label) ?>
                            <small class="muted"><?= h(date_id($it["earned_on"])) ?></small></span>
                        <span class="collection-dot" style="background: linear-gradient(135deg, <?= h($it["c1"]) ?>, <?= h($it["c2"]) ?>)" aria-hidden="true"></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>
<?php page_footer();
