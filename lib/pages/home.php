<?php
$user = current_user();
if ($user) {
    redirect(home_for($user));
}
page_header("Mood Diary", ["nav" => "public", "desc" => "Ruang sederhana untuk mencatat perasaan, aktivitas, dan cerita harianmu. Kupu-kupu kecil tumbuh bersama setiap catatanmu."]);

// kupu-kupu di adegan hero: [kunci mood, kiri %, atas %, ukuran px, kedalaman parallax, animasi]
$flies = [
    ["🥰", 2,  3,  76, 0.9, "a"],
    ["😄", 45, 9,  64, 0.6, "b"],
    ["🙂", 50, 72, 82, 1.0, "c"],
    ["😴", 93, 10, 90, 0.8, "b"],
];
mt_srand(7);
?>
<section class="hero hero-scene" data-parallax-root>

    <div class="scene" aria-hidden="true">
        <div class="sc-l" data-depth="0.25">
            <span class="sc-cloud" style="left: 6%; top: 12%"></span>
            <span class="sc-cloud sc-cloud-s" style="left: 52%; top: 6%"></span>
            <span class="sc-cloud" style="left: 78%; top: 40%"></span>
        </div>

        <?php foreach ($flies as $f): [$mood, $left, $top, $size, $depth, $anim] = $f; ?>
            <div class="sc-l sc-fly-wrap" data-depth="<?= $depth ?>" style="left: <?= $left ?>%; top: <?= $top ?>%; width: <?= $size ?>px">
                <div class="sc-fly sc-fly-<?= $anim ?>"><?= butterfly_svg("adult", butterfly_pastel(BUTTERFLY_PALETTE[$mood], 0.22), ["label" => ""]) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="sc-l sc-hills" data-depth="0.15">
            <svg viewBox="0 0 1440 240" preserveAspectRatio="none">
                <path class="hill-back" d="M0 120 C 220 40, 420 40, 640 110 C 860 180, 1080 60, 1440 100 L1440 240 L0 240 Z"/>
            </svg>
        </div>
        <div class="sc-l sc-hills sc-hills-front" data-depth="0.35">
            <svg viewBox="0 0 1440 200" preserveAspectRatio="none">
                <path class="hill-front" d="M0 110 C 260 170, 520 40, 820 100 C 1080 150, 1250 70, 1440 120 L1440 200 L0 200 Z"/>
            </svg>
        </div>
        <div class="sc-l sc-flowers" data-depth="0.5">
            <?php for ($i = 0; $i < 16; $i++): ?>
                <span class="sc-flower" style="left: <?= round(($i + 0.5) * 100 / 16 + mt_rand(-20, 20) / 10, 1) ?>%; --h: <?= mt_rand(70, 120) ?>%; animation-delay: -<?= mt_rand(0, 40) / 10 ?>s">
                    <?= scene_flower_svg($i + mt_rand(0, 4)) ?>
                </span>
            <?php endfor; ?>
        </div>
    </div>

    <div class="hero-content">
        <p class="eyebrow">Mood diary pribadi</p>

        <h1>
            Kenali dirimu,<br>
            lewat setiap <span>cerita.</span>
        </h1>

        <p class="hero-description">
            MoodJourney adalah ruang sederhana untuk mencatat perasaan, aktivitas,
            dan cerita dari setiap harimu. Setiap catatan membuat kupu-kupu kecilmu tumbuh,
            dari telur sampai terbang bebas.
        </p>

        <div class="hero-actions">
            <a href="/register.php" class="btn">Mulai journey</a>
            <a href="/login.php" class="btn btn-outline">Saya sudah punya akun</a>
        </div>
        <p class="hero-note">Gratis · hanya kamu yang bisa membaca catatanmu · bisa dipasang di HP</p>
    </div>

    <div class="hero-card" data-tilt="5">
        <div class="hero-stage" id="hero-3d" aria-hidden="true"
             data-moods='<?= h(json_encode(["😄", "🥰", "🙂", "😐", "😴", "😰", "😔", "😡"], JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="emoji-big">😊</div>
        </div>
        <h2>Hari ini kamu merasa apa?</h2>
        <p>Pilih mood, ceritakan harimu, dan lihat perjalanan emosimu dari waktu ke waktu.</p>

        <div class="mood-row" aria-hidden="true">
            <?php foreach (["😄", "🥰", "🙂", "😐", "😴", "😰", "😔", "😡"] as $e): ?>
                <div class="mood-pill"><?= $e ?></div>
            <?php endforeach; ?>
        </div>
    </div>

</section>

<section class="lp-section" id="cara-kerja">
    <header class="lp-head reveal">
        <p class="eyebrow">Cara kerjanya</p>
        <h2>Cukup satu menit sehari</h2>
        <p class="muted">Tidak perlu menulis panjang. Satu emoji dan satu kalimat sudah cukup untuk hari itu.</p>
    </header>

    <ol class="lp-steps">
        <li class="lp-step reveal">
            <span class="lp-step-num">1</span>
            <span class="lp-step-emoji" aria-hidden="true">🙂</span>
            <h3>Pilih mood</h3>
            <p>Pilih satu dari delapan emoji, lalu geser seberapa kuat perasaannya.</p>
        </li>
        <li class="lp-step reveal" style="--d: 0.08s">
            <span class="lp-step-num">2</span>
            <span class="lp-step-emoji" aria-hidden="true">✍️</span>
            <h3>Ceritakan harimu</h3>
            <p>Tandai aktivitas, tulis cerita singkat kalau mau, dan tambahkan sampai 4 foto.</p>
        </li>
        <li class="lp-step reveal" style="--d: 0.16s">
            <span class="lp-step-num">3</span>
            <span class="lp-step-emoji" aria-hidden="true">🦋</span>
            <h3>Lihat dirimu tumbuh</h3>
            <p>Kupu-kupumu berkembang, dan polanya terlihat di statistik dan kalender setahun.</p>
        </li>
    </ol>
</section>

<section class="lp-section lp-butterfly">
    <header class="lp-head reveal">
        <p class="eyebrow">Streak yang ramah</p>
        <h2>Kupu-kupu yang tumbuh bersamamu</h2>
        <p class="muted">Bukan sekadar angka. Setiap hari yang kamu catat membawa kupu-kupumu satu langkah lebih dekat untuk terbang.</p>
    </header>

    <ol class="stage-track lp-stages reveal">
        <?php foreach (BUTTERFLY_STAGES as $key => $s):
            $pal = butterfly_pastel(BUTTERFLY_PALETTE["🥰"], 0.15);
        ?>
            <li class="stage-item is-current">
                <span class="stage-art"><?= butterfly_svg($key, $pal, ["animate" => $key === "adult", "label" => $s["label"]]) ?></span>
                <strong><?= h($s["label"]) ?></strong>
                <small><?= $s["min"] === 0 ? "Hari pertama" : $s["min"] . "+ hari" ?></small>
            </li>
        <?php endforeach; ?>
    </ol>

    <ul class="lp-kind reveal">
        <li><strong>🍃 Hari angin.</strong> Satu hari terlewat tiap minggu dimaafkan.</li>
        <li><strong>🌙 Tidak mulai dari nol.</strong> Kalau terlewat lagi, kupu-kupumu hanya beristirahat di kepompong.</li>
        <li><strong>🎨 Warna milikmu.</strong> Sayapnya mengikuti mood yang paling sering kamu catat.</li>
    </ul>
</section>

<section class="lp-section">
    <header class="lp-head reveal">
        <p class="eyebrow">Fitur</p>
        <h2>Semua yang kamu butuhkan, tanpa ribet</h2>
    </header>

    <div class="lp-features">
        <?php
        $features = [
            ["📈", "Wawasan yang jelas", "Grafik tren, lanskap mood 3D, hari terbaikmu, dan hubungan antara aktivitas dengan mood."],
            ["🟦", "Setahun dalam piksel", "Satu tahun penuh dalam satu layar, satu kotak berwarna untuk setiap hari."],
            ["🫧", "Ruang tenang", "Suara hujan, ombak, dan pemandu napas untuk jeda sebentar saat pikiran ramai."],
            ["💬", "Asisten dan dukungan", "Tanya cara memakai aplikasi kapan saja, atau ajukan bicara dengan konselor."],
            ["📷", "Foto kenangan", "Sertakan sampai 4 foto di setiap catatan. Ukurannya dikecilkan otomatis."],
            ["🔒", "Privat untukmu", "Catatan dan fotomu tidak tampil di panel admin atau konselor."],
        ];
        foreach ($features as $i => [$icon, $title, $text]): ?>
            <article class="lp-feature reveal" style="--d: <?= ($i % 3) * 0.07 ?>s" data-tilt="6">
                <span class="lp-feature-icon" aria-hidden="true"><?= $icon ?></span>
                <h3><?= h($title) ?></h3>
                <p><?= h($text) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="lp-section lp-trust">
    <div class="lp-trust-card reveal">
        <h2>Ruang aman, dengan batas yang jujur</h2>
        <p>
            MoodJourney adalah buku harian, bukan layanan medis dan tidak mendiagnosis apa pun.
            Kalau suatu hari terasa terlalu berat, kamu tidak sendirian: ada halaman bantuan dengan
            layanan gratis, dan kamu bisa mengajukan bicara dengan konselor.
        </p>
        <a class="btn btn-outline" href="/bantuan.php">Lihat layanan bantuan</a>
    </div>
</section>

<section class="lp-cta reveal">
    <h2>Mulai dari satu emoji.</h2>
    <p>Telur kupu-kupumu menunggu catatan pertamamu.</p>
    <div class="hero-actions lp-cta-actions">
        <a href="/register.php" class="btn">Buat akun gratis</a>
        <a href="/login.php" class="btn btn-outline">Masuk</a>
    </div>
</section>
<?php page_footer(["/assets/js/landing.js"]);
