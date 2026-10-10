<?php
header("Content-Type: application/json; charset=UTF-8");

$user = current_user();
if ($user === null || $user["role"] !== "user") {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit;
}
if (!is_post()) {
    http_response_code(405);
    echo json_encode(["error" => "method"]);
    exit;
}

$sent = $_POST["_csrf"] ?? "";
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    http_response_code(403);
    echo json_encode(["error" => "csrf"]);
    exit;
}

$bucket = "bot:" . (int) $user["id"];
if (rate_count($bucket, 60) >= 20) {
    http_response_code(429);
    echo json_encode(["reply" => "Pelan-pelan ya. Coba lagi sebentar lagi.", "links" => [], "crisis" => false]);
    exit;
}
rate_hit($bucket);

$q = mb_substr(trim((string) ($_POST["q"] ?? "")), 0, 300);
echo json_encode(bot_answer($q), JSON_UNESCAPED_UNICODE);
