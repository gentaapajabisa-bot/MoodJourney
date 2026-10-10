<?php
redirect_if_logged_in();

$sent = false;

if (is_post()) {
    csrf_verify("/forgot.php");

    $email = mb_strtolower(trim((string) ($_POST["email"] ?? "")));
    $bucket = "forgot:ip:" . client_ip();

    if (rate_count($bucket, 3600) >= 5) {
        flash("error", "Terlalu banyak permintaan. Coba lagi nanti.");
        redirect("/forgot.php");
    }
    rate_hit($bucket);

    $user = filter_var($email, FILTER_VALIDATE_EMAIL)
        ? db_one("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1", "s", [$email])
        : null;

    if ($user) {
        if (mail_enabled()) {
            $token = bin2hex(random_bytes(32));
            db_exec(
                "INSERT INTO password_resets (user_id, token_hash, expires_at, handled_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())",
                "is",
                [(int) $user["id"], hash("sha256", $token)]
            );
            $link = app_base_url() . "/reset.php?token=" . $token;
            send_mail(
                $user["email"],
                "Atur ulang password MoodJourney",
                "<p>Halo " . h($user["name"]) . ",</p>"
                . "<p>Klik tautan berikut untuk membuat password baru. Tautan berlaku 1 jam.</p>"
                . '<p><a href="' . h($link) . '">' . h($link) . "</a></p>"
                . "<p>Kalau kamu tidak meminta ini, abaikan email ini.</p>"
            );
        } else {
            // Tanpa layanan email: permintaan masuk ke panel admin, yang membuat tautan reset.
            $pending = db_value(
                "SELECT COUNT(*) FROM password_resets
                 WHERE user_id = ? AND token_hash IS NULL AND handled_at IS NULL",
                "i",
                [(int) $user["id"]],
                0
            );
            if ((int) $pending === 0) {
                db_exec("INSERT INTO password_resets (user_id) VALUES (?)", "i", [(int) $user["id"]]);
            }
        }
    }
    $sent = true;
}

page_header("Lupa password", ["nav" => "public"]);
?>
<div class="container">
    <section class="card form-card">

        <h1>Lupa password</h1>

        <?php if ($sent): ?>
            <div class="notice notice-success" role="status">
                <?php if (mail_enabled()): ?>
                    Kalau email itu terdaftar, tautan untuk membuat password baru sudah dikirim. Cek kotak masuk dan folder spam.
                <?php else: ?>
                    Permintaanmu sudah dicatat. Admin akan mengirimkan tautan reset lewat kontak yang kamu berikan,
                    atau hubungi admin langsung supaya lebih cepat.
                <?php endif; ?>
            </div>
            <a class="btn btn-outline full" href="/login.php">Kembali ke halaman masuk</a>
        <?php else: ?>
            <p class="muted">Masukkan email akunmu. Kami akan membantu mengatur ulang passwordnya.</p>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" autocomplete="email" required autofocus>
                </div>
                <button class="btn full" type="submit">Kirim permintaan</button>
            </form>

            <p class="muted form-foot"><a class="link" href="/login.php">Kembali ke halaman masuk</a></p>
        <?php endif; ?>

    </section>
</div>
<?php page_footer();
