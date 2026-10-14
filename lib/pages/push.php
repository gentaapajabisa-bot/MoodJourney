<?php
/** Simpan atau hapus langganan notifikasi push perangkat ini (JSON). */
header("Content-Type: application/json; charset=UTF-8");

$user = current_user();
if ($user === null || $user["role"] !== "user") {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "unauthorized"]);
    exit;
}
if (!is_post()) {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "method"]);
    exit;
}
$sent = $_POST["_csrf"] ?? "";
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "csrf"]);
    exit;
}

$userId = (int) $user["id"];
$action = (string) ($_POST["action"] ?? "");
$endpoint = (string) ($_POST["endpoint"] ?? "");

function push_fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg]);
    exit;
}

if ($action === "subscribe") {
    $p256dh = (string) ($_POST["p256dh"] ?? "");
    $auth = (string) ($_POST["auth"] ?? "");
    if (!push_enabled()) {
        push_fail("push_disabled", 503);
    }
    if (strpos($endpoint, "https://") !== 0 || strlen($endpoint) > 1000
        || strlen(b64u_decode($p256dh)) !== 65 || strlen(b64u_decode($auth)) < 8 || strlen($auth) > 60) {
        push_fail("invalid");
    }
    if (rate_count("push:" . $userId, 3600) >= 20) {
        push_fail("rate", 429);
    }
    rate_hit("push:" . $userId);
    if (db_value("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?", "i", [$userId], 0) >= 10) {
        push_fail("too_many");
    }
    db_exec(
        "REPLACE INTO push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth) VALUES (?, ?, ?, ?, ?)",
        "issss",
        [$userId, hash("sha256", $endpoint), $endpoint, $p256dh, $auth]
    );
    echo json_encode(["ok" => true]);
    exit;
}

if ($action === "unsubscribe") {
    db_exec("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?", "is", [$userId, hash("sha256", $endpoint)]);
    echo json_encode(["ok" => true]);
    exit;
}

if ($action === "test") {
    if (!push_enabled()) {
        push_fail("push_disabled", 503);
    }
    if (rate_count("pushtest:" . $userId, 600) >= 3) {
        push_fail("rate", 429);
    }
    rate_hit("pushtest:" . $userId);
    $n = push_send_user($userId, ["title" => "MoodJourney 🦋", "body" => "Notifikasi berhasil. Kupu-kupumu menyapa!", "url" => "/dashboard.php"]);
    echo json_encode(["ok" => $n > 0, "sent" => $n]);
    exit;
}

push_fail("unknown");
