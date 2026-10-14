<?php
$admin = require_admin();

$weeks = 12;
$firstMonday = week_start(date("Y-m-d", strtotime("-" . ($weeks - 1) . " weeks")));
$from = $firstMonday;

$pos = "'" . implode("','", ["😄", "🥰", "🙂"]) . "'";
$neu = "'😐'";

/* Pengguna baru per minggu */
$newBy = array_column(
    db_all("SELECT YEARWEEK(created_at, 1) AS wk, COUNT(*) AS c FROM users WHERE role = 'user' AND created_at >= ? GROUP BY YEARWEEK(created_at, 1)", "s", [$from]),
    "c",
    "wk"
);

/* Aktivitas pencatatan per minggu (gabungan, anonim) */
$diaryBy = [];
foreach (db_all(
    "SELECT YEARWEEK(diary_date, 1) AS wk, COUNT(*) AS entries, COUNT(DISTINCT user_id) AS active,
            SUM(CASE WHEN mood IN ($pos) THEN 1 ELSE 0 END) AS pos,
            SUM(CASE WHEN mood IN ($neu) THEN 1 ELSE 0 END) AS neu
     FROM diary WHERE diary_date >= ? GROUP BY YEARWEEK(diary_date, 1)",
    "s",
    [$from]
) as $r) {
    $diaryBy[$r["wk"]] = $r;
}

$rows = [];
for ($i = $weeks - 1; $i >= 0; $i--) {
    $ws = week_start(date("Y-m-d", strtotime("-$i weeks")));
    $wk = date("oW", strtotime($ws)); // setara YEARWEEK(..., 1)
    $d = $diaryBy[$wk] ?? null;
    $entries = (int) ($d["entries"] ?? 0);
    $active = (int) ($d["active"] ?? 0);
    $enough = $entries >= 10 && $active >= 3; // jangan tampilkan campuran mood kalau datanya terlalu sedikit
    $rows[] = [
        "label"   => date("j M", strtotime($ws)),
        "new"     => (int) ($newBy[$wk] ?? 0),
        "active"  => $active,
        "entries" => $entries,
        "enough"  => $enough,
        "pos"     => $enough ? (int) $d["pos"] : 0,
        "neu"     => $enough ? (int) $d["neu"] : 0,
        "low"     => $enough ? $entries - (int) $d["pos"] - (int) $d["neu"] : 0,
    ];
}

$maxEntries = max(1, max(array_column($rows, "entries")));
$maxNew = max(1, max(array_column($rows, "new")));

/* Aktivitas paling sering 30 hari terakhir */
$tagCount = [];
foreach (db_all("SELECT tags FROM diary WHERE diary_date >= ? AND tags IS NOT NULL AND tags <> ''", "s", [date("Y-m-d", strtotime("-29 days"))]) as $r) {
    foreach (tags_to_array($r["tags"]) as $t) {
        $tagCount[$t] = ($tagCount[$t] ?? 0) + 1;
    }
}
arsort($tagCount);
$tagMax = $tagCount ? max($tagCount) : 1;

$totalUsers = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user'", "", [], 0);
$active30 = (int) db_value("SELECT COUNT(DISTINCT user_id) FROM diary WHERE diary_date >= ?", "s", [date("Y-m-d", strtotime("-29 days"))], 0);
$ret = $totalUsers > 0 ? (int) round($active30 / $totalUsers * 100) : 0;

page_header("Laporan", ["nav" => "admin", "active" => "admin-report"]);
?>
<div class="container">

    <section class="adm-hero">
        <div class="adm-hero-text">
            <span class="adm-eyebrow">Laporan</span>
            <h1>Tren 12 minggu</h1>
            <p>Angka gabungan tanpa nama dan tanpa isi diary. Campuran mood hanya ditampilkan untuk minggu dengan data yang cukup agar tidak mengarah ke satu orang.</p>
        </div>
        <div class="adm-hero-art" aria-hidden="true"><span>📈</span><span>🦋</span></div>
    </section>

    <section class="adm-stats">
        <div class="adm-stat"><span class="adm-stat-icon is-lilac" aria-hidden="true">👥</span><div><span class="adm-stat-n"><?= $totalUsers ?></span><span class="adm-stat-l">Total pengguna</span><small>akun pengguna biasa</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-mint" aria-hidden="true">🟢</span><div><span class="adm-stat-n"><?= $active30 ?></span><span class="adm-stat-l">Aktif 30 hari</span><small><?= $ret ?>% dari total</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-sky" aria-hidden="true">📝</span><div><span class="adm-stat-n"><?= array_sum(array_column($rows, "entries")) ?></span><span class="adm-stat-l">Catatan 12 minggu</span><small>seluruh pengguna</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-peach" aria-hidden="true">✨</span><div><span class="adm-stat-n"><?= array_sum(array_column($rows, "new")) ?></span><span class="adm-stat-l">Pengguna baru</span><small>12 minggu terakhir</small></div></div>
    </section>

    <section class="grid-2">
        <div class="card adm-card">
            <h2>Catatan per minggu</h2>
            <p class="muted">Dengan campuran mood: <span class="rep-key is-pos"></span> cerah <span class="rep-key is-neu"></span> biasa <span class="rep-key is-low"></span> berat</p>
            <div class="rep-bars" role="img" aria-label="Jumlah catatan per minggu selama 12 minggu">
                <?php foreach ($rows as $r): $h = round($r["entries"] / $maxEntries * 100); ?>
                    <div class="rep-col" title="<?= h($r["label"] . ": " . $r["entries"] . " catatan, " . $r["active"] . " pengguna aktif") ?>">
                        <span class="rep-n"><?= $r["entries"] ?></span>
                        <span class="rep-bar" style="height: <?= max(2, $h) ?>%">
                            <?php if ($r["enough"] && $r["entries"] > 0): ?>
                                <i class="is-pos" style="flex: <?= $r["pos"] ?>"></i><i class="is-neu" style="flex: <?= $r["neu"] ?>"></i><i class="is-low" style="flex: <?= $r["low"] ?>"></i>
                            <?php else: ?>
                                <i class="is-flat" style="flex: 1"></i>
                            <?php endif; ?>
                        </span>
                        <small><?= h($r["label"]) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card adm-card">
            <h2>Pengguna baru per minggu</h2>
            <p class="muted">Pendaftaran akun pengguna biasa.</p>
            <div class="rep-bars" role="img" aria-label="Pengguna baru per minggu selama 12 minggu">
                <?php foreach ($rows as $r): ?>
                    <div class="rep-col" title="<?= h($r["label"] . ": " . $r["new"] . " pengguna baru") ?>">
                        <span class="rep-n"><?= $r["new"] ?></span>
                        <span class="rep-bar" style="height: <?= max(2, round($r["new"] / $maxNew * 100)) ?>%"><i class="is-new" style="flex: 1"></i></span>
                        <small><?= h($r["label"]) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="grid-2">
        <div class="card adm-card">
            <h2>Aktivitas paling sering</h2>
            <p class="muted">Aktivitas yang ditandai pengguna dalam 30 hari terakhir.</p>
            <?php if (!$tagCount): ?>
                <div class="empty">Belum ada data.</div>
            <?php else: foreach (array_slice($tagCount, 0, 8, true) as $tag => $n): ?>
                <div class="yip-bar-row">
                    <span class="yip-bar-label"><?= h(TAGS[$tag] ?? $tag) ?></span>
                    <span class="yip-bar-track"><span class="yip-bar-fill" style="width: <?= round($n / $tagMax * 100) ?>%; background: #b9a8f5"></span></span>
                    <span class="yip-bar-n"><?= (int) $n ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card adm-card">
            <h2>Rincian mingguan</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Minggu</th><th>Baru</th><th>Aktif</th><th>Catatan</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($rows) as $r): ?>
                        <tr><td><?= h($r["label"]) ?></td><td><?= $r["new"] ?></td><td><?= $r["active"] ?></td><td><?= $r["entries"] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
<?php page_footer();
