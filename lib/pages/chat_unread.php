<?php
header("Content-Type: text/plain; charset=UTF-8");

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo "0";
    exit;
}
echo unread_count_for($user);
