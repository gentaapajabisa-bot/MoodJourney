<?php
redirect_if_logged_in();

$token = (string) ($_GET["token"] ?? $_POST["token"] ?? "");
$row = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $row = db_one(
        "SELECT r.id, r.user_id FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1
         LIMIT 1",
        "s",
        [hash("sha256", $token)]
    );
}

$error = "";

if ($row && is_post()) {
    csrf_verify("/reset.php?token=" . urlencode($token));

    $password = (string) ($_POST["password"] ?? "");
    $confirm = (string) ($_POST["confirm"] ?? "");

    if (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        db_exec(
            "UPDATE users SET password = ? WHERE id = ?",
            "si",
            [password_hash($password, PASSWORD_DEFAULT), (int) $row["user_id"]]
        );
        db_exec("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", "i", [(int) $row["user_id"]]);
        redirect("/login.php?reset=1");
    }
}

page_header("Password baru", ["nav" => "public"]);
?>
<div class="container">
    <section class="card form-card">

        <?php if (!$row): ?>
            <h1>Tautan tidak berlaku</h1>
            <p class="muted">Tautan reset ini salah, sudah dipakai, atau sudah kedaluwarsa. Minta tautan baru untuk melanjutkan.</p>
            <a class="btn full" href="/forgot.php">Minta tautan baru</a>
        <?php else: ?>
            <h1>Buat password baru</h1>

            <?php if ($error): ?>
                <div class="notice notice-error" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div class="form-group">
                    <label for="password">Password baru</label>
                    <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
                </div>
                <div class="form-group">
                    <label for="confirm">Ulangi password baru</label>
                    <input class="form-control" type="password" id="confirm" name="confirm" minlength="8" autocomplete="new-password" required>
                </div>

                <button class="btn full" type="submit">Simpan password</button>
            </form>
        <?php endif; ?>

    </section>
</div>
<?php page_footer();
