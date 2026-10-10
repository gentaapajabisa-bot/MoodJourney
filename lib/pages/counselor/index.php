<?php
$me = require_staff();
if ($me["role"] !== "counselor") {
    redirect("/admin/");
}
$myId = (int) $me["id"];

$waiting = db_all(
    "SELECT sr.id, sr.topic, sr.urgent, sr.created_at
     FROM support_requests sr JOIN users u ON u.id = sr.user_id
     WHERE sr.status = 'waiting' AND u.is_active = 1
     ORDER BY sr.urgent DESC, sr.created_at ASC LIMIT 50"
);

$mine = db_all(
    "SELECT sr.id, sr.topic, sr.message, sr.urgent, sr.created_at, u.name, r.id AS room_id,
            (SELECT COUNT(*) FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE m.chat_room_id = r.id AND s.role = 'user' AND m.is_read = 0) AS unread,
            (SELECT COUNT(*) FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE m.chat_room_id = r.id AND s.role = 'user' AND m.is_read = 0 AND m.flagged = 1) AS flagged
     FROM support_requests sr
     JOIN users u ON u.id = sr.user_id
     LEFT JOIN chat_rooms r ON r.user_id = sr.user_id
     WHERE sr.status = 'active' AND sr.counselor_id = ?
     ORDER BY flagged DESC, unread DESC, sr.created_at ASC",
    "i",
    [$myId]
);

$done = db_all(
    "SELECT sr.topic, sr.updated_at, u.name FROM support_requests sr JOIN users u ON u.id = sr.user_id
     WHERE sr.status = 'done' AND sr.counselor_id = ? ORDER BY sr.updated_at DESC LIMIT 10",
    "i",
    [$myId]
);

page_header("Antrean konselor", ["nav" => "counselor", "active" => "counselor"]);
?>
<div class="container">

    <section class="page-title">
        <h1>Antrean konselor</h1>
        <p class="muted">Kamu hanya melihat percakapan yang kamu ambil. Catatan diary pengguna tidak ditampilkan di sini.</p>
    </section>

    <section class="card">
        <h2>Sedang kamu tangani (<?= count($mine) ?>)</h2>
        <?php if (!$mine): ?>
            <div class="empty">Belum ada. Ambil permintaan dari antrean di bawah.</div>
        <?php endif; ?>
        <?php foreach ($mine as $r): ?>
            <article class="queue-item">
                <div class="queue-head">
                    <strong><?= h($r["name"]) ?></strong>
                    <span class="muted"><?= h($r["topic"]) ?> · sejak <?= h(date_id($r["created_at"])) ?></span>
                    <?php if ((int) $r["flagged"] > 0): ?><span class="msg-flag">⚠ Perlu perhatian</span><?php endif; ?>
                    <?php if ((int) $r["unread"] > 0): ?><span class="badge"><?= (int) $r["unread"] ?></span><?php endif; ?>
                </div>
                <?php if ($r["message"]): ?><p class="queue-msg"><?= nl2br(h($r["message"])) ?></p><?php endif; ?>
                <div class="row-actions">
                    <?php if ($r["room_id"]): ?>
                        <a class="btn btn-small" href="/konselor/chat.php?room=<?= (int) $r["room_id"] ?>">Buka chat</a>
                    <?php endif; ?>
                    <form method="POST" action="/support/action.php" data-confirm="Tandai selesai? Percakapan akan ditutup.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="done">
                        <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                        <button class="btn btn-outline btn-small" type="submit">Selesai</button>
                    </form>
                    <form method="POST" action="/support/action.php" data-confirm="Kembalikan ke antrean?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="release">
                        <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                        <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Kembalikan</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Menunggu (<?= count($waiting) ?>)</h2>
        <p class="muted">Nama dan pesan baru terlihat setelah kamu mengambil permintaan.</p>
        <?php if (!$waiting): ?>
            <div class="empty">Tidak ada yang menunggu.</div>
        <?php endif; ?>
        <?php foreach ($waiting as $r): ?>
            <article class="queue-item">
                <div class="queue-head">
                    <strong><?= h($r["topic"]) ?></strong>
                    <span class="muted">diajukan <?= h(date("j M, H:i", strtotime($r["created_at"]))) ?></span>
                    <?php if ((int) $r["urgent"] === 1): ?><span class="msg-flag">⚠ Perlu perhatian</span><?php endif; ?>
                </div>
                <form method="POST" action="/support/action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="take">
                    <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                    <button class="btn btn-small" type="submit">Ambil</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($done): ?>
    <section class="card">
        <h2>Selesai terakhir</h2>
        <ul class="tag-list">
            <?php foreach ($done as $d): ?>
                <li><span><?= h($d["name"]) ?> · <?= h($d["topic"]) ?></span><span class="muted"><?= h(date_id($d["updated_at"])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

</div>
<?php page_footer();
