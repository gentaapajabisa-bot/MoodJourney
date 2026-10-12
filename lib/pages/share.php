<?php
$user = require_member();
$userId = (int) $user["id"];

$journey = journey_load($userId);
$dates = array_column(
    db_all("SELECT DISTINCT diary_date FROM diary WHERE user_id = ? ORDER BY diary_date DESC LIMIT 3700", "i", [$userId]),
    "diary_date"
);
$streaks = compute_streaks($dates);
$stage = $journey["display"];
$label = BUTTERFLY_STAGES[$stage]["label"];
$firstName = trim(explode(" ", trim((string) $user["name"]))[0]);

page_header("Bagikan", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("butterfly"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Bagikan perjalananmu</h1>
            <p class="muted">Gambar berisi kupu-kupu dan angkamu saja. Isi catatan, mood, dan fotomu tidak ikut.</p>
        </div>
    </section>

    <section class="share-layout">
        <div class="share-stage">
            <canvas id="share-canvas" width="1080" height="1350"
                    data-days="<?= (int) $journey["days"] ?>"
                    data-stage="<?= h($stage) ?>"
                    data-stage-label="<?= h($label) ?>"
                    data-logged="<?= (int) $journey["total_logged"] ?>"
                    data-longest="<?= (int) $streaks["longest"] ?>"
                    data-name="<?= h($firstName) ?>"
                    data-c1="<?= h($journey["colors"][0]) ?>"
                    data-c2="<?= h($journey["colors"][1]) ?>"
                    role="img" aria-label="Pratinjau kartu: <?= h($label) ?>, <?= (int) $journey["days"] ?> hari perjalanan"></canvas>
        </div>

        <div class="card share-controls">
            <h2>Atur kartumu</h2>

            <fieldset class="share-field">
                <legend>Warna latar</legend>
                <div class="share-themes" role="radiogroup" aria-label="Warna latar">
                    <button type="button" class="share-theme is-active" data-theme="lilac" role="radio" aria-checked="true" aria-label="Lilac"><i></i></button>
                    <button type="button" class="share-theme" data-theme="mint" role="radio" aria-checked="false" aria-label="Mint"><i></i></button>
                    <button type="button" class="share-theme" data-theme="peach" role="radio" aria-checked="false" aria-label="Peach"><i></i></button>
                    <button type="button" class="share-theme" data-theme="night" role="radio" aria-checked="false" aria-label="Malam"><i></i></button>
                </div>
            </fieldset>

            <label class="share-check"><input type="checkbox" id="share-name"> Tampilkan nama depanku (<?= h($firstName) ?>)</label>
            <label class="share-check"><input type="checkbox" id="share-stats" checked> Tampilkan jumlah hari tercatat dan streak terpanjang</label>

            <div class="share-actions">
                <button type="button" class="btn" id="share-send">Bagikan</button>
                <button type="button" class="btn btn-outline" id="share-download">Unduh gambar</button>
            </div>
            <p class="muted share-note" id="share-note" role="status"></p>
            <p class="muted card-foot">Gambar dibuat di perangkatmu dan tidak diunggah ke mana pun sebelum kamu membagikannya.</p>
        </div>
    </section>

</div>
<?php page_footer(["/assets/js/share.js"]);
