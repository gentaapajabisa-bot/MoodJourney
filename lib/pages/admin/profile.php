<?php
$admin = require_admin();

$id = (int) ($_GET["id"] ?? 0);
$u = $id > 0
    ? db_one(
        "SELECT id, name, email, role, is_active, created_at, last_login_at, avatar_v
         FROM users WHERE id = ? AND role IN ('user', 'counselor') LIMIT 1",
        "i",
        [$id]
    )
    : null;

if ($u === null) {
    flash("error", "Akun tidak ditemukan.");
    redirect("/admin/");
}

$isUser = $u["role"] === "user";
$active = (int) $u["is_active"] === 1;

/* Ringkasan aktivitas (hanya angka, tidak pernah isi diary, mood, atau foto) */
$stats = ["entries" => 0, "days" => 0, "last" => null, "photos" => 0, "goals" => 0, "badges" => 0];
$streaks = ["current" => 0, "longest" => 0];
$journey = null;
$perDay = [];
$requests = [];
$room = null;
$pendingReset = 0;

if ($isUser) {
    $stats["entries"] = (int) db_value("SELECT COUNT(*) FROM diary WHERE user_id = ?", "i", [$id], 0);
    $stats["last"] = db_value("SELECT MAX(diary_date) FROM diary WHERE user_id = ?", "i", [$id], null);
    $stats["photos"] = (int) db_value("SELECT COUNT(*) FROM diary_photos WHERE user_id = ?", "i", [$id], 0);
    $stats["goals"] = (int) db_value("SELECT COUNT(*) FROM goals WHERE user_id = ?", "i", [$id], 0);
    $stats["badges"] = (int) db_value("SELECT COUNT(*) FROM achievements WHERE user_id = ?", "i", [$id], 0);

    $dates = array_column(
        db_all("SELECT DISTINCT diary_date FROM diary WHERE user_id = ? ORDER BY diary_date DESC LIMIT 3700", "i", [$id]),
        "diary_date"
    );
    $stats["days"] = count($dates);
    $streaks = compute_streaks($dates);
    $journey = journey_compute(array_reverse($dates));

    $from = date("Y-m-d", strtotime("-27 days"));
    $perDay = array_column(
        db_all("SELECT diary_date AS d, COUNT(*) AS c FROM diary WHERE user_id = ? AND diary_date >= ? GROUP BY diary_date", "is", [$id, $from]),
        "c",
        "d"
    );

    $requests = db_all(
        "SELECT sr.topic, sr.status, sr.urgent, sr.created_at, c.name AS counselor_name
         FROM support_requests sr LEFT JOIN users c ON c.id = sr.counselor_id
         WHERE sr.user_id = ? ORDER BY sr.created_at DESC LIMIT 5",
        "i",
        [$id]
    );
    $room = db_one(
        "SELECT r.id, r.status, r.last_message_at,
                (SELECT COUNT(*) FROM messages m WHERE m.chat_room_id = r.id) AS total
         FROM chat_rooms r WHERE r.user_id = ? LIMIT 1",
        "i",
        [$id]
    );
    $pendingReset = (int) db_value("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND token_hash IS NULL AND handled_at IS NULL", "i", [$id], 0);
}

$resetLink = $_SESSION["reset_link"] ?? null;
unset($_SESSION["reset_link"]);

$daysBetween = $u["created_at"] ? max(1, (int) floor((time() - strtotime($u["created_at"])) / 86400) + 1) : 1;
$consistency = $isUser ? min(100, (int) round($stats["days"] / $daysBetween * 100)) : 0;
$perDayMax = max(1, $perDay ? max($perDay) : 1);

page_header("Profil akun", ["nav" => "admin", "active" => "admin"]);
?>
<div class="container">

    <p class="adm-back"><a class="link" href="/admin/#pengguna">← Kembali ke panel admin</a></p>

    <?php if ($resetLink): ?>
        <section class="card reset-box" role="status">
            <h2>Tautan reset untuk <?= h($resetLink["name"]) ?></h2>
            <p class="muted">Kirim tautan ini lewat kontak yang kamu verifikasi. Berlaku 1 jam dan hanya sekali ini ditampilkan.</p>
            <div class="copy-row">
                <input class="form-control" type="text" readonly value="<?= h($resetLink["url"]) ?>" id="reset-url" aria-label="Tautan reset password">
                <button class="btn" type="button" data-copy="#reset-url">Salin</button>
            </div>
        </section>
    <?php endif; ?>

    <section class="adm-profile">
        <div class="adm-profile-main">
            <?= admin_avatar_html($u, 96, "adm-profile-avatar") ?>
            <div class="adm-profile-id">
                <h1><?= h($u["name"]) ?></h1>
                <p class="muted"><?= h($u["email"]) ?></p>
                <p>
                    <span class="admin-badge"><?= $isUser ? "Pengguna" : "Konselor" ?></span>
                    <span class="admin-badge <?= $active ? "" : "is-muted" ?>"><?= $active ? "Aktif" : "Nonaktif" ?></span>
                    <?php if ($pendingReset): ?><span class="admin-badge is-warn">Minta reset password</span><?php endif; ?>
                </p>
            </div>
        </div>
        <dl class="adm-facts">
            <div><dt>ID akun</dt><dd>#<?= (int) $u["id"] ?></dd></div>
            <div><dt>Bergabung</dt><dd><?= h(date_id($u["created_at"])) ?></dd></div>
            <div><dt>Terakhir masuk</dt><dd><?= $u["last_login_at"] ? h(date_id($u["last_login_at"])) : "Belum pernah" ?></dd></div>
            <?php if ($isUser): ?>
            <div><dt>Catatan terakhir</dt><dd><?= $stats["last"] ? h(date_id($stats["last"])) : "Belum ada" ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>

    <?php if ($isUser): ?>
    <section class="adm-stats adm-stats-5">
        <div class="adm-stat"><span class="adm-stat-icon is-lilac" aria-hidden="true">📝</span><div><span class="adm-stat-n"><?= $stats["entries"] ?></span><span class="adm-stat-l">Catatan</span><small><?= $stats["days"] ?> hari berbeda</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-peach" aria-hidden="true">🔥</span><div><span class="adm-stat-n"><?= (int) $streaks["current"] ?></span><span class="adm-stat-l">Streak sekarang</span><small>terpanjang <?= (int) $streaks["longest"] ?> hari</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-mint" aria-hidden="true">🏅</span><div><span class="adm-stat-n"><?= $stats["badges"] ?></span><span class="adm-stat-l">Pencapaian</span><small><?= $stats["goals"] ?> target dibuat</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-sky" aria-hidden="true">📷</span><div><span class="adm-stat-n"><?= $stats["photos"] ?></span><span class="adm-stat-l">Foto tersimpan</span><small>isi foto tidak ditampilkan</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-lilac" aria-hidden="true">📈</span><div><span class="adm-stat-n"><?= $consistency ?>%</span><span class="adm-stat-l">Konsistensi</span><small>hari tercatat sejak bergabung</small></div></div>
    </section>

    <section class="grid-2">
        <div class="card adm-card">
            <h2>Perjalanan kupu-kupu</h2>
            <div class="adm-journey">
                <div class="adm-journey-art"><?= butterfly_svg($journey["display"], BUTTERFLY_DEFAULT_COLORS, ["size" => 120]) ?></div>
                <div>
                    <strong><?= h(BUTTERFLY_STAGES[$journey["display"]]["label"]) ?></strong>
                    <p class="muted"><?= (int) $journey["days"] ?> hari perjalanan<?= $journey["resting"] ? " · sedang beristirahat" : "" ?></p>
                    <span class="onboard-bar" aria-hidden="true"><i style="width: <?= (int) $journey["progress"] ?>%"></i></span>
                    <small class="muted"><?= $journey["next"] ? "Menuju " . h(BUTTERFLY_STAGES[$journey["next"]]["label"]) . ": " . (int) $journey["days_left"] . " hari lagi" : "Tahap akhir tercapai" ?></small>
                </div>
            </div>
        </div>

        <div class="card adm-card">
            <h2>Aktivitas 4 minggu</h2>
            <p class="muted">Jumlah catatan per hari, tanpa isi.</p>
            <div class="adm-heat" role="img" aria-label="Aktivitas pencatatan 28 hari terakhir">
                <?php for ($i = 27; $i >= 0; $i--):
                    $d = date("Y-m-d", strtotime("-$i days"));
                    $c = (int) ($perDay[$d] ?? 0);
                    $lv = $c === 0 ? 0 : min(4, 1 + (int) floor(($c - 1) / max(1, $perDayMax / 4)));
                ?>
                    <i class="adm-heat-cell" data-lv="<?= $lv ?>" title="<?= h(date_id($d) . ": " . $c . " catatan") ?>"></i>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="grid-2">
        <div class="card adm-card">
            <h2>Dukungan dan chat</h2>
            <?php if ($room): ?>
                <p>Chat dengan tim: <span class="admin-badge <?= $room["status"] === "closed" ? "is-muted" : "" ?>"><?= $room["status"] === "closed" ? "Selesai" : "Terbuka" ?></span>
                    <span class="muted"><?= (int) $room["total"] ?> pesan<?= $room["last_message_at"] ? " · terakhir " . h(date("j M, H:i", strtotime($room["last_message_at"]))) : "" ?></span></p>
                <p><a class="btn btn-outline btn-small" href="/admin/chat.php?room=<?= (int) $room["id"] ?>">Buka chat</a></p>
            <?php else: ?>
                <p class="muted">Belum pernah memulai chat.</p>
            <?php endif; ?>

            <h3 class="subhead">Permintaan konselor</h3>
            <?php if (!$requests): ?>
                <p class="muted">Belum ada permintaan.</p>
            <?php else: ?>
                <ul class="adm-list">
                    <?php foreach ($requests as $r): ?>
                        <li>
                            <span><?= h($r["topic"]) ?><?= (int) $r["urgent"] === 1 ? ' <span class="msg-flag">⚠ Perlu perhatian</span>' : "" ?>
                                <small class="muted"><?= h(date("j M Y", strtotime($r["created_at"]))) ?><?= $r["counselor_name"] ? " · " . h($r["counselor_name"]) : "" ?></small></span>
                            <span class="admin-badge <?= in_array($r["status"], ["done", "closed"], true) ? "is-muted" : "" ?>"><?= h(["waiting" => "Menunggu", "active" => "Aktif", "done" => "Selesai"][$r["status"]] ?? $r["status"]) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <section class="grid-2">
    <?php endif; ?>

        <div class="card adm-card">
            <h2>Kelola akun</h2>
            <p class="muted">Isi diary, mood, dan foto pengguna tidak bisa dibuka dari sini. Admin hanya mengelola akunnya.</p>
            <div class="adm-actions">
                <form method="POST" action="/admin/user.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                    <input type="hidden" name="back" value="profil">
                    <button class="btn btn-outline" type="submit"><?= $active ? "Nonaktifkan akun" : "Aktifkan akun" ?></button>
                </form>
                <form method="POST" action="/admin/user.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_link">
                    <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                    <input type="hidden" name="back" value="profil">
                    <button class="btn btn-outline" type="submit">Buat tautan reset password</button>
                </form>
                <?php if ($isUser): ?>
                <form method="POST" action="/admin/user.php" data-confirm="Hapus <?= h($u["name"]) ?> beserta semua catatan dan chat-nya secara permanen?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                    <button class="btn btn-outline btn-quiet-danger" type="submit">Hapus akun</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

</div>
<?php page_footer();
