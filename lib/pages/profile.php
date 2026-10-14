<?php
$user = require_login();
$userId = (int) $user["id"];
$isAdmin = $user["role"] !== "user"; // admin dan konselor: tanpa fitur diary

if (is_post()) {
    csrf_verify("/profile.php");
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "profile") {
        $name = trim((string) ($_POST["name"] ?? ""));
        $bio = trim((string) ($_POST["bio"] ?? ""));

        if ($name === "" || mb_strlen($name) > 100) {
            flash("error", "Nama harus diisi (maksimal 100 karakter).");
        } elseif (mb_strlen($bio) > 500) {
            flash("error", "Bio maksimal 500 karakter.");
        } else {
            db_exec("UPDATE users SET name = ?, bio = ? WHERE id = ?", "ssi", [$name, $bio, $userId]);
            flash("success", "Profil diperbarui.");
        }
        redirect("/profile.php");
    }

    if ($action === "avatar") {
        $bucket = "avatar:" . $userId;
        $photo = photo_read_single("avatar", AVATAR_MAX_BYTES, AVATAR_MAX_DIM);
        if (rate_count($bucket, 3600) >= 20) {
            flash("error", "Terlalu sering mengganti foto. Coba lagi nanti.");
        } elseif ($photo === null) {
            flash("error", "Pilih foto terlebih dulu.");
        } elseif (isset($photo["error"])) {
            flash("error", $photo["error"]);
        } else {
            rate_hit($bucket);
            avatar_save($userId, $photo);
            flash("success", "Foto profil diperbarui.");
        }
        redirect("/profile.php");
    }

    if ($action === "avatar_remove") {
        avatar_delete($userId);
        flash("success", "Foto profil dihapus.");
        redirect("/profile.php");
    }

    if ($action === "password") {
        $bucket = "pwchange:" . $userId;
        $current = (string) ($_POST["current"] ?? "");
        $new = (string) ($_POST["new"] ?? "");
        $confirm = (string) ($_POST["confirm"] ?? "");

        $hash = (string) db_value("SELECT password FROM users WHERE id = ?", "i", [$userId], "");

        if (rate_count($bucket, 900) >= 5) {
            flash("error", "Terlalu banyak percobaan. Coba lagi dalam 15 menit.");
        } elseif (!password_verify($current, $hash)) {
            rate_hit($bucket);
            flash("error", "Password saat ini salah.");
        } elseif (strlen($new) < 8) {
            flash("error", "Password baru minimal 8 karakter.");
        } elseif ($new !== $confirm) {
            flash("error", "Konfirmasi password baru tidak sama.");
        } else {
            db_exec("UPDATE users SET password = ? WHERE id = ?", "si", [password_hash($new, PASSWORD_DEFAULT), $userId]);
            rate_clear($bucket);
            flash("success", "Password berhasil diubah.");
        }
        redirect("/profile.php");
    }

    if (($action === "pin_set" || $action === "pin_remove") && !$isAdmin) {
        $bucket = "pwchange:" . $userId;
        $hash = (string) db_value("SELECT password FROM users WHERE id = ?", "i", [$userId], "");
        $pw = (string) ($_POST["pin_password"] ?? "");
        $pin = (string) ($_POST["pin"] ?? "");
        $pin2 = (string) ($_POST["pin_confirm"] ?? "");

        if (rate_count($bucket, 900) >= 5) {
            flash("error", "Terlalu banyak percobaan. Coba lagi dalam 15 menit.");
        } elseif (!password_verify($pw, $hash)) {
            rate_hit($bucket);
            flash("error", "Password salah.");
        } elseif ($action === "pin_remove") {
            db_exec("UPDATE users SET pin_hash = NULL WHERE id = ?", "i", [$userId]);
            flash("success", "Kunci PIN dimatikan.");
        } elseif (!preg_match('/^\d{4,6}$/', $pin)) {
            flash("error", "PIN harus 4 sampai 6 angka.");
        } elseif ($pin !== $pin2) {
            flash("error", "Konfirmasi PIN tidak sama.");
        } elseif (in_array($pin, ["0000", "1111", "1234", "123456", "000000", "111111"], true)) {
            flash("error", "PIN itu terlalu mudah ditebak. Pilih angka lain.");
        } else {
            db_exec("UPDATE users SET pin_hash = ? WHERE id = ?", "si", [password_hash($pin, PASSWORD_DEFAULT), $userId]);
            pin_unlock();
            flash("success", "Kunci PIN aktif. PIN diminta lagi setelah " . (PIN_IDLE_SECONDS / 60) . " menit tidak aktif.");
        }
        redirect("/profile.php#pin");
    }

    if ($action === "prefs" && !$isAdmin) {
        $email = isset($_POST["reminder_email"]) ? 1 : 0;
        db_exec("UPDATE users SET reminder_email = ? WHERE id = ?", "ii", [$email, $userId]);
        flash("success", $email ? "Pengingat email diaktifkan." : "Pengingat email dimatikan.");
        redirect("/profile.php");
    }

    if ($action === "delete" && !$isAdmin) {
        $bucket = "pwchange:" . $userId;
        $hash = (string) db_value("SELECT password FROM users WHERE id = ?", "i", [$userId], "");

        if (rate_count($bucket, 900) >= 5) {
            flash("error", "Terlalu banyak percobaan. Coba lagi dalam 15 menit.");
            redirect("/profile.php");
        }
        if (!password_verify((string) ($_POST["confirm_password"] ?? ""), $hash) || ($_POST["confirm_text"] ?? "") !== "HAPUS") {
            rate_hit($bucket);
            flash("error", "Penghapusan dibatalkan. Pastikan password dan kata HAPUS diisi dengan benar.");
            redirect("/profile.php");
        }

        delete_user_data($userId);
        $_SESSION = [];
        session_regenerate_id(true);
        flash("success", "Akunmu dan semua datanya sudah dihapus.");
        redirect("/");
    }

    redirect("/profile.php");
}

$stats = db_one(
    "SELECT COUNT(*) AS total, MIN(diary_date) AS first_date FROM diary WHERE user_id = ?",
    "i",
    [$userId]
);

page_header("Profil", ["nav" => nav_for($user), "active" => "profile"]);
?>
<div class="container narrow">

    <section class="page-title">
        <h1>Profil</h1>
        <p class="muted">
            <?= h($user["email"]) ?> · bergabung <?= h(date_id($user["created_at"])) ?>
            <?php if (!$isAdmin && (int) $stats["total"] > 0): ?>
                · <?= (int) $stats["total"] ?> catatan sejak <?= h(date_id($stats["first_date"])) ?>
            <?php endif; ?>
        </p>
    </section>

    <section class="card">
        <h2>Foto profil</h2>
        <div class="avatar-editor">
            <div class="avatar-preview" id="avatar-preview-wrap"><?= avatar_html($user, 96, "avatar-lg") ?></div>
            <div class="avatar-controls">
                <form method="POST" enctype="multipart/form-data" id="avatar-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="avatar">
                    <label class="sr-only" for="avatar-input">Pilih foto profil</label>
                    <input class="form-control" type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp,image/*">
                    <small class="muted block">Foto dipotong persegi dan dikecilkan otomatis di perangkatmu. Hanya kamu yang bisa melihatnya.</small>
                    <p class="muted" id="avatar-status" aria-live="polite"></p>
                    <button class="btn btn-small" type="submit" id="avatar-save" disabled>Simpan foto</button>
                </form>
                <?php if (!empty($user["avatar_v"])): ?>
                    <form method="POST" class="avatar-remove" data-confirm="Hapus foto profil?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="avatar_remove">
                        <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus foto</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Data diri</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">

            <div class="form-group">
                <label for="name">Nama</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= h($user["name"]) ?>" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="bio">Bio <span class="muted">(opsional)</span></label>
                <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500" data-counter="bio-count"><?= h($user["bio"] ?? "") ?></textarea>
                <small class="muted"><span id="bio-count">0</span>/500</small>
            </div>
            <button class="btn" type="submit">Simpan profil</button>
        </form>
    </section>

    <section class="card">
        <h2>Ubah password</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <div class="form-group">
                <label for="current">Password saat ini</label>
                <input class="form-control" type="password" id="current" name="current" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label for="new">Password baru</label>
                <input class="form-control" type="password" id="new" name="new" minlength="8" autocomplete="new-password" required>
                <small class="muted">Minimal 8 karakter.</small>
            </div>
            <div class="form-group">
                <label for="confirm">Ulangi password baru</label>
                <input class="form-control" type="password" id="confirm" name="confirm" minlength="8" autocomplete="new-password" required>
            </div>
            <button class="btn" type="submit">Ubah password</button>
        </form>
    </section>

    <?php if (!$isAdmin): ?>
    <section class="card" id="reminders">
        <h2>Pengingat</h2>
        <p class="muted">Pengingat lembut untuk mencatat mood. Kamu bisa mematikannya kapan saja.</p>

        <form method="POST" class="reminder-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="prefs">
            <label class="check-row">
                <input type="checkbox" name="reminder_email" value="1" <?= (int) ($user["reminder_email"] ?? 0) === 1 ? "checked" : "" ?> <?= mail_enabled() ? "" : "disabled" ?>>
                <span>
                    Kirim email pengingat setiap malam kalau aku belum mencatat hari itu
                    <?php if (!mail_enabled()): ?>
                        <small class="muted block">Belum tersedia: pengelola belum mengaktifkan pengiriman email.</small>
                    <?php endif; ?>
                </span>
            </label>
            <?php if (mail_enabled()): ?><button class="btn btn-outline btn-small" type="submit">Simpan</button><?php endif; ?>
        </form>

        <hr class="divider">

        <div class="push-box" id="push-box"
             data-enabled="<?= push_enabled() ? "1" : "0" ?>"
             data-key="<?= h((string) env_value("VAPID_PUBLIC_KEY", "")) ?>"
             data-csrf="<?= h(csrf_token()) ?>">
            <h3>Notifikasi push <span class="admin-badge">Baru</span></h3>
            <p class="muted">
                Pengingat malam yang sampai ke perangkatmu walau MoodJourney sedang tertutup. Hanya dikirim kalau kamu belum
                mencatat hari itu, paling banyak sekali sehari.
            </p>
            <?php if (!push_enabled()): ?>
                <p class="muted">Belum tersedia: pengelola belum mengaktifkan kunci notifikasi.</p>
            <?php else: ?>
                <div class="form-actions">
                    <button class="btn btn-small" type="button" id="push-on">Aktifkan di perangkat ini</button>
                    <button class="btn btn-outline btn-small" type="button" id="push-test" hidden>Kirim tes</button>
                    <button class="btn btn-outline btn-small" type="button" id="push-off" hidden>Matikan</button>
                </div>
                <p class="muted" id="push-status" aria-live="polite"></p>
            <?php endif; ?>
        </div>

        <hr class="divider">

        <div class="browser-reminder" id="browser-reminder" data-supported="0">
            <h3>Pengingat di browser</h3>
            <p class="muted">
                Notifikasi muncul pada jam yang kamu pilih kalau kamu belum mencatat hari itu. Pengingat ini hanya aktif
                saat MoodJourney terbuka di browser atau aplikasi terpasang (tab boleh di latar belakang).
            </p>
            <div class="form-actions">
                <label class="sr-only" for="remind-time">Jam pengingat</label>
                <input class="form-control time-input" type="time" id="remind-time" value="20:00">
                <button class="btn btn-small" type="button" id="remind-on">Aktifkan</button>
                <button class="btn btn-outline btn-small" type="button" id="remind-off" hidden>Matikan</button>
            </div>
            <p class="muted" id="remind-status" aria-live="polite"></p>
        </div>

        <hr class="divider">

        <div class="install-box">
            <h3>Pasang sebagai aplikasi</h3>
            <p class="muted" id="install-hint">
                Di Chrome atau Edge, tombol pasang muncul di bawah ini. Di iPhone, buka menu Bagikan lalu pilih
                &ldquo;Tambah ke Layar Utama&rdquo;.
            </p>
            <button class="btn btn-outline btn-small" type="button" id="install-btn" hidden>Pasang aplikasi</button>
        </div>
    </section>

    <section class="card" id="pin">
        <h2>Kunci PIN</h2>
        <p class="muted">Diary itu pribadi. Dengan PIN, orang lain yang memegang HP-mu tidak bisa langsung membukanya.
            PIN diminta saat kamu membuka MoodJourney dan setelah <?= (int) (PIN_IDLE_SECONDS / 60) ?> menit tidak aktif.</p>
        <p><span class="admin-badge <?= !empty($user["has_pin"]) ? "" : "is-muted" ?>"><?= !empty($user["has_pin"]) ? "PIN aktif" : "PIN belum aktif" ?></span></p>
        <form method="POST" class="pin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pin_set">
            <div class="form-group">
                <label for="pin-new"><?= !empty($user["has_pin"]) ? "PIN baru" : "Buat PIN" ?> (4 sampai 6 angka)</label>
                <input class="form-control" type="password" id="pin-new" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="pin-confirm">Ulangi PIN</label>
                <input class="form-control" type="password" id="pin-confirm" name="pin_confirm" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="pin-password">Password akunmu</label>
                <input class="form-control" type="password" id="pin-password" name="pin_password" autocomplete="current-password" required>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit"><?= !empty($user["has_pin"]) ? "Ganti PIN" : "Aktifkan PIN" ?></button>
            </div>
        </form>
        <?php if (!empty($user["has_pin"])): ?>
        <form method="POST" class="pin-form" data-confirm="Matikan kunci PIN?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pin_remove">
            <div class="form-group">
                <label for="pin-off-password">Password (untuk mematikan PIN)</label>
                <input class="form-control" type="password" id="pin-off-password" name="pin_password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-outline btn-small" type="submit">Matikan PIN</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="card" id="datamu">
        <h2>Datamu</h2>
        <p class="muted">Unduh semua catatanmu kapan saja.</p>
        <div class="form-actions">
            <a class="btn" href="/cadangan.php">Cadangan lengkap (dengan foto)</a>
            <a class="btn btn-outline" href="/export.php?format=csv">Unduh CSV</a>
            <a class="btn btn-outline" href="/export.php?format=print" target="_blank" rel="noopener">Cetak / simpan PDF</a>
        </div>
    </section>

    <section class="card danger-zone">
        <h2>Hapus akun</h2>
        <p class="muted">
            Semua catatan diary, percakapan chat, dan profilmu akan dihapus permanen dan tidak bisa dikembalikan.
            Unduh datamu lebih dulu kalau masih ingin menyimpannya.
        </p>
        <form method="POST" data-confirm="Yakin ingin menghapus akun dan semua datamu secara permanen?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">

            <div class="form-group">
                <label for="confirm_password">Password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label for="confirm_text">Ketik <strong>HAPUS</strong> untuk melanjutkan</label>
                <input class="form-control" type="text" id="confirm_text" name="confirm_text" autocomplete="off" required>
            </div>
            <button class="btn btn-danger" type="submit">Hapus akun saya</button>
        </form>
    </section>
    <?php endif; ?>

</div>
<?php page_footer(["/assets/js/push.js"]);
