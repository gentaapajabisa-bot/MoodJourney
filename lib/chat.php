<?php
/**
 * Fungsi bersama untuk chat pengguna <-> admin.
 */

const CHAT_MAX_LENGTH = 2000;

function chat_get_room(int $userId, bool $create = true): ?array
{
    $room = db_one("SELECT id, status FROM chat_rooms WHERE user_id = ? LIMIT 1", "i", [$userId]);
    if ($room || !$create) {
        return $room;
    }
    [, $newId] = db_exec("INSERT INTO chat_rooms (user_id) VALUES (?)", "i", [$userId]);
    return ["id" => $newId, "status" => "open"];
}

function chat_send(int $roomId, int $senderId, string $text, bool $reopen): void
{
    db_exec(
        "INSERT INTO messages (chat_room_id, sender_id, message) VALUES (?, ?, ?)",
        "iis",
        [$roomId, $senderId, $text]
    );
    if ($reopen) {
        db_exec("UPDATE chat_rooms SET last_message_at = NOW(), status = 'open' WHERE id = ?", "i", [$roomId]);
    } else {
        db_exec("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?", "i", [$roomId]);
    }
}

/** Tandai pesan dari pihak lawan sebagai sudah dibaca. $viewerRole: "user" atau "admin". */
function chat_mark_read(int $roomId, string $viewerRole): void
{
    $otherRole = $viewerRole === "admin" ? "user" : "admin";
    db_exec(
        "UPDATE messages m JOIN users u ON u.id = m.sender_id
         SET m.is_read = 1
         WHERE m.chat_room_id = ? AND u.role = ? AND m.is_read = 0",
        "is",
        [$roomId, $otherRole]
    );
}

function chat_fetch(int $roomId, int $afterId = 0, int $limit = 300): array
{
    $rows = db_all(
        "SELECT m.id, m.message, m.created_at, u.name, u.role
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.chat_room_id = ? AND m.id > ?
         ORDER BY m.id DESC LIMIT ?",
        "iii",
        [$roomId, $afterId, $limit]
    );
    return array_reverse($rows);
}

function chat_message_html(array $m, string $viewerRole, bool $showName = false): string
{
    $mine = $m["role"] === $viewerRole;
    $ts = strtotime($m["created_at"]);
    $time = date("Y-m-d", $ts) === date("Y-m-d") ? date("H:i", $ts) : date("j M, H:i", $ts);

    return '<div class="message ' . ($mine ? "mine" : "theirs") . '" data-id="' . (int) $m["id"] . '">'
         . nl2br(h($m["message"]))
         . '<small>' . ($showName ? h($m["name"]) . " · " : "") . h($time) . '</small>'
         . '</div>';
}
