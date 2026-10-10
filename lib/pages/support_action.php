<?php
$staff = require_staff();

if (!is_post()) {
    redirect(home_for($staff));
}
$back = home_for($staff);
csrf_verify($back);

$action = (string) ($_POST["action"] ?? "");
$requestId = (int) ($_POST["request_id"] ?? 0);
$isAdmin = $staff["role"] === "admin";

$req = $requestId > 0
    ? db_one("SELECT id, user_id, status, counselor_id FROM support_requests WHERE id = ? LIMIT 1", "i", [$requestId])
    : null;

if (!$req) {
    flash("error", "Permintaan tidak ditemukan.");
    redirect($back);
}

switch ($action) {

    case "take": // hanya konselor
        if ($staff["role"] !== "counselor" || $req["status"] !== "waiting") {
            flash("error", "Permintaan ini tidak bisa diambil.");
            break;
        }
        [$affected] = db_exec(
            "UPDATE support_requests SET status = 'active', counselor_id = ?, updated_at = NOW()
             WHERE id = ? AND status = 'waiting'",
            "ii",
            [(int) $staff["id"], $requestId]
        );
        if ($affected === 0) {
            flash("error", "Permintaan itu sudah diambil konselor lain.");
            break;
        }
        $room = chat_get_room((int) $req["user_id"]);
        redirect("/konselor/chat.php?room=" . (int) $room["id"]);

    case "assign": // hanya admin
        $cid = (int) ($_POST["counselor_id"] ?? 0);
        $counselor = $cid > 0
            ? db_one("SELECT id FROM users WHERE id = ? AND role = 'counselor' AND is_active = 1 LIMIT 1", "i", [$cid])
            : null;
        if (!$isAdmin || !$counselor || !in_array($req["status"], ["waiting", "active"], true)) {
            flash("error", "Penugasan gagal. Pilih konselor yang aktif.");
            break;
        }
        db_exec(
            "UPDATE support_requests SET status = 'active', counselor_id = ?, updated_at = NOW() WHERE id = ?",
            "ii",
            [$cid, $requestId]
        );
        chat_get_room((int) $req["user_id"]);
        flash("success", "Permintaan ditugaskan.");
        break;

    case "release": // konselor mengembalikan ke antrean
        if ($staff["role"] === "counselor" && (int) $req["counselor_id"] === (int) $staff["id"] && $req["status"] === "active") {
            db_exec(
                "UPDATE support_requests SET status = 'waiting', counselor_id = NULL, updated_at = NOW() WHERE id = ?",
                "i",
                [$requestId]
            );
            flash("success", "Permintaan dikembalikan ke antrean.");
        } else {
            flash("error", "Aksi tidak diizinkan.");
        }
        break;

    case "done":
        $mine = $staff["role"] === "counselor" && (int) $req["counselor_id"] === (int) $staff["id"];
        if (($mine || $isAdmin) && $req["status"] === "active") {
            db_exec("UPDATE support_requests SET status = 'done', updated_at = NOW() WHERE id = ?", "i", [$requestId]);
            db_exec("UPDATE chat_rooms SET status = 'closed' WHERE user_id = ?", "i", [(int) $req["user_id"]]);
            flash("success", "Permintaan ditandai selesai.");
        } else {
            flash("error", "Aksi tidak diizinkan.");
        }
        break;

    default:
        flash("error", "Aksi tidak dikenal.");
}

redirect($back);
