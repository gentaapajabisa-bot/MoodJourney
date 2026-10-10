<?php
redirect_if_logged_in();

const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW = 900; // 15 menit

$error = "";
$email = "";

if (is_post()) {
    csrf_verify("/login.php");

    $email = mb_strtolower(trim((string) ($_POST["email"] ?? "")));
    $password = (string) ($_POST["password"] ?? "");

    $ipBucket = "login:ip:" . client_ip();
    $mailBucket = "login:mail:" . $email;

    if (
        rate_count($ipBucket, LOGIN_WINDOW) >= LOGIN_MAX_FAILS * 3
        || rate_count($mailBucket, LOGIN_WINDOW) >= LOGIN_MAX_FAILS
    ) {
        $error = "Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.";
    } else {
        $user = db_one(
            "SELECT id, name, password, role, is_active FROM users WHERE email = ? LIMIT 1",
            "s",
            [$email]
        );

        if ($user && password_verify($password, $user["password"])) {
            if ((int) $user["is_active"] !== 1) {
                $error = "Akun ini dinonaktifkan. Hubungi admin untuk bantuan.";
            } else {
                session_regenerate_id(true);
                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];
                unset($_SESSION["csrf"]);

                rate_clear($mailBucket);
                db_exec("UPDATE users SET last_login_at = NOW() WHERE id = ?", "i", [(int) $user["id"]]);

                redirect($user["role"] === "admin" ? "/admin/" : "/dashboard.php");
            }
        } else {
            rate_hit($ipBucket);
            rate_hit($mailBucket);
            $error = "Email atau password salah.";
        }
    }
}

page_header("Masuk", ["nav" => "public"]);
?>
<div class="container">
    <section class="card form-card">

        <h1>Masuk</h1>
        <p class="muted">Selamat datang kembali.</p>

        <?php if (isset($_GET["registered"])): ?>
            <div class="notice notice-success auto-hide">Akun berhasil dibuat. Silakan masuk.</div>
        <?php endif; ?>
        <?php if (isset($_GET["reset"])): ?>
            <div class="notice notice-success auto-hide">Password berhasil diubah. Silakan masuk dengan password baru.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= h($email) ?>" autocomplete="email" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <button class="btn full" type="submit">Masuk</button>
        </form>

        <p class="muted form-foot">
            <a href="/forgot.php">Lupa password?</a><br>
            Belum punya akun? <a class="link" href="/register.php">Daftar sekarang</a>
        </p>

    </section>
</div>
<?php page_footer();
