<?php
header("Content-Type: application/json; charset=UTF-8");

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit;
}

$isAdmin = $user["role"] === "admin";
$role = $isAdmin ? "admin" : "user";
$after = max(0, (int) ($_GET["after"] ?? 0));

if ($isAdmin) {
    $roomId = (int) ($_GET["room"] ?? 0);
    $room = $roomId > 0 ? db_one("SELECT id, status FROM chat_rooms WHERE id = ? LIMIT 1", "i", [$roomId]) : null;
} else {
    $room = chat_get_room((int) $user["id"], false);
    $roomId = $room ? (int) $room["id"] : 0;
}

if (!$room) {
    echo json_encode(["messages" => [], "last_id" => $after, "status" => "open", "unread" => unread_count_for($user)]);
    exit;
}

$rows = chat_fetch($roomId, $after);
if ($rows) {
    chat_mark_read($roomId, $role);
}

$html = [];
$lastId = $after;
foreach ($rows as $m) {
    $html[] = chat_message_html($m, $role, $isAdmin);
    $lastId = max($lastId, (int) $m["id"]);
}

echo json_encode([
    "messages" => $html,
    "last_id"  => $lastId,
    "status"   => $room["status"],
    "unread"   => unread_count_for($user),
], JSON_UNESCAPED_UNICODE);
