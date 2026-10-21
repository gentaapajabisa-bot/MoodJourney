<?php
$user = require_member();
$userId = (int) $user["id"];

$j = journey_load($userId);
$stage = $j["stage"];
$meta = BUTTERFLY_STAGES[$j["display"]];
$stageNames = array_keys(BUTTERFLY_STAGES);
$moodLabel = $j["mood"] !== null ? mood_label($j["mood"]) : null;

/* Filosofi per tahap, berurutan sesuai BUTTERFLY_STAGES (telur, ulat, kepompong, kupu-kupu muda, kupu-kupu dewasa) */
$philosophy = [
    [
        "meaning" => "Awal yang kecil tetap layak dirayakan.",
        "why"     => "Telur terlihat diam, padahal di dalamnya sudah ada kehidupan. Begitu juga langkah pertamamu: satu catatan mood sudah cukup untuk memulai, tanpa harus langsung \"baik-baik saja\".",
    ],
    [
        "meaning" => "Tumbuh itu lapar, berantakan, dan butuh waktu.",
        "why"     => "Ulat makan terus dan tubuhnya berubah sedikit demi sedikit. Ini tahap membiasakan diri: mengenali perasaan lewat catatan harian, termasuk hari-hari yang terasa berat.",
    ],
    [
        "meaning" => "Berhenti sejenak bukan berarti mundur.",
        "why"     => "Di dalam kepompong, ulat tampak tidak melakukan apa-apa, padahal tubuhnya sedang disusun ulang. Kami memilih kepompong sebagai tempat istirahat saat kamu absen, supaya jeda terasa aman dan bukan hukuman.",
    ],
    [
        "meaning" => "Sayap baru butuh waktu untuk menguat.",
        "why"     => "Kupu-kupu yang baru keluar harus menunggu sayapnya mengering sebelum bisa terbang. Pada tahap ini kebiasaanmu mulai terbentuk, dan kamu mulai melihat pola dari perasaanmu sendiri.",
    ],
    [
        "meaning" => "Berubah bukan berarti menjadi orang lain, tetapi menjadi lebih utuh.",
        "why"     => "Kupu-kupu dewasa membawa semua tahap sebelumnya. Kamu tidak harus selalu bahagia untuk sampai di sini; kamu sampai karena tetap mau jujur pada dirimu sendiri.",
    ],
];

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
            <p class="journey-demo"><a class="btn btn-outline" href="/bagikan.php">🦋 Bagikan perjalananku</a> <button type="button" class="link-btn" data-stageup-open="stage-up-demo">✨ Lihat animasi naik tahap</button></p>
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

    <section class="card" id="filosofi">
        <h2>Filosofi di balik kupu-kupu 🦋</h2>
        <p class="philosophy-lead">Metamorfosis adalah perubahan total: ulat yang merayap menjadi makhluk bersayap. Prosesnya tidak instan, tidak bisa dipercepat, dan melewati fase yang tampak seperti diam. Kami memakai kupu-kupu sebagai teman perjalananmu karena kehidupan emosi kita bekerja serupa.</p>
        <blockquote class="philosophy-quote">Perubahan yang bertahan jarang datang dari satu lompatan besar. Ia datang dari hal kecil yang diulang dengan sabar.</blockquote>

        <h3>Mengapa kupu-kupu?</h3>
        <ol class="philosophy-reasons">
            <li><strong>Tumbuh itu bertahap.</strong> Tidak ada ulat yang menjadi kupu-kupu dalam semalam. Mencatat mood sedikit demi sedikit juga begitu: manfaatnya terasa setelah kamu konsisten, bukan setelah sekali mencoba.</li>
            <li><strong>Istirahat adalah bagian dari proses.</strong> Fase kepompong mengajarkan bahwa jeda itu wajar. Karena itu, hari yang terlewat tidak menghapus perjalananmu, dan kupu-kupu hanya beristirahat sampai kamu kembali.</li>
            <li><strong>Setiap warna sayap itu valid.</strong> Warna sayapmu mengikuti mood yang paling sering kamu catat. Tidak ada warna yang lebih baik; semuanya bagian dari dirimu.</li>
            <li><strong>Kerentanan mendahului keindahan.</strong> Kupu-kupu melewati fase paling rapuh sebelum bisa terbang. Mengakui perasaan yang sulit adalah bagian dari tumbuh, bukan tanda kamu gagal.</li>
        </ol>

        <h3>Makna tiap tahap</h3>
        <ul class="philosophy-list">
            <?php foreach ($stageNames as $i => $key):
                $ph = $philosophy[$i] ?? null;
                if ($ph === null) { continue; }
            ?>
                <li>
                    <span class="philosophy-art"><?= butterfly_svg($key, $j["colors"], ["animate" => false, "label" => BUTTERFLY_STAGES[$key]["label"]]) ?></span>
                    <div>
                        <h3><?= h(BUTTERFLY_STAGES[$key]["label"]) ?></h3>
                        <p class="p-meaning"><?= h($ph["meaning"]) ?></p>
                        <p class="p-why"><?= h($ph["why"]) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
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
