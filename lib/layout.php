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
            try {
                var saved = localStorage.getItem("mj-theme");
                var dark = saved ? saved === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
                document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
            } catch (e) {}
        })();
    </script>

    <meta name="theme-color" content="#6d5ce8">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<?php
    $loggedToday = "";
    if ($user && $user["role"] === "user") {
        $loggedToday = db_one(
            "SELECT 1 AS x FROM diary WHERE user_id = ? AND diary_date = ? LIMIT 1",
            "is",
            [(int) $user["id"], date("Y-m-d")]
        ) ? "1" : "0";
    }
?>
<body class="<?= h($opts["body"] ?? "") ?>"<?= $loggedToday !== "" ? ' data-logged-today="' . $loggedToday . '"' : "" ?>>

<a class="skip-link" href="#main">Lewati ke konten</a>

<?php if ($nav !== "none"): ?>
<nav class="navbar" aria-label="Navigasi utama">
    <a href="<?= $user ? h(home_for($user)) : "/" ?>" class="logo">
        Mood<span>Journey</span>
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
            <a href="/profile.php" <?= $active === "profile" ? 'aria-current="page"' : "" ?>>Profil</a>
            <a href="/diary.php" class="btn btn-small">Catat mood</a>
        <?php elseif ($nav === "admin" && $user): $unread = unread_count_for($user); ?>
            <a href="/admin/" <?= $active === "admin" ? 'aria-current="page"' : "" ?>>Ringkasan
                <span class="badge" data-unread-badge <?= $unread > 0 ? "" : "hidden" ?>><?= $unread ?></span>
            </a>
            <a href="/profile.php" <?= $active === "profile" ? 'aria-current="page"' : "" ?>>Profil</a>
            <span class="admin-badge">Admin</span>
        <?php elseif ($nav === "counselor" && $user): $unread = unread_count_for($user); ?>
            <a href="/konselor/" <?= $active === "counselor" ? 'aria-current="page"' : "" ?>>Antrean
                <span class="badge" data-unread-badge <?= $unread > 0 ? "" : "hidden" ?>><?= $unread ?></span>
            </a>
            <a href="/profile.php" <?= $active === "profile" ? 'aria-current="page"' : "" ?>>Profil</a>
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

<script src="/assets/js/app.js" defer></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= h($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Sub-navigasi untuk halaman Wawasan. $active: stats | weekly | goals | badges */
function insights_tabs(string $active): void
{
    $tabs = [
        "stats"   => ["/statistics.php", "Statistik"],
        "weekly"  => ["/ringkasan.php", "Ringkasan mingguan"],
        "goals"   => ["/target.php", "Target"],
        "badges"  => ["/pencapaian.php", "Pencapaian"],
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
