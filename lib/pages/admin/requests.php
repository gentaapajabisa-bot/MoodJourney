<?php
/**
 * Admin: tinjau formulir konselor yang masuk, cek ketersediaan konselor, lalu tugaskan.
 */
require_once dirname(__DIR__, 2) . "/cform.php";

$user = require_admin();
$topics = cform_topics();

if (is_post()) {
    csrf_verify("/admin/permintaan.php");
    $action = (string) ($_POST["action"] ?? "");
    $reqId = (int) ($_POST["request_id"] ?? 0);
    $req = db_one("SELECT id, status FROM support_requests WHERE id = ?", "i", [$reqId]);

    if ($req === null || !in_array($req["status"], ["waiting", "active"], true)) {
        flash("error", "Permintaan tidak ditemukan atau sudah selesai.");
    } elseif ($action === "assign") {
        $cid = (int) ($_POST["counselor_id"] ?? 0);
        $c = db_one(
            "SELECT id, name, counselor_available FROM users WHERE id = ? AND role = 'counselor' AND is_active = 1",
            "i",
            [$cid]
        );
        if ($c === null) {
            flash("error", "Pilih konselor yang valid.");
        } elseif ((int) $c["counselor_available"] !== 1) {
            flash("error", $c["name"] . " sedang menandai dirinya tidak tersedia.");
        } else {
            db_exec("UPDATE support_requests SET counselor_id = ?, updated_at = NOW() WHERE id = ?", "ii", [$cid, $reqId]);
            flash("success", "Permintaan ditugaskan ke " . $c["name"] . ".");
        }
    } elseif ($action === "unassign") {
        db_exec("UPDATE support_requests SET counselor_id = NULL, status = 'waiting', updated_at = NOW() WHERE id = ?", "i", [$reqId]);
        flash("success", "Penugasan dilepas. Permintaan kembali menunggu.");
    }
    redirect("/admin/permintaan.php#r" . $reqId);
}

$counselors = db_all(
    "SELECT u.id, u.name, u.counselor_available, u.counselor_topics, u.counselor_hours,
            (SELECT COUNT(*) FROM support_requests sr WHERE sr.counselor_id = u.id AND sr.status IN ('waiting','active')) AS load_n
     FROM users u WHERE u.role = 'counselor' AND u.is_active = 1
     ORDER BY u.counselor_available DESC, load_n ASC, u.name ASC"
);
foreach ($counselors as &$c) {
    $c["topic_keys"] = array_values(array_filter(array_map("trim", explode(",", (string) $c["counselor_topics"]))));
}
unset($c);

$requests = db_all(
    "SELECT sr.*, u.name AS user_name, c.name AS counselor_name, p.name AS preferred_name
     FROM support_requests sr
     JOIN users u ON u.id = sr.user_id
     LEFT JOIN users c ON c.id = sr.counselor_id
     LEFT JOIN users p ON p.id = sr.preferred_counselor_id
     WHERE sr.status IN ('waiting','active')
     ORDER BY sr.urgent DESC, (sr.counselor_id IS NULL) DESC, sr.created_at ASC"
);

page_header("Permintaan konselor", ["nav" => "admin", "active" => "admin-requests"]);
?>
<div class="container">
    <section class="page-title">
        <h1>Permintaan konselor</h1>
        <p class="muted">Baca formulir yang masuk, cek ketersediaan konselor, lalu tugaskan. Permintaan mendesak tampil paling atas.</p>
    </section>

    <section class="card">
        <h2>Ketersediaan konselor</h2>
        <?php if ($counselors === []): ?>
            <p class="muted">Belum ada akun konselor aktif.</p>
        <?php else: ?>
            <ul class="cform-counselors">
                <?php foreach ($counselors as $c): ?>
                    <li>
                        <strong><?= h($c["name"]) ?></strong>
                        <span class="chip-static"><?= (int) $c["counselor_available"] === 1 ? "Tersedia" : "Tidak tersedia" ?></span>
                        <small class="muted"> · <?= (int) $c["load_n"] ?> permintaan berjalan<?= $c["counselor_hours"] ? " · " . h($c["counselor_hours"]) : "" ?></small>
                        <div class="chip-row">
                            <?php if ($c["topic_keys"] === []): ?>
                                <span class="chip-static">Semua topik</span>
                            <?php else: foreach ($c["topic_keys"] as $k): if (isset($topics[$k])): ?>
                                <span class="chip-static"><?= h($topics[$k]) ?></span>
                            <?php endif; endforeach; endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($requests === []): ?>
        <section class="card"><p class="muted">Belum ada permintaan yang menunggu.</p></section>
    <?php endif; ?>

    <?php foreach ($requests as $r):
        $form = $r["form_data"] ? json_decode((string) $r["form_data"], true) : null;
    ?>
        <section class="card req-card <?= (int) $r["urgent"] === 1 ? "is-urgent" : "" ?>" id="r<?= (int) $r["id"] ?>">
            <h2>
                <?= h($r["user_name"]) ?>
                <?php if ((int) $r["urgent"] === 1): ?><span class="chip-static req-urgent">Mendesak</span><?php endif; ?>
            </h2>
            <p class="muted">
                #<?= (int) $r["id"] ?> · <?= h($topics[$r["topic"]] ?? $r["topic"]) ?> · masuk <?= h(date("d M Y H:i", strtotime((string) $r["created_at"]))) ?>
                · Status:
                <?php if ($r["status"] === "active"): ?>
                    ditangani <?= h((string) $r["counselor_name"]) ?>
                <?php elseif ($r["counselor_id"]): ?>
                    ditugaskan ke <?= h((string) $r["counselor_name"]) ?>
                <?php else: ?>
                    belum ada konselor
                <?php endif; ?>
                <?php if ($r["preferred_name"]): ?> · Diharapkan: <?= h($r["preferred_name"]) ?><?php endif; ?>
            </p>
            <?php if (!empty($r["phone"])): ?>
                <p>
                    Telepon/WhatsApp: <strong><?= h($r["phone"]) ?></strong>
                    · <a href="tel:<?= h($r["phone"]) ?>">Telepon</a>
                    · <a href="https://wa.me/<?= h(cform_wa_number((string) $r["phone"])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                </p>
            <?php endif; ?>

            <details class="req-attach" <?= (int) $r["urgent"] === 1 ? "open" : "" ?>>
                <summary>Lampiran formulir</summary>
                <?php if (is_array($form)): ?>
                    <dl class="req-qa">
                        <?php foreach ($form as $item): ?>
                            <?php if (($item["type"] ?? "") === "section"): ?>
                                <dt class="req-section"><?= h($item["t"] ?? "") ?></dt>
                            <?php else: ?>
                                <dt><?= h($item["t"] ?? "") ?></dt>
                                <dd><?= nl2br(h($item["a"] ?? "")) ?></dd>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <p class="muted">Permintaan ini dibuat sebelum formulir baru.</p>
                    <dl class="req-qa">
                        <dt>Topik</dt><dd><?= h($topics[$r["topic"]] ?? $r["topic"]) ?></dd>
                        <?php if ($r["message"]): ?><dt>Pesan</dt><dd><?= nl2br(h($r["message"])) ?></dd><?php endif; ?>
                    </dl>
                <?php endif; ?>
            </details>

            <form method="POST" class="req-assign">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $r["id"] ?>">
                <label class="sr-only" for="c<?= (int) $r["id"] ?>">Pilih konselor</label>
                <select class="form-control" id="c<?= (int) $r["id"] ?>" name="counselor_id" required>
                    <option value="">Pilih konselor…</option>
                    <?php foreach ($counselors as $c):
                        $free = (int) $c["counselor_available"] === 1;
                        $match = $c["topic_keys"] === [] || in_array($r["topic"], $c["topic_keys"], true);
                    ?>
                        <option value="<?= (int) $c["id"] ?>" <?= $free ? "" : "disabled" ?> <?= (int) $r["counselor_id"] === (int) $c["id"] ? "selected" : "" ?>>
                            <?= h($c["name"]) ?> — <?= $free ? "tersedia" : "tidak tersedia" ?>, <?= $match ? "cocok topik" : "topik berbeda" ?>, <?= (int) $c["load_n"] ?> berjalan
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-small" type="submit" name="action" value="assign"><?= $r["counselor_id"] ? "Ganti konselor" : "Tugaskan" ?></button>
                <?php if ($r["counselor_id"] && $r["status"] === "waiting"): ?>
                    <button class="btn btn-outline btn-small" type="submit" name="action" value="unassign">Lepas</button>
                <?php endif; ?>
            </form>
        </section>
    <?php endforeach; ?>
</div>
<?php page_footer();
