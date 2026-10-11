<?php
/**
 * Menyajikan foto profil milik pengguna yang sedang masuk (parameter v hanya untuk cache-busting).
 */
$user = current_user();
if ($user === null) {
    http_response_code(403);
    exit;
}

$row = db_one("SELECT mime, data FROM user_avatars WHERE user_id = ? LIMIT 1", "i", [(int) $user["id"]]);

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
