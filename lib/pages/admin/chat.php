<?php
$admin = require_admin();
$adminId = (int) $admin["id"];

$roomId = (int) ($_GET["room"] ?? $_POST["room"] ?? 0);
$room = $roomId > 0
    ? db_one(
        "SELECT r.id, r.status, u.name, u.email FROM chat_rooms r JOIN users u ON u.id = r.user_id WHERE r.id = ? LIMIT 1",
        "i",
        [$roomId]
    )
    : null;

if (!$room) {
    flash("error", "Percakapan tidak ditemukan.");
    redirect("/admin/");
}

if (is_post()) {
    csrf_verify("/admin/chat.php?room=" . $roomId);

    if (($_POST["action"] ?? "") === "status") {
        $new = $room["status"] === "closed" ? "open" : "closed";
        db_exec("UPDATE chat_rooms SET status = ? WHERE id = ?", "si", [$new, $roomId]);
        flash("success", $new === "closed" ? "Percakapan ditandai selesai." : "Percakapan dibuka kembali.");
    } else {
        $text = trim((string) ($_POST["message"] ?? ""));
        if ($text !== "" && mb_strlen($text) <= CHAT_MAX_LENGTH) {
            chat_send($roomId, $adminId, $text, false);
        } elseif ($text !== "") {
            flash("error", "Pesan terlalu panjang (maksimal " . CHAT_MAX_LENGTH . " karakter).");
        }
    }
    redirect("/admin/chat.php?room=" . $roomId);
}

chat_mark_read($roomId, "admin");
$messages = chat_fetch($roomId);
$lastId = $messages ? (int) end($messages)["id"] : 0;

page_header("Chat — " . $room["name"], ["nav" => "admin", "active" => "admin"]);
?>
<div class="container narrow">

    <p><a class="link" href="/admin/">← Kembali ke panel admin</a></p>

    <section class="card chat-wrapper">

        <header class="chat-header">
            <div>
                <strong><?= h($room["name"]) ?></strong>
                <div class="muted"><?= h($room["email"]) ?></div>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="room" value="<?= $roomId ?>">
                <input type="hidden" name="action" value="status">
                <button class="btn btn-outline btn-small" type="submit">
                    <?= $room["status"] === "closed" ? "Buka kembali" : "Tandai selesai" ?>
                </button>
            </form>
        </header>

        <div class="chat-messages" id="chat-messages" tabindex="0" aria-live="polite" aria-label="Riwayat pesan"
             data-poll="/chat/poll.php?room=<?= $roomId ?>" data-last-id="<?= $lastId ?>" data-role="admin">
            <?php if (!$messages): ?>
                <div class="empty" data-chat-empty>Belum ada pesan.</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <?= chat_message_html($m, "admin", true) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($room["status"] === "closed"): ?>
            <p class="chat-closed">Percakapan ini ditandai selesai. Balasan baru akan tetap terkirim ke pengguna.</p>
        <?php endif; ?>

        <form method="POST" class="chat-form">
            <?= csrf_field() ?>
            <input type="hidden" name="room" value="<?= $roomId ?>">
            <label class="sr-only" for="chat-input">Balas pengguna</label>
            <textarea class="form-control" id="chat-input" name="message" rows="1" maxlength="<?= CHAT_MAX_LENGTH ?>"
                      placeholder="Balas pengguna..." required></textarea>
            <button class="btn" type="submit">Kirim</button>
        </form>

    </section>

</div>
<?php page_footer();
