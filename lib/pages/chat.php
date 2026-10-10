<?php
$user = require_member();
$userId = (int) $user["id"];
$room = chat_get_room($userId);
$roomId = (int) $room["id"];

if (is_post()) {
    csrf_verify("/chat.php");

    $text = trim((string) ($_POST["message"] ?? ""));
    $bucket = "chat:" . $userId;

    if ($text === "") {
        // abaikan pesan kosong
    } elseif (mb_strlen($text) > CHAT_MAX_LENGTH) {
        flash("error", "Pesan terlalu panjang (maksimal " . CHAT_MAX_LENGTH . " karakter).");
    } elseif (rate_count($bucket, 60) >= 15) {
        flash("error", "Kamu mengirim terlalu cepat. Tunggu sebentar.");
    } else {
        rate_hit($bucket);
        if (chat_send($roomId, $userId, $text, true, true)) {
            flash("info", crisis_message() . " Tim akan membaca pesanmu secepatnya, tetapi balasan mungkin tidak langsung.");
        }
    }
    redirect("/chat.php");
}

chat_mark_read($roomId, "user");
$messages = chat_fetch($roomId);
$lastId = $messages ? (int) end($messages)["id"] : 0;
$room = chat_get_room($userId, false);

page_header("Chat dengan tim", ["active" => "support"]);
?>
<div class="container narrow">

    <aside class="notice notice-info chat-privacy">
        <strong>Percakapan ini bisa dibaca tim: admin dan konselor yang menangani permintaanmu.</strong>
        Jangan kirim password atau data pribadi yang sensitif. Kalau kamu dalam bahaya atau butuh bantuan segera,
        hubungi <strong>112</strong> atau lihat <a class="link" href="/bantuan.php">layanan bantuan</a>. Admin bukan tenaga kesehatan jiwa dan balasan tidak selalu langsung.
    </aside>

    <section class="card chat-wrapper">

        <header class="chat-header">
            <div>
                <strong>Dukungan MoodJourney</strong>
                <div class="muted">Tanya soal akun dan penggunaan aplikasi.</div>
            </div>
            <span class="status-dot <?= $room["status"] === "closed" ? "is-closed" : "" ?>">
                <?= $room["status"] === "closed" ? "Selesai" : "Terbuka" ?>
            </span>
        </header>

        <div class="chat-messages" id="chat-messages" tabindex="0" aria-live="polite" aria-label="Riwayat pesan"
             data-poll="/chat/poll.php" data-last-id="<?= $lastId ?>" data-role="user">
            <?php if (!$messages): ?>
                <div class="empty" data-chat-empty>
                    Belum ada pesan.<br>Tulis pesan pertamamu di bawah.
                </div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <?= chat_message_html($m, "user") ?>
            <?php endforeach; ?>
        </div>

        <?php if ($room["status"] === "closed"): ?>
            <p class="chat-closed">Percakapan ini ditandai selesai. Kirim pesan baru untuk membukanya kembali.</p>
        <?php endif; ?>

        <form method="POST" class="chat-form">
            <?= csrf_field() ?>
            <label class="sr-only" for="chat-input">Tulis pesan</label>
            <textarea class="form-control" id="chat-input" name="message" rows="1" maxlength="<?= CHAT_MAX_LENGTH ?>"
                      placeholder="Tulis pesan..." required></textarea>
            <button class="btn" type="submit">Kirim</button>
        </form>

    </section>

</div>
<?php page_footer();
