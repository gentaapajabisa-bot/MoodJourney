<?php
redirect_if_logged_in();

$error = "";
$name = "";
$email = "";

if (is_post()) {
    csrf_verify("/register.php");

    $name = trim((string) ($_POST["name"] ?? ""));
    $email = mb_strtolower(trim((string) ($_POST["email"] ?? "")));
    $password = (string) ($_POST["password"] ?? "");

    $bucket = "register:ip:" . client_ip();

    if (rate_count($bucket, 3600) >= 5) {
        $error = "Terlalu banyak pendaftaran dari jaringan ini. Coba lagi nanti.";
    } elseif ($name === "" || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $error = "Nama dan email harus diisi dengan benar.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif (db_one("SELECT id FROM users WHERE email = ? LIMIT 1", "s", [$email])) {
        $error = "Email tersebut sudah terdaftar.";
    } else {
        db_exec(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
            "sss",
            [$name, $email, password_hash($password, PASSWORD_DEFAULT)]
        );
        rate_hit($bucket);
        redirect("/login.php?registered=1");
    }
}

page_header("Daftar", ["nav" => "public"]);
?>
<div class="container">
    <section class="card form-card">

        <h1>Buat akun</h1>
        <p class="muted">Gratis, dan datamu hanya untukmu.</p>

        <?php if ($error): ?>
            <div class="notice notice-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Nama</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= h($name) ?>" maxlength="100" autocomplete="name" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= h($email) ?>" maxlength="150" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" aria-describedby="pw-hint" required>
                <small id="pw-hint" class="muted">Minimal 8 karakter.</small>
            </div>

            <button class="btn full" type="submit">Daftar</button>
        </form>

        <p class="muted form-foot">
            Sudah punya akun? <a class="link" href="/login.php">Masuk di sini</a>
        </p>

    </section>
</div>
<?php page_footer();
