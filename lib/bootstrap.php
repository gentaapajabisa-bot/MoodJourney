<?php
/**
 * Titik masuk bersama: zona waktu, header keamanan, koneksi, migrasi, sesi, dan pemuat library.
 */
declare(strict_types=1);

$tz = getenv("APP_TZ");
date_default_timezone_set($tz !== false && $tz !== "" ? $tz : "Asia/Jakarta");
require_once __DIR__ . "/compat.php";
mb_internal_encoding("UTF-8");

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: same-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Cache-Control: private, no-store");

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/migrate.php";

ensure_schema(db_connect());

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/moods.php";
require_once __DIR__ . "/diary.php";
require_once __DIR__ . "/charts.php";
require_once __DIR__ . "/mail.php";
require_once __DIR__ . "/chat.php";
require_once __DIR__ . "/goals.php";
require_once __DIR__ . "/achievements.php";
require_once __DIR__ . "/prompts.php";
require_once __DIR__ . "/safety.php";
require_once __DIR__ . "/bot.php";
require_once __DIR__ . "/photos.php";
require_once __DIR__ . "/layout.php";

function render_fatal(Throwable $e): void
{
    error_log("[MoodJourney] " . get_class($e) . ": " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debug = env_value("APP_DEBUG", "0") === "1";
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Terjadi kesalahan — MoodJourney</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <main class="container narrow">
        <section class="card">
            <h1>Ada yang tidak beres</h1>
            <p class="muted">Halaman ini gagal dimuat. Coba muat ulang beberapa saat lagi. Kalau masalahnya berulang, hubungi admin.</p>
            <?php if ($debug): ?>
                <pre class="debug"><?= h($e->getMessage()) ?>

<?= h(basename($e->getFile()) . ":" . $e->getLine()) ?></pre>
            <?php endif; ?>
            <a class="btn" href="/">Kembali ke beranda</a>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

set_exception_handler("render_fatal");
