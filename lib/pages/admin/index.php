<?php
$admin = require_admin();

/* Peringatan: password admin demo belum diganti */
$adminHash = (string) db_value("SELECT password FROM users WHERE id = ?", "i", [(int) $admin["id"]], "");
$defaultPassword = password_verify("admin123", $adminHash);

/* Ringkasan */
$activeUsers = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user' AND is_active = 1", "", [], 0);
$totalUsers  = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user'", "", [], 0);
$totalDiary  = (int) db_value("SELECT COUNT(*) FROM diary", "", [], 0);
$diary7      = (int) db_value("SELECT COUNT(*) FROM diary WHERE diary_date >= ?", "s", [date("Y-m-d", strtotime("-6 days"))], 0);
$unread      = unread_count_for($admin);

/* Entri per hari, 14 hari terakhir (anonim) */
$from14 = date("Y-m-d", strtotime("-13 days"));
$perDay = array_column(
    db_all("SELECT diary_date AS d, COUNT(*) AS c FROM diary WHERE diary_date >= ? GROUP BY diary_date", "s", [$from14]),
    "c",
    "d"
);
$dayItems = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days"));
    $dayItems[] = [
        "label"   => (string) (int) date("j", strtotime($d)),
        "value"   => (int) ($perDay[$d] ?? 0),
        "title"   => date_id($d) . ": " . (int) ($perDay[$d] ?? 0) . " catatan",
    ];
}

/* Sebaran mood 30 hari terakhir (anonim) */
$moodDist = db_all(
    "SELECT mood, COUNT(*) AS c FROM diary WHERE diary_date >= ? GROUP BY mood ORDER BY c DESC",
    "s",
    [date("Y-m-d", strtotime("-29 days"))]
);
$moodTotal = array_sum(array_column($moodDist, "c"));
$moodMax = $moodDist ? (int) $moodDist[0]["c"] : 1;

/* Permintaan reset password */
$requests = db_all(
    "SELECT r.id, r.created_at, u.id AS user_id, u.name, u.email
     FROM password_resets r JOIN users u ON u.id = r.user_id
     WHERE r.token_hash IS NULL AND r.handled_at IS NULL
     ORDER BY r.created_at ASC LIMIT 50"
);

$resetLink = $_SESSION["reset_link"] ?? null;
unset($_SESSION["reset_link"]);

/* Pengguna (cari + halaman) */
$q = trim((string) ($_GET["q"] ?? ""));
$perPage = 15;
$where = "u.role = 'user'";
$types = "";
$params = [];
if ($q !== "") {
    $like = "%" . like_escape(mb_substr($q, 0, 100)) . "%";
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $types = "ss";
    $params = [$like, $like];
}
$totalMatch = (int) db_value("SELECT COUNT(*) FROM users u WHERE $where", $types, $params, 0);
$pages = max(1, (int) ceil($totalMatch / $perPage));
$page = max(1, min($pages, (int) ($_GET["page"] ?? 1)));

$users = db_all(
    "SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM diary d WHERE d.user_id = u.id) AS entries
     FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
    $types . "ii",
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

/* Chat */
$rooms = db_all(
    "SELECT r.id, r.status, r.last_message_at, u.name, u.email,
            (SELECT COUNT(*) FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE m.chat_room_id = r.id AND s.role = 'user' AND m.is_read = 0) AS unread
     FROM chat_rooms r JOIN users u ON u.id = r.user_id
     WHERE r.last_message_at IS NOT NULL
     ORDER BY unread DESC, r.last_message_at DESC, r.id DESC LIMIT 100"
);

page_header("Admin", ["nav" => "admin", "active" => "admin"]);
?>
<div class="container">

    <section class="page-title">
        <h1>Panel admin</h1>
        <p class="muted">Kelola pengguna dan percakapan. Catatan diary pengguna tidak ditampilkan di sini.</p>
    </section>

    <?php if ($defaultPassword): ?>
        <div class="notice notice-error" role="alert">
            Password admin masih memakai bawaan (<code>admin123</code>), yang diketahui publik.
            <a class="link" href="/profile.php">Ganti sekarang</a>.
        </div>
    <?php endif; ?>

    <?php if ($resetLink): ?>
        <section class="card reset-box" role="status">
            <h2>Tautan reset untuk <?= h($resetLink["name"]) ?></h2>
            <p class="muted">Kirim tautan ini ke pengguna lewat kontak yang kamu verifikasi. Berlaku 1 jam dan hanya sekali ini ditampilkan.</p>
            <div class="copy-row">
                <input class="form-control" type="text" readonly value="<?= h($resetLink["url"]) ?>" id="reset-url" aria-label="Tautan reset password">
                <button class="btn" type="button" data-copy="#reset-url">Salin</button>
            </div>
        </section>
    <?php endif; ?>

    <section class="grid-3 stats-4">
        <div class="stat-card" data-tilt="6"><span class="muted">Pengguna aktif</span><span class="number"><?= $activeUsers ?></span><span class="stat-note">dari <?= $totalUsers ?> terdaftar</span></div>
        <div class="stat-card" data-tilt="6"><span class="muted">Catatan 7 hari</span><span class="number"><?= $diary7 ?></span><span class="stat-note">total <?= $totalDiary ?></span></div>
        <div class="stat-card" data-tilt="6"><span class="muted">Chat belum dibaca</span><span class="number"><?= $unread ?></span><span class="stat-note"><?= count($rooms) ?> percakapan</span></div>
    </section>

    <section class="grid-2">
        <div class="card">
            <h2>Catatan per hari</h2>
            <p class="muted">14 hari terakhir, seluruh pengguna.</p>
            <?= html_columns($dayItems) ?>
        </div>
        <div class="card">
            <h2>Sebaran mood 30 hari</h2>
            <p class="muted">Gabungan, tanpa nama.</p>
            <?php if (!$moodDist): ?>
                <div class="empty">Belum ada data.</div>
            <?php endif; ?>
            <?php foreach ($moodDist as $row): ?>
                <div class="bar-row">
                    <span class="bar-face" title="<?= h(mood_label($row["mood"])) ?>"><?= h($row["mood"]) ?></span>
                    <div class="bar-bg"><div class="bar-fill" style="width: <?= round($row["c"] / $moodMax * 100) ?>%"></div></div>
                    <strong><?= (int) $row["c"] ?> <small class="muted">(<?= round($row["c"] / max(1, $moodTotal) * 100) ?>%)</small></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($requests): ?>
    <section class="card">
        <h2>Permintaan reset password</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pengguna</th><th>Diminta</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= h($r["name"]) ?><br><span class="muted"><?= h($r["email"]) ?></span></td>
                        <td><?= h(date_id($r["created_at"])) ?></td>
                        <td class="row-actions">
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_link">
                                <input type="hidden" name="user_id" value="<?= (int) $r["user_id"] ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                                <button class="btn btn-small" type="submit">Buat tautan</button>
                            </form>
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="dismiss_request">
                                <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit">Abaikan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Chat pengguna</h2>
        <?php if (!$rooms): ?>
            <div class="empty">Belum ada percakapan.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pengguna</th><th>Status</th><th>Pesan terakhir</th><th>Belum dibaca</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?= h($room["name"]) ?><br><span class="muted"><?= h($room["email"]) ?></span></td>
                        <td><span class="admin-badge <?= $room["status"] === "closed" ? "is-muted" : "" ?>"><?= $room["status"] === "closed" ? "Selesai" : "Terbuka" ?></span></td>
                        <td><?= h(date("j M, H:i", strtotime($room["last_message_at"]))) ?></td>
                        <td><?= (int) $room["unread"] > 0 ? '<span class="badge">' . (int) $room["unread"] . "</span>" : "–" ?></td>
                        <td><a class="btn btn-small" href="/admin/chat.php?room=<?= (int) $room["id"] ?>">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="section-head">
            <h2>Pengguna (<?= $totalMatch ?>)</h2>
            <form class="inline-search" method="GET" action="/admin/">
                <label class="sr-only" for="q">Cari pengguna</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Cari nama atau email">
                <button class="btn btn-outline btn-small" type="submit">Cari</button>
            </form>
        </div>

        <?php if (!$users): ?>
            <div class="empty">Tidak ada pengguna<?= $q !== "" ? " yang cocok" : "" ?>.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pengguna</th><th>Bergabung</th><th>Terakhir masuk</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): $active = (int) $u["is_active"] === 1; ?>
                    <tr>
                        <td><?= h($u["name"]) ?><br><span class="muted"><?= h($u["email"]) ?></span></td>
                        <td><?= h(date_id($u["created_at"])) ?></td>
                        <td><?= $u["last_login_at"] ? h(date_id($u["last_login_at"])) : "–" ?></td>
                        <td><?= (int) $u["entries"] ?></td>
                        <td><span class="admin-badge <?= $active ? "" : "is-muted" ?>"><?= $active ? "Aktif" : "Nonaktif" ?></span></td>
                        <td class="row-actions">
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit"><?= $active ? "Nonaktifkan" : "Aktifkan" ?></button>
                            </form>
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_link">
                                <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit">Tautan reset</button>
                            </form>
                            <form method="POST" action="/admin/user.php" data-confirm="Hapus <?= h($u["name"]) ?> beserta semua catatan dan chat-nya secara permanen?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= (int) $u["id"] ?>">
                                <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Halaman pengguna">
                <?php if ($page > 1): ?><a class="btn btn-outline btn-small" href="/admin/?<?= h(http_build_query(array_filter(["q" => $q, "page" => $page - 1]))) ?>">← Sebelumnya</a><?php endif; ?>
                <span class="muted">Halaman <?= $page ?> dari <?= $pages ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-outline btn-small" href="/admin/?<?= h(http_build_query(array_filter(["q" => $q, "page" => $page + 1]))) ?>">Berikutnya →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

</div>
<?php page_footer();
