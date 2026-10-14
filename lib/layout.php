<?php
/**
 * Kerangka halaman: head, navigasi, pesan flash, dan footer.
 */

function unread_count_for(array $user): int
{
    if ($user["role"] === "admin") {
        return (int) db_value(
            "SELECT COUNT(*) FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE u.role = 'user' AND m.is_read = 0",
            "",
            [],
            0
        );
    }
    if ($user["role"] === "counselor") {
        return (int) db_value(
            "SELECT COUNT(*) FROM messages m
             JOIN users u ON u.id = m.sender_id
             JOIN chat_rooms r ON r.id = m.chat_room_id
             WHERE u.role = 'user' AND m.is_read = 0
               AND EXISTS (SELECT 1 FROM support_requests sr
                           WHERE sr.user_id = r.user_id AND sr.counselor_id = ? AND sr.status = 'active')",
            "i",
            [(int) $user["id"]],
            0
        );
    }
    return (int) db_value(
        "SELECT COUNT(*) FROM messages m
         JOIN chat_rooms r ON r.id = m.chat_room_id
         JOIN users u ON u.id = m.sender_id
         WHERE r.user_id = ? AND u.role IN ('admin', 'counselor') AND m.is_read = 0",
        "i",
        [(int) $user["id"]],
        0
    );
}

/**
 * @param array{nav?:string,active?:string,body?:string,desc?:string} $opts
 *   nav: "app" | "admin" | "public" | "none"
 */
function page_header(string $title, array $opts = []): void
{
    $nav    = $opts["nav"] ?? "app";
    $active = $opts["active"] ?? "";
    $user   = current_user();
    $desc   = $opts["desc"] ?? "Catat mood harian, kenali polanya, dan jaga kebiasaan baik.";
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= h($desc) ?>">
    <meta name="color-scheme" content="light dark">
    <title><?= h($title) ?> — MoodJourney</title>

    <script>
        (function () {
            document.documentElement.classList.add("js");
            try {
                var saved = localStorage.getItem("mj-theme");
                var dark = saved ? saved === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
                document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
            } catch (e) {}
        })();
    </script>

    <meta name="theme-color" content="#6d5ce8">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/logo.svg">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<?php
    // satu query: catatan terbaru dalam 3 hari terakhir menentukan "sudah mencatat hari ini" dan warna latar
    $loggedToday = "";
    $auraTheme = "none";
    if ($user && $user["role"] === "user") {
        $latest = db_one(
            "SELECT mood, diary_date FROM diary WHERE user_id = ? AND diary_date >= ? ORDER BY diary_date DESC, id DESC LIMIT 1",
            "is",
            [(int) $user["id"], date("Y-m-d", strtotime("-2 days"))]
        );
        $loggedToday = $latest && $latest["diary_date"] === date("Y-m-d") ? "1" : "0";
        $auraTheme = aura_theme_for_mood($latest["mood"] ?? null);
    }
?>
<body class="<?= h($opts["body"] ?? "") ?>" data-mood="<?= h($auraTheme) ?>"<?= $loggedToday !== "" ? ' data-logged-today="' . $loggedToday . '"' : "" ?>>

<div class="aura" aria-hidden="true"><i></i><i></i><i></i></div>

<a class="skip-link" href="#main">Lewati ke konten</a>

<?php if ($nav !== "none"): ?>
<nav class="navbar" aria-label="Navigasi utama">
    <a href="<?= $user ? h(home_for($user)) : "/" ?>" class="logo" aria-label="MoodJourney, beranda">
        <svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><defs><linearGradient id="lg-a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9b8cf5"/><stop offset="1" stop-color="#6d5ce8"/></linearGradient><linearGradient id="lg-b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8fd5b8"/><stop offset="1" stop-color="#7fb8f0"/></linearGradient></defs><path d="M31 29 C 24 12, 6 8, 5 21 C 4 31, 15 37, 31 34 Z" fill="url(#lg-a)"/><path d="M33 29 C 40 12, 58 8, 59 21 C 60 31, 49 37, 33 34 Z" fill="url(#lg-a)"/><path d="M31 36 C 20 36, 11 42, 14 51 C 17 58, 28 54, 31 43 Z" fill="url(#lg-b)"/><path d="M33 36 C 44 36, 53 42, 50 51 C 47 58, 36 54, 33 43 Z" fill="url(#lg-b)"/><circle cx="16" cy="22" r="3.2" fill="#fff" opacity=".55"/><circle cx="48" cy="22" r="3.2" fill="#fff" opacity=".55"/><rect x="30" y="22" width="4" height="26" rx="2" fill="#3d3566"/><path d="M31 23 C 29 16, 25 13, 22 12 M33 23 C 35 16, 39 13, 42 12" fill="none" stroke="#3d3566" stroke-width="1.6" stroke-linecap="round"/></svg>
        <span class="logo-text">Mood<span>Journey</span></span>
    </a>

    <button class="menu-toggle" type="button" aria-label="Buka menu" aria-expanded="false">☰</button>

    <div class="nav-links">
        <?php if ($nav === "app" && $user): $unread = unread_count_for($user); ?>
            <a href="/dashboard.php" <?= $active === "dashboard" ? 'aria-current="page"' : "" ?>>Dashboard</a>
            <a href="/history.php" <?= $active === "history" ? 'aria-current="page"' : "" ?>>Riwayat</a>
            <a href="/statistics.php" <?= $active === "statistics" ? 'aria-current="page"' : "" ?>>Wawasan</a>
            <a href="/tenang.php" <?= $active === "calm" ? 'aria-current="page"' : "" ?>>Tenang</a>
            <a href="/dukungan.php" <?= $active === "support" ? 'aria-current="page"' : "" ?>>
                Dukungan<span class="badge" data-unread-badge <?= $unread > 0 ? "" : "hidden" ?>><?= $unread ?></span>
            </a>
            <a href="/profile.php" class="nav-profile" <?= $active === "profile" ? 'aria-current="page"' : "" ?>><?= avatar_html($user, 26) ?> Profil</a>
            <a href="/diary.php" class="btn btn-small">Catat mood</a>
        <?php elseif ($nav === "admin" && $user): $unread = unread_count_for($user); ?>
            <a href="/admin/" <?= $active === "admin" ? 'aria-current="page"' : "" ?>>Ringkasan
                <span class="badge" data-unread-badge <?= $unread > 0 ? "" : "hidden" ?>><?= $unread ?></span>
            </a>
            <a href="/admin/laporan.php" <?= $active === "admin-report" ? 'aria-current="page"' : "" ?>>Laporan</a>
            <a href="/admin/status.php" <?= $active === "admin-status" ? 'aria-current="page"' : "" ?>>Status</a>
            <a href="/profile.php" class="nav-profile" <?= $active === "profile" ? 'aria-current="page"' : "" ?>><?= avatar_html($user, 26) ?> Profil</a>
            <span class="admin-badge">Admin</span>
        <?php elseif ($nav === "counselor" && $user): $unread = unread_count_for($user); ?>
            <a href="/konselor/" <?= $active === "counselor" ? 'aria-current="page"' : "" ?>>Antrean
                <span class="badge" data-unread-badge <?= $unread > 0 ? "" : "hidden" ?>><?= $unread ?></span>
            </a>
            <a href="/profile.php" class="nav-profile" <?= $active === "profile" ? 'aria-current="page"' : "" ?>><?= avatar_html($user, 26) ?> Profil</a>
            <span class="admin-badge">Konselor</span>
        <?php elseif ($nav === "public"): ?>
            <a href="/login.php">Masuk</a>
            <a href="/register.php" class="btn btn-small">Daftar</a>
        <?php endif; ?>

        <button class="theme-toggle" type="button" aria-label="Ganti tema terang atau gelap" title="Ganti tema">
            <span class="theme-icon" aria-hidden="true"></span>
        </button>

        <?php if ($user && $nav !== "public"): ?>
            <a href="/logout.php">Keluar</a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>

<main id="main">
<?php
    $items = flash_take();
    if ($items):
        echo '<div class="flash-wrap">';
        foreach ($items as $f) {
            $type = in_array($f["type"], ["success", "error", "info"], true) ? $f["type"] : "info";
            $auto = $type === "success" ? " auto-hide" : "";
            echo '<div class="notice notice-' . $type . $auto . '" role="' . ($type === "error" ? "alert" : "status") . '">'
               . h($f["message"]) . '</div>';
        }
        echo '</div>';
    endif;
}

function page_footer(array $scripts = []): void
{
    ?>
</main>

<footer class="footer">
    <div class="footer-links">
        <a href="/bantuan.php">Bantuan &amp; kontak darurat</a>
        <a href="/privasi.php">Privasi</a>
    </div>
    <p>© <?= date("Y") ?> MoodJourney. Kenali dirimu lewat setiap cerita.</p>
</footer>

<?php
// perayaan naik tahap (diatur setelah menyimpan catatan), tampil sekali
$su = $_SESSION["stage_up"] ?? null;
if (is_array($su)) {
    unset($_SESSION["stage_up"]);
    echo stage_up_overlay_html((string) ($su["from"] ?? ""), (string) ($su["to"] ?? ""), (array) ($su["colors"] ?? BUTTERFLY_DEFAULT_COLORS), true);
}
?>
<script src="/assets/js/app.js" defer></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= h($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Sub-navigasi untuk halaman Wawasan. $active: stats | weekly | goals | badges | butterfly | year | pattern | share */
function insights_tabs(string $active): void
{
    $tabs = [
        "stats"   => ["/statistics.php", "Statistik"],
        "weekly"  => ["/ringkasan.php", "Ringkasan mingguan"],
        "goals"   => ["/target.php", "Target"],
        "badges"  => ["/pencapaian.php", "Pencapaian"],
        "butterfly" => ["/metamorfosis.php", "Metamorfosis"],
        "year"    => ["/setahun.php", "Setahun"],
        "challenge" => ["/tantangan.php", "Tantangan"],
        "collection" => ["/koleksi.php", "Koleksi"],
        "letters" => ["/surat.php", "Surat"],
        "share"   => ["/bagikan.php", "Bagikan"],
        "pattern" => ["/pola.php", "Pola"],
    ];
    echo '<nav class="segmented insights-tabs" aria-label="Wawasan">';
    foreach ($tabs as $key => [$href, $label]) {
        echo '<a href="' . $href . '"' . ($key === $active ? ' aria-current="true"' : "") . '>' . h($label) . '</a>';
    }
    echo '</nav>';
}

/** Jenis navigasi sesuai peran pengguna (null = belum masuk). */
function nav_for(?array $user): string
{
    if ($user === null) {
        return "public";
    }
    return ["admin" => "admin", "counselor" => "counselor"][$user["role"]] ?? "app";
}
