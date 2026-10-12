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
    "SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.last_login_at, u.avatar_v,
            (SELECT COUNT(*) FROM diary d WHERE d.user_id = u.id) AS entries
     FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
    $types . "ii",
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

/* Konselor dan permintaan dukungan */
$counselors = db_all(
    "SELECT u.id, u.name, u.email, u.is_active, u.last_login_at,
            (SELECT COUNT(*) FROM support_requests sr WHERE sr.counselor_id = u.id AND sr.status = 'active') AS active_count
     FROM users u WHERE u.role = 'counselor' ORDER BY u.created_at DESC"
);
$activeCounselors = array_values(array_filter($counselors, fn($c) => (int) $c["is_active"] === 1));
$supportRequests = db_all(
    "SELECT sr.id, sr.topic, sr.status, sr.urgent, sr.created_at, sr.counselor_id,
            u.name AS user_name, c.name AS counselor_name
     FROM support_requests sr
     JOIN users u ON u.id = sr.user_id
     LEFT JOIN users c ON c.id = sr.counselor_id
     WHERE sr.status IN ('waiting', 'active')
     ORDER BY sr.urgent DESC, sr.created_at ASC LIMIT 50"
);

/* Chat */
$rooms = db_all(
    "SELECT r.id, r.status, r.last_message_at, u.name, u.email,
            (SELECT COUNT(*) FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE m.chat_room_id = r.id AND s.role = 'user' AND m.is_read = 0) AS unread,
            (SELECT COUNT(*) FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE m.chat_room_id = r.id AND s.role = 'user' AND m.is_read = 0 AND m.flagged = 1) AS flagged
     FROM chat_rooms r JOIN users u ON u.id = r.user_id
     WHERE r.last_message_at IS NOT NULL
     ORDER BY flagged DESC, unread DESC, r.last_message_at DESC, r.id DESC LIMIT 100"
);

$attention = (int) db_value("SELECT COUNT(*) FROM support_requests WHERE status IN ('waiting','active') AND urgent = 1", "", [], 0);
$waiting = count(array_filter($supportRequests, fn($r) => $r["status"] === "waiting"));
$newUsers7 = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= ?", "s", [date("Y-m-d", strtotime("-6 days"))], 0);

page_header("Admin", ["nav" => "admin", "active" => "admin"]);
?>
<div class="container">

    <section class="adm-hero">
        <div class="adm-hero-text">
            <span class="adm-eyebrow">Panel admin</span>
            <h1>Halo, <?= h($admin["name"]) ?></h1>
            <p>Kelola pengguna, dukungan, dan percakapan. Isi catatan diary pengguna tidak pernah ditampilkan di sini.</p>
        </div>
        <div class="adm-hero-art" aria-hidden="true"><span>🦋</span><span>🌿</span><span>✨</span></div>
    </section>

    <nav class="adm-nav" aria-label="Bagian panel admin">
        <a href="#ringkasan">Ringkasan</a>
        <a href="#dukungan">Dukungan<?php if ($waiting): ?> <span class="badge"><?= $waiting ?></span><?php endif; ?></a>
        <a href="#konselor">Konselor</a>
        <a href="#chat">Chat<?php if ($unread): ?> <span class="badge"><?= $unread ?></span><?php endif; ?></a>
        <a href="#pengguna">Pengguna</a>
    </nav>

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

    <section class="adm-stats" id="ringkasan">
        <div class="adm-stat"><span class="adm-stat-icon is-lilac" aria-hidden="true">👥</span><div><span class="adm-stat-n"><?= $activeUsers ?></span><span class="adm-stat-l">Pengguna aktif</span><small>dari <?= $totalUsers ?> terdaftar · +<?= $newUsers7 ?> minggu ini</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-mint" aria-hidden="true">📝</span><div><span class="adm-stat-n"><?= $diary7 ?></span><span class="adm-stat-l">Catatan 7 hari</span><small>total <?= $totalDiary ?> catatan</small></div></div>
        <div class="adm-stat"><span class="adm-stat-icon is-sky" aria-hidden="true">💬</span><div><span class="adm-stat-n"><?= $unread ?></span><span class="adm-stat-l">Chat belum dibaca</span><small><?= count($rooms) ?> percakapan</small></div></div>
        <div class="adm-stat <?= $attention ? "is-alert" : "" ?>"><span class="adm-stat-icon is-peach" aria-hidden="true">⚠️</span><div><span class="adm-stat-n"><?= $attention ?></span><span class="adm-stat-l">Perlu perhatian</span><small><?= $waiting ?> permintaan menunggu</small></div></div>
    </section>

    <section class="grid-2">
        <div class="card adm-card">
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

    <section class="card adm-card" id="dukungan">
        <h2>Permintaan bicara dengan konselor</h2>
        <?php if (!$activeCounselors): ?>
            <div class="notice notice-info">Belum ada konselor aktif. Tambahkan akun konselor di bawah supaya fitur ini bisa dipakai pengguna.</div>
        <?php endif; ?>
        <?php if (!$supportRequests): ?>
            <div class="empty">Tidak ada permintaan yang berjalan.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pengguna</th><th>Topik</th><th>Status</th><th>Ditangani</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($supportRequests as $sr): ?>
                    <tr>
                        <td><?= h($sr["user_name"]) ?><?= (int) $sr["urgent"] === 1 ? ' <span class="msg-flag">⚠ Perlu perhatian</span>' : "" ?></td>
                        <td><?= h($sr["topic"]) ?><br><span class="muted"><?= h(date("j M, H:i", strtotime($sr["created_at"]))) ?></span></td>
                        <td><span class="admin-badge <?= $sr["status"] === "waiting" ? "is-muted" : "" ?>"><?= $sr["status"] === "waiting" ? "Menunggu" : "Aktif" ?></span></td>
                        <td><?= h($sr["counselor_name"] ?? "–") ?></td>
                        <td class="row-actions">
                            <?php if ($activeCounselors): ?>
                            <form method="POST" action="/support/action.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="request_id" value="<?= (int) $sr["id"] ?>">
                                <label class="sr-only" for="c<?= (int) $sr["id"] ?>">Pilih konselor</label>
                                <select class="form-control select-small" id="c<?= (int) $sr["id"] ?>" name="counselor_id">
                                    <?php foreach ($activeCounselors as $c): ?>
                                        <option value="<?= (int) $c["id"] ?>" <?= (int) $c["id"] === (int) $sr["counselor_id"] ? "selected" : "" ?>><?= h($c["name"]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline btn-small" type="submit">Tugaskan</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($sr["status"] === "active"): ?>
                            <form method="POST" action="/support/action.php" data-confirm="Tandai permintaan ini selesai?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="done">
                                <input type="hidden" name="request_id" value="<?= (int) $sr["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit">Selesai</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card adm-card" id="konselor">
        <h2>Konselor (<?= count($counselors) ?>)</h2>
        <p class="muted">Konselor hanya bisa membuka percakapan pengguna yang permintaannya ia ambil atau yang kamu tugaskan kepadanya.</p>

        <?php if ($counselors): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Konselor</th><th>Terakhir masuk</th><th>Aktif ditangani</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($counselors as $c): $cActive = (int) $c["is_active"] === 1; ?>
                    <tr>
                        <td><?= h($c["name"]) ?><br><span class="muted"><?= h($c["email"]) ?></span></td>
                        <td><?= $c["last_login_at"] ? h(date_id($c["last_login_at"])) : "Belum pernah" ?></td>
                        <td><?= (int) $c["active_count"] ?></td>
                        <td><span class="admin-badge <?= $cActive ? "" : "is-muted" ?>"><?= $cActive ? "Aktif" : "Nonaktif" ?></span></td>
                        <td class="row-actions">
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= (int) $c["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit"><?= $cActive ? "Nonaktifkan" : "Aktifkan" ?></button>
                            </form>
                            <form method="POST" action="/admin/user.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_link">
                                <input type="hidden" name="user_id" value="<?= (int) $c["id"] ?>">
                                <button class="btn btn-outline btn-small" type="submit">Tautan reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <h3 class="subhead">Tambah konselor</h3>
        <form method="POST" action="/admin/user.php" class="counselor-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_counselor">
            <div class="form-group">
                <label for="cname">Nama</label>
                <input class="form-control" type="text" id="cname" name="name" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="cemail">Email</label>
                <input class="form-control" type="email" id="cemail" name="email" maxlength="150" required>
            </div>
            <button class="btn" type="submit">Buat akun konselor</button>
            <p class="muted">Kamu akan mendapat tautan sekali pakai agar konselor membuat passwordnya sendiri. Pastikan ia memang tenaga yang terlatih.</p>
        </form>
    </section>

    <section class="card adm-card" id="chat">
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
                        <td><?= (int) $room["unread"] > 0 ? '<span class="badge">' . (int) $room["unread"] . "</span>" : "–" ?>
                            <?= (int) $room["flagged"] > 0 ? '<span class="msg-flag">⚠ Perlu perhatian</span>' : "" ?></td>
                        <td><a class="btn btn-small" href="/admin/chat.php?room=<?= (int) $room["id"] ?>">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card adm-card" id="pengguna">
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
                        <td>
                            <a class="adm-user" href="/admin/profil.php?id=<?= (int) $u["id"] ?>">
                                <?= admin_avatar_html($u, 40) ?>
                                <span><strong><?= h($u["name"]) ?></strong><small class="muted"><?= h($u["email"]) ?></small></span>
                            </a>
                        </td>
                        <td><?= h(date_id($u["created_at"])) ?></td>
                        <td><?= $u["last_login_at"] ? h(date_id($u["last_login_at"])) : "–" ?></td>
                        <td><?= (int) $u["entries"] ?></td>
                        <td><span class="admin-badge <?= $active ? "" : "is-muted" ?>"><?= $active ? "Aktif" : "Nonaktif" ?></span></td>
                        <td class="row-actions">
                            <a class="btn btn-small" href="/admin/profil.php?id=<?= (int) $u["id"] ?>">Profil</a>
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
