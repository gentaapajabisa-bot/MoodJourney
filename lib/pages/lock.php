<?php
/** Layar kunci PIN. Dibuka dengan PIN 4 sampai 6 angka; lupa PIN = masuk ulang dengan password. */
$user = require_login();
if ($user["role"] !== "user" || empty($user["has_pin"])) {
    redirect(home_for($user));
}
$userId = (int) $user["id"];
$next = safe_return((string) ($_GET["next"] ?? $_POST["next"] ?? ""), "/dashboard.php");
if (str_starts_with($next, "/kunci")) {
    $next = "/dashboard.php";
}

$error = "";
if (is_post()) {
    csrf_verify("/kunci.php?next=" . rawurlencode($next));
    $bucket = "pin:" . $userId;
    $pin = (string) ($_POST["pin"] ?? "");

    if (rate_count($bucket, 600) >= 5) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash("error", "Terlalu banyak PIN yang salah. Masuk lagi dengan password.");
        redirect("/login.php");
    }
    $hash = (string) db_value("SELECT pin_hash FROM users WHERE id = ?", "i", [$userId], "");
    if ($hash !== "" && preg_match('/^\d{4,6}$/', $pin) && password_verify($pin, $hash)) {
        rate_clear($bucket);
        pin_unlock();
        redirect($next);
    }
    rate_hit($bucket);
    $left = max(0, 5 - rate_count($bucket, 600));
    $error = "PIN salah." . ($left > 0 ? " Sisa percobaan: " . $left . "." : "");
}

page_header("Layar kunci", ["nav" => "none", "body" => "lock-page"]);
?>
<div class="container narrow lock-wrap">
    <section class="card lock-card">
        <div class="lock-art" aria-hidden="true">🦋</div>
        <h1>Halo, <?= h(trim(explode(" ", trim((string) $user["name"]))[0])) ?></h1>
        <p class="muted">Masukkan PIN untuk membuka MoodJourney.</p>

        <?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>

        <form method="POST" class="lock-form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <label class="sr-only" for="pin">PIN</label>
            <input class="form-control lock-input" type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{4,6}"
                   maxlength="6" minlength="4" autocomplete="off" placeholder="••••" autofocus required>
            <button class="btn" type="submit">Buka</button>
        </form>

        <p class="muted card-foot">Lupa PIN? <a class="link" href="/logout.php">Masuk lagi dengan password</a>. Setelah itu PIN bisa diganti di Profil.</p>
    </section>
</div>
<?php page_footer();
