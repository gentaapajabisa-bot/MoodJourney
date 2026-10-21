<?php
$user = require_member();
$userId = (int) $user["id"];

$total = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ?", "i", [$userId], 0);

$dates = array_column(
    db_all(
        "SELECT DISTINCT diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date DESC",
        "is",
        [$userId, date("Y-m-d", strtotime("-3650 days"))]
    ),
    "diary_date"
);
$streak = compute_streaks($dates);

// 7 hari terakhir
$weekFrom = date("Y-m-d", strtotime("-6 days"));
$weekEntries = db_all(
    "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date ASC, id ASC",
    "is",
    [$userId, $weekFrom]
);
$weekScores = daily_scores($weekEntries);
$lastMoodPerDay = [];
foreach ($weekEntries as $e) {
    $lastMoodPerDay[$e["diary_date"]] = $e["mood"];
}
$weekAvg = $weekScores ? array_sum($weekScores) / count($weekScores) : null;

$recent = db_all(
    "SELECT * FROM diary WHERE user_id = ? ORDER BY diary_date DESC, id DESC LIMIT 5",
    "i",
    [$userId]
);

attach_photo_ids($recent, $userId);

$showSupport = needs_support_hint($userId);
$memories = memories_today($userId);
$challenge = challenge_current($userId);
$lettersReady = letters_ready_count($userId);
$heavyMood = recent_heavy_mood($userId);
$journey = journey_load($userId);

$goals = array_slice(goals_with_progress($userId), 0, 3);
$badgeRows = db_all(
    "SELECT code, seen FROM achievements WHERE user_id = ? ORDER BY earned_at DESC, id DESC LIMIT 4",
    "i",
    [$userId]
);
$badgeCount = (int) db_value("SELECT COUNT(*) FROM achievements WHERE user_id = ?", "i", [$userId], 0);

// Langkah awal: tampil sampai semuanya selesai atau pengguna menutupnya
$goalCount = (int) db_value("SELECT COUNT(*) FROM goals WHERE user_id = ?", "i", [$userId], 0);
$loggedDays = (int) db_value("SELECT COUNT(DISTINCT diary_date) FROM diary WHERE user_id = ?", "i", [$userId], 0);
$onboard = [
    ["Catat mood pertamamu", "Pilih emoji yang paling pas. Cerita boleh dikosongkan.", "/diary.php", $total >= 1, "Catat sekarang"],
    ["Catat di 3 hari berbeda", "Kupu-kupumu menetas jadi ulat setelah 3 hari tercatat.", "/diary.php", $loggedDays >= 3, "Catat lagi"],
    ["Buat satu target", "Pilih aktivitas dan berapa hari per minggu.", "/target.php", $goalCount >= 1, "Buat target"],
    ["Pasang foto profil", "Opsional, supaya akunmu terasa milikmu.", "/profile.php", !empty($user["avatar_v"]), "Buka profil"],
];
$onboardDone = count(array_filter($onboard, fn($s) => $s[3]));
$showOnboard = $onboardDone < count($onboard);

page_header("Dashboard", ["active" => "dashboard"]);
?>
<div class="container">

    <section class="dashboard-header">
        <div class="dashboard-greeting">
            <a href="/profile.php" class="greeting-avatar" aria-label="Buka profil"><?= avatar_html($user, 56) ?></a>
            <div>
                <h1>Halo, <?= h($user["name"]) ?> 👋</h1>
                <p class="muted">Gimana perasaanmu hari ini?</p>
            </div>
        </div>
        <a href="/diary.php" class="btn">+ Catat mood</a>
    </section>

    <?php if ($showOnboard): ?>
    <section class="card onboard" id="onboard" aria-labelledby="onboard-title" hidden>
        <div class="section-head">
            <div>
                <h2 id="onboard-title">Langkah awal</h2>
                <p class="muted"><?= $onboardDone ?> dari <?= count($onboard) ?> selesai. Tidak ada yang wajib, lakukan semampumu.</p>
            </div>
            <button type="button" class="link-btn" id="onboard-close">Sembunyikan</button>
        </div>
        <span class="onboard-bar" aria-hidden="true"><i style="width: <?= round($onboardDone / count($onboard) * 100) ?>%"></i></span>
        <ol class="onboard-list">
            <?php foreach ($onboard as $i => $st): ?>
                <li class="<?= $st[3] ? "is-done" : "" ?>">
                    <span class="onboard-check" aria-hidden="true"><?= $st[3] ? "✓" : $i + 1 ?></span>
                    <span class="onboard-text"><strong><?= h($st[0]) ?></strong><small><?= h($st[1]) ?></small></span>
                    <?php if (!$st[3]): ?><a class="btn btn-outline btn-small" href="<?= h($st[2]) ?>"><?= h($st[4]) ?></a><?php else: ?><span class="muted onboard-ok">Selesai</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="muted card-foot">Bingung? Tanya asisten di pojok layar, misalnya "Bagaimana cara memakai MoodJourney dari awal?"</p>
    </section>
    <script>
    (function () {
        var el = document.getElementById("onboard");
        if (!el) return;
        var hide = false;
        try { hide = localStorage.getItem("mj-onboard-hide") === "1"; } catch (e) {}
        el.hidden = hide;
        document.getElementById("onboard-close").addEventListener("click", function () {
            el.hidden = true;
            try { localStorage.setItem("mj-onboard-hide", "1"); } catch (e) {}
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    $guideSteps = [
        ["📝", "Catat mood", "Klik \"Catat mood\", pilih emoji yang paling pas, geser kekuatan perasaannya (1 sampai 5), tandai aktivitas, lalu simpan. Cerita dan foto (maksimal 4) boleh dikosongkan.", "/diary.php", "Catat mood"],
        ["🦋", "Rawat kupu-kupumu", "Setiap hari yang kamu catat membuat kupu-kupu tumbuh: telur, ulat, kepompong, lalu kupu-kupu. Satu hari terlewat tiap minggu dimaafkan.", "/metamorfosis.php", "Lihat perjalanan"],
        ["🔎", "Lihat polamu", "Riwayat untuk mencari catatan lama. Wawasan berisi grafik, ringkasan mingguan, target, pencapaian, dan kalender setahun.", "/statistics.php", "Buka Wawasan"],
        ["🎯", "Buat target kecil", "Pilih aktivitas dan berapa hari per minggu. Progresnya terlihat di dashboard.", "/target.php", "Buat target"],
        ["💬", "Butuh bantuan?", "Tanya asisten di pojok layar kapan saja. Untuk bicara dengan orang, buka Dukungan (chat dengan tim atau konselor).", "/dukungan.php", "Buka Dukungan"],
    ];
    ?>
    <section class="card guide" id="guide">
        <details <?= $total === 0 ? "open" : "" ?>>
            <summary>
                <span class="guide-title"><span aria-hidden="true">📖</span> Cara memakai MoodJourney</span>
                <span class="guide-sub muted">5 langkah singkat</span>
            </summary>
            <ol class="guide-steps">
                <?php foreach ($guideSteps as $i => $g): ?>
                    <li>
                        <span class="guide-icon" aria-hidden="true"><?= $g[0] ?></span>
                        <div class="guide-body">
                            <strong><?= $i + 1 ?>. <?= h($g[1]) ?></strong>
                            <p><?= h($g[2]) ?></p>
                        </div>
                        <a class="btn btn-outline btn-small" href="<?= h($g[3]) ?>"><?= h($g[4]) ?></a>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="muted card-foot">Semua langkah ini opsional. Mulai dari yang paling nyaman buatmu.</p>
        </details>
    </section>

    <section class="garden" aria-label="Taman kupu-kupu">
        <canvas id="garden" aria-hidden="true"
                data-count="<?= garden_butterfly_count($journey["days"]) ?>"
                data-c1="<?= h($journey["colors"][0]) ?>" data-c2="<?= h($journey["colors"][1]) ?>"></canvas>
        <div class="garden-caption">
            <strong>Taman kupu-kupumu</strong>
            <span><?= garden_butterfly_count($journey["days"]) ?> kupu-kupu · gerakkan kursor atau sentuh untuk memanggil mereka</span>
        </div>
        <a class="garden-link" href="/metamorfosis.php">Lihat perjalananmu →</a>
        <a class="garden-share btn btn-small" href="/bagikan.php">🦋 Bagikan</a>
    </section>

    <?php if (!$streak["today_done"]): ?>
        <div class="notice notice-info reminder">
            <span>
                <?php if ($streak["current"] > 0): ?>
                    Kamu sudah mencatat <?= $streak["current"] ?> hari berturut-turut. Catat hari ini untuk melanjutkan.
                <?php else: ?>
                    Kamu belum mencatat mood hari ini. Cukup satu menit.
                <?php endif; ?>
            </span>
            <a class="btn btn-small" href="/diary.php">Catat sekarang</a>
        </div>
    <?php endif; ?>

    <?php if (!$streak["today_done"]): ?>
        <section class="card dash-quick" aria-label="Check-in cepat">
            <span>Check-in 5 detik</span>
            <form method="POST" action="/cepat.php">
                <?= csrf_field() ?>
                <?php foreach (MOODS as $emoji => $m): ?>
                    <button type="submit" name="mood" value="<?= h($emoji) ?>" aria-label="<?= h($m["label"]) ?>" title="<?= h($m["label"]) ?>"><?= h($emoji) ?></button>
                <?php endforeach; ?>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($heavyMood !== null): ?>
        <section class="card care-card" aria-label="Mau ditemani">
            <h2>Mau ditemani sebentar? 🤍</h2>
            <p class="muted">Terima kasih sudah jujur pada perasaanmu. Kamu tidak harus menghadapi hari yang berat sendirian.</p>
            <div class="care-actions">
                <a class="btn btn-small" href="/tenang.php">Latihan napas</a>
                <a class="btn btn-outline btn-small" href="/formulir-konselor.php">Bicara dengan konselor</a>
                <a class="btn btn-outline btn-small" href="/bantuan.php">Layanan bantuan</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($lettersReady > 0): ?>
        <div class="notice notice-info letter-banner">
            <span>💌 Ada <?= $lettersReady ?> surat dari dirimu yang sudah boleh dibuka.</span>
            <a class="btn btn-small" href="/surat.php">Buka surat</a>
        </div>
    <?php endif; ?>

    <section class="dash-extra">
        <?php if ($memories): ?>
        <div class="card">
            <h2>🕰️ Pada hari ini</h2>
            <?php foreach ($memories as $m): ?>
                <a class="mem-item" href="/history.php?from=<?= h($m["date"]) ?>&amp;to=<?= h($m["date"]) ?>" style="text-decoration:none;color:inherit">
                    <span class="mem-emoji" aria-hidden="true"><?= h($m["mood"]) ?></span>
                    <span><small><?= h($m["label"]) ?> · <?= h(date_id($m["date"])) ?></small>
                        <?php if ($m["note"] !== ""): ?><p><?= h($m["note"]) ?></p><?php endif; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a class="card dash-chal" href="/tantangan.php" style="text-decoration:none;color:inherit">
            <span class="challenge-icon" aria-hidden="true"><?= $challenge["def"]["icon"] ?></span>
            <span style="flex:1;min-width:0">
                <small class="muted">Tantangan minggu ini</small><br>
                <strong><?= h($challenge["def"]["title"]) ?></strong>
                <span class="onboard-bar" aria-hidden="true"><i style="width: <?= round($challenge["progress"] / max(1, $challenge["target"]) * 100) ?>%"></i></span>
                <small class="muted"><?= $challenge["done"] ? "Selesai 🎉" : $challenge["progress"] . " dari " . $challenge["target"] ?></small>
            </span>
        </a>
    </section>

    <?php if ($showSupport): ?>
        <aside class="support-card" aria-label="Dukungan">
            <p class="support-title">Beberapa hari terakhir terasa berat.</p>
            <p>
                Itu wajar dan kamu tidak perlu menghadapinya sendirian. Ngobrol dengan orang yang kamu percaya
                atau tenaga profesional bisa membantu. Ada layanan gratis yang bisa dihubungi kapan saja.
            </p>
            <a class="btn btn-small" href="/bantuan.php">Lihat layanan bantuan</a>
        </aside>
    <?php endif; ?>

    <section class="grid-3">
        <a class="stat-card journey-mini" data-tilt="6" href="/metamorfosis.php" aria-label="Lihat metamorfosis kupu-kupumu">
            <span class="journey-mini-art"><?= butterfly_svg($journey["display"], $journey["colors"], ["animate" => false, "label" => BUTTERFLY_STAGES[$journey["display"]]["label"]]) ?></span>
            <span class="journey-mini-body">
                <span class="muted">Perjalananmu</span>
                <span class="number"><?= $journey["days"] ?> <small>hari</small></span>
                <span class="stat-note"><?= h($journey["resting"] ? "Kepompong, sedang istirahat" : BUTTERFLY_STAGES[$journey["display"]]["label"]) ?> · beruntun <?= $streak["current"] ?> hari</span>
            </span>
        </a>

        <div class="stat-card" data-tilt="6">
            <span class="muted">Mood 7 hari terakhir</span>
            <?php if ($weekAvg !== null): ?>
                <span class="number"><?= h(score_to_emoji($weekAvg)) ?> <small><?= h(score_label($weekAvg)) ?></small></span>
                <span class="stat-note">Rata-rata <?= number_format($weekAvg, 1) ?> dari 5</span>
            <?php else: ?>
                <span class="number">–</span>
                <span class="stat-note">Belum ada catatan minggu ini</span>
            <?php endif; ?>
        </div>

        <div class="stat-card" data-tilt="6">
            <span class="muted">Total catatan</span>
            <span class="number"><?= $total ?></span>
            <span class="stat-note"><a class="link" href="/history.php">Lihat semua</a></span>
        </div>
    </section>

    <section class="card week-card">
        <h2>7 hari terakhir</h2>
        <div class="week-strip">
            <?php for ($i = 6; $i >= 0; $i--):
                $d = date("Y-m-d", strtotime("-$i days"));
                $has = isset($lastMoodPerDay[$d]);
                $score = $has ? (int) round($weekScores[$d]) : 0;
                $dayName = mb_substr(DAYS_ID[(int) date("N", strtotime($d))], 0, 3);
            ?>
                <a class="week-day <?= $has ? "filled" : "" ?>" <?= $has ? 'data-score="' . $score . '"' : "" ?>
                   href="<?= $has ? "/history.php?from=$d&to=$d" : "/diary.php?date=$d" ?>"
                   title="<?= h(date_id($d)) ?>">
                    <span class="week-name"><?= h($dayName) ?></span>
                    <span class="week-emoji"><?= $has ? h($lastMoodPerDay[$d]) : "·" ?></span>
                    <span class="week-num"><?= (int) date("j", strtotime($d)) ?></span>
                </a>
            <?php endfor; ?>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <div class="section-head">
                <h2>Target minggu ini</h2>
                <a class="btn btn-outline btn-small" href="/target.php">Kelola</a>
            </div>
            <?php if (!$goals): ?>
                <p class="muted">Belum ada target. Pilih satu kebiasaan yang ingin kamu jaga.</p>
                <a class="btn btn-small" href="/target.php">Buat target</a>
            <?php endif; ?>
            <?php foreach ($goals as $g): $pct = min(100, round($g["done"] / max(1, $g["target"]) * 100)); ?>
                <div class="goal-row">
                    <div class="goal-head">
                        <strong><?= h($g["label"]) ?></strong>
                        <span class="<?= $g["met"] ? "delta up" : "muted" ?>"><?= $g["met"] ? "✓ " : "" ?><?= (int) $g["done"] ?>/<?= (int) $g["target"] ?> hari</span>
                    </div>
                    <div class="bar-bg" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $g["target"] ?>" aria-valuenow="<?= (int) $g["done"] ?>" aria-label="<?= h($g["label"]) ?>">
                        <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="section-head">
                <h2>Pencapaian</h2>
                <a class="btn btn-outline btn-small" href="/pencapaian.php"><?= $badgeCount ?>/<?= count(ACHIEVEMENTS) ?></a>
            </div>
            <?php if (!$badgeRows): ?>
                <p class="muted">Catat mood pertamamu untuk membuka pencapaian pertama.</p>
            <?php else: ?>
                <div class="badge-strip">
                    <?php foreach ($badgeRows as $b): [$icon, $title] = ACHIEVEMENTS[$b["code"]] ?? ["🏅", $b["code"]]; ?>
                        <a class="badge-mini <?= (int) $b["seen"] === 0 ? "is-new" : "" ?>" href="/pencapaian.php" title="<?= h($title) ?>">
                            <span aria-hidden="true"><?= h($icon) ?></span>
                            <small><?= h($title) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="muted card-foot"><a class="link" href="/ringkasan.php">Lihat ringkasan mingguan →</a></p>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2>Catatan terbaru</h2>
                <p class="muted">Lima catatan mood terakhir kamu.</p>
            </div>
            <a class="btn btn-outline btn-small" href="/history.php">Semua riwayat</a>
        </div>

        <?php if (!$recent): ?>
            <div class="empty">
                <p>Belum ada catatan. Mulai dari yang kecil: satu emoji dan satu kalimat.</p>
                <a href="/diary.php" class="btn">Buat catatan pertama</a>
            </div>
        <?php else: ?>
            <?php foreach ($recent as $entry): ?>
                <?= render_diary_item($entry, true, "/dashboard.php") ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div>
<?php page_footer(["/assets/js/garden.js"]);
