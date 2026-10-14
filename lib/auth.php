<?php

require_once __DIR__ . "/session.php";

/** Data pengguna yang sedang login (di-cache per permintaan). */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (!isset($_SESSION["user_id"])) {
        return $user = null;
    }
    $row = db_one(
        "SELECT id, name, email, role, bio, is_active, reminder_email, avatar_v, (pin_hash IS NOT NULL) AS has_pin, created_at FROM users WHERE id = ? LIMIT 1",
        "i",
        [(int) $_SESSION["user_id"]]
    );
    if (!$row || (int) $row["is_active"] !== 1) {
        // akun dihapus atau dinonaktifkan: akhiri sesi
        $_SESSION = [];
        return $user = null;
    }
    $_SESSION["name"] = $row["name"];
    $_SESSION["role"] = $row["role"];
    return $user = $row;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect("/login.php");
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user["role"] !== "admin") {
        redirect("/dashboard.php");
    }
    return $user;
}

/** Halaman khusus tamu (login, daftar): pengguna yang sudah masuk diarahkan ke dashboard. */
function redirect_if_logged_in(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(home_for($user));
    }
}

/** Admin atau konselor. */
function require_staff(): array
{
    $user = require_login();
    if (!is_staff($user["role"])) {
        redirect("/dashboard.php");
    }
    return $user;
}

/** Berapa detik tidak aktif sebelum layar PIN muncul lagi. */
const PIN_IDLE_SECONDS = 600;

/** Tandai sesi ini sudah dibuka (setelah login dengan password atau PIN benar). */
function pin_unlock(): void
{
    $_SESSION["pin_at"] = time();
}

/** Apakah akun ini punya PIN dan sesinya sedang terkunci? */
function pin_locked(array $user): bool
{
    if (empty($user["has_pin"])) {
        return false;
    }
    $at = (int) ($_SESSION["pin_at"] ?? 0);
    return $at === 0 || (time() - $at) > PIN_IDLE_SECONDS;
}

/** Halaman khusus pengguna biasa: staf diarahkan ke halamannya sendiri. */
function require_member(): array
{
    $user = require_login();
    if ($user["role"] !== "user") {
        redirect(home_for($user));
    }
    if (pin_locked($user)) {
        $next = (string) ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET" ? (string) ($_SERVER["REQUEST_URI"] ?? "/dashboard.php") : "/dashboard.php";
        redirect("/kunci.php?next=" . rawurlencode($next));
    }
    if (!empty($user["has_pin"])) {
        $_SESSION["pin_at"] = time(); // aktivitas memperpanjang sesi
    }
    return $user;
}
