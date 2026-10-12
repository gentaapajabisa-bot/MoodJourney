<?php
/**
 * Satu-satunya fungsi serverless. Semua URL diarahkan ke sini oleh vercel.json
 * lalu diteruskan ke file di lib/pages/.
 *
 * Alasannya: paket Vercel Hobby dibatasi 12 fungsi per deployment. Dengan satu router,
 * jumlah halaman bisa bertambah tanpa menambah fungsi. URL lama (/login.php, /dashboard.php,
 * /admin/index.php, dst.) tetap berlaku.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . "/lib/bootstrap.php";

$route = $_GET["_r"] ?? null;
if ($route === null) {
    $path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
    $route = ltrim(is_string($path) ? $path : "/", "/");
}
unset($_GET["_r"]);

$route = trim(rawurldecode((string) $route), "/");
$route = preg_replace('/\.php$/', "", $route);
if ($route === "" || $route === "index" || $route === "api/index") {
    $route = "home";
}

/* URL => file di lib/pages/ (tanpa .php) */
$routes = [
    "home"           => "home",
    "login"          => "login",
    "register"       => "register",
    "logout"         => "logout",
    "forgot"         => "forgot",
    "reset"          => "reset",
    "dashboard"      => "dashboard",
    "diary"          => "diary",
    "diary_delete"   => "diary_delete",
    "history"        => "history",
    "statistics"     => "statistics",
    "ringkasan"      => "weekly",
    "target"         => "goals",
    "pencapaian"     => "achievements",
    "metamorfosis"   => "butterfly",
    "pola"           => "patterns",
    "bagikan"        => "share",
    "setahun"        => "year",
    "cron/reminder"  => "cron_reminder",
    "dukungan"       => "support",
    "bot"            => "bot",
    "tenang"         => "calm",
    "photo"          => "photo",
    "avatar"         => "avatar",
    "support/action" => "support_action",
    "konselor"       => "counselor/index",
    "konselor/index" => "counselor/index",
    "konselor/chat"  => "admin/chat",
    "profile"        => "profile",
    "export"         => "export",
    "chat"           => "chat",
    "chat/unread"    => "chat_unread",
    "chat/poll"      => "chat_poll",
    "bantuan"        => "help",
    "privasi"        => "privacy",
    "admin"          => "admin/index",
    "admin/index"    => "admin/index",
    "admin/chat"     => "admin/chat",
    "admin/user"     => "admin/user",
    "admin/profil"   => "admin/profile",
];

$page = $routes[$route] ?? null;

if ($page === null) {
    http_response_code(404);
    $page = "404";
}

try {
    require $root . "/lib/pages/" . $page . ".php";
} catch (Throwable $e) {
    render_fatal($e);
}
