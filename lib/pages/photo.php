<?php
/**
 * Menyajikan foto catatan. Hanya pemiliknya yang bisa melihat (admin dan konselor tidak bisa).
 */
$user = current_user();
if ($user === null || $user["role"] !== "user") {
    http_response_code(403);
    exit;
}

$id = (int) ($_GET["id"] ?? 0);
$row = $id > 0
    ? db_one("SELECT mime, data FROM diary_photos WHERE id = ? AND user_id = ? LIMIT 1", "ii", [$id, (int) $user["id"]])
    : null;

if (!$row || !in_array($row["mime"], ["image/jpeg", "image/png", "image/webp"], true)) {
    http_response_code(404);
    exit;
}

header("Content-Type: " . $row["mime"]);
header("Content-Length: " . strlen($row["data"]));
header("Content-Disposition: inline");
header("Cache-Control: private, max-age=86400");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
echo $row["data"];
