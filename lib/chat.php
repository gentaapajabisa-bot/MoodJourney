<?php
/**
 * Fungsi bersama untuk chat pengguna <-> staf (admin atau konselor).
 *
 * Konvensi $viewerRole: "user" untuk pengguna, "admin" untuk staf mana pun (admin/konselor).
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

/** Kirim pesan. Pesan pengguna yang memuat tanda krisis diberi penanda "flagged". */
function chat_send(int $roomId, int $senderId, string $text, bool $reopen, bool $fromUser = false): bool
{
    $flagged = ($fromUser && crisis_detect($text)) ? 1 : 0;

    db_exec(
        "INSERT INTO messages (chat_room_id, sender_id, message, flagged) VALUES (?, ?, ?, ?)",
        "iisi",
        [$roomId, $senderId, $text, $flagged]
    );
    if ($reopen) {
        db_exec("UPDATE chat_rooms SET last_message_at = NOW(), status = 'open' WHERE id = ?", "i", [$roomId]);
    } else {
        db_exec("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?", "i", [$roomId]);
    }
    return $flagged === 1;
}

/** Tandai pesan dari pihak lawan sebagai sudah dibaca. */
function chat_mark_read(int $roomId, string $viewerRole): void
{
    if ($viewerRole === "admin") {
        db_exec(
            "UPDATE messages m JOIN users u ON u.id = m.sender_id
             SET m.is_read = 1
             WHERE m.chat_room_id = ? AND u.role = 'user' AND m.is_read = 0",
            "i",
            [$roomId]
        );
    } else {
        db_exec(
            "UPDATE messages m JOIN users u ON u.id = m.sender_id
             SET m.is_read = 1
             WHERE m.chat_room_id = ? AND u.role IN ('admin', 'counselor') AND m.is_read = 0",
            "i",
            [$roomId]
        );
    }
}

function chat_fetch(int $roomId, int $afterId = 0, int $limit = 300): array
{
    $rows = db_all(
        "SELECT m.id, m.message, m.created_at, m.flagged, u.name, u.role
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.chat_room_id = ? AND m.id > ?
         ORDER BY m.id DESC LIMIT ?",
        "iii",
        [$roomId, $afterId, $limit]
    );
    return array_reverse($rows);
}

function role_label(string $role): string
{
    return $role === "counselor" ? "Konselor" : ($role === "admin" ? "Admin" : "Pengguna");
}

function chat_message_html(array $m, string $viewerRole, bool $showName = false): string
{
    $mine = $viewerRole === "admin" ? is_staff($m["role"]) : $m["role"] === "user";
    $ts = strtotime($m["created_at"]);
    $time = date("Y-m-d", $ts) === date("Y-m-d") ? date("H:i", $ts) : date("j M, H:i", $ts);

    // pengguna melihat siapa yang membalas (admin atau konselor); staf melihat nama pengguna
    $who = "";
    if ($showName) {
        $who = h($m["name"]) . " · ";
    } elseif (!$mine && is_staff($m["role"])) {
        $who = h(role_label($m["role"]) . " " . $m["name"]) . " · ";
    }

    $flag = ($viewerRole === "admin" && !$mine && (int) ($m["flagged"] ?? 0) === 1)
        ? '<span class="msg-flag" title="Memuat tanda yang perlu perhatian">⚠ Perlu perhatian</span>'
        : "";

    return '<div class="message ' . ($mine ? "mine" : "theirs") . '" data-id="' . (int) $m["id"] . '">'
         . $flag . nl2br(h($m["message"]))
         . '<small>' . $who . h($time) . '</small>'
         . '</div>';
}

/**
 * Apakah staf ini boleh membuka ruang chat tersebut?
 * Admin: semua. Konselor: hanya pengguna yang permintaannya sedang ia tangani.
 */
function staff_can_access_room(array $staff, int $roomId): bool
{
    if ($staff["role"] === "admin") {
        return true;
    }
    if ($staff["role"] !== "counselor") {
        return false;
    }
    return (bool) db_one(
        "SELECT sr.id FROM support_requests sr
         JOIN chat_rooms r ON r.user_id = sr.user_id
         WHERE r.id = ? AND sr.counselor_id = ? AND sr.status = 'active' LIMIT 1",
        "ii",
        [$roomId, (int) $staff["id"]]
    );
}
