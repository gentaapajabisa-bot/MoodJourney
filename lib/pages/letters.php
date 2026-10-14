<?php
/** Surat untuk diri sendiri: ditulis sekarang, baru bisa dibuka di tanggal yang dipilih. */
$user = require_member();
$userId = (int) $user["id"];
$today = date("Y-m-d");

if (is_post()) {
    csrf_verify("/surat.php");
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "create") {
        $body = trim((string) ($_POST["body"] ?? ""));
        $preset = (string) ($_POST["preset"] ?? "30");
        $days = ["30" => 30, "90" => 90, "180" => 180, "365" => 365][$preset] ?? 0;
        $open = $days > 0 ? date("Y-m-d", strtotime("+$days days")) : (string) ($_POST["open_on"] ?? "");

        if ($body === "" || mb_strlen($body) > LETTER_MAX_CHARS) {
            flash("error", "Isi surat harus diisi (maksimal " . LETTER_MAX_CHARS . " karakter).");
        } elseif (!valid_date($open) || $open < date("Y-m-d", strtotime("+7 days")) || $open > date("Y-m-d", strtotime("+5 years"))) {
            flash("error", "Tanggal buka minimal 7 hari lagi dan maksimal 5 tahun lagi.");
        } elseif ((int) db_value("SELECT COUNT(*) FROM letters WHERE user_id = ? AND opened_at IS NULL", "i", [$userId], 0) >= LETTER_MAX_PENDING) {
            flash("error", "Kamu sudah punya " . LETTER_MAX_PENDING . " surat yang belum dibuka. Tunggu salah satunya terbuka dulu.");
        } else {
            db_exec("INSERT INTO letters (user_id, body, open_on) VALUES (?, ?, ?)", "iss", [$userId, $body, $open]);
            flash("success", "Suratmu tersimpan dan terkunci sampai " . date_id($open) . ".");
        }
        redirect("/surat.php");
    }

    if ($action === "open") {
        $id = (int) ($_POST["id"] ?? 0);
        db_exec("UPDATE letters SET opened_at = NOW() WHERE id = ? AND user_id = ? AND open_on <= ? AND opened_at IS NULL", "iis", [$id, $userId, $today]);
        redirect("/surat.php#s" . $id);
    }

    if ($action === "delete") {
        $id = (int) ($_POST["id"] ?? 0);
        db_exec("DELETE FROM letters WHERE id = ? AND user_id = ?", "ii", [$id, $userId]);
        flash("success", "Surat dihapus.");
        redirect("/surat.php");
    }
    redirect("/surat.php");
}

$letters = db_all("SELECT id, body, open_on, created_at, opened_at FROM letters WHERE user_id = ? ORDER BY open_on ASC, id ASC", "i", [$userId]);
$ready = array_filter($letters, fn($l) => $l["open_on"] <= $today && $l["opened_at"] === null);
$locked = array_filter($letters, fn($l) => $l["open_on"] > $today);
$opened = array_reverse(array_values(array_filter($letters, fn($l) => $l["opened_at"] !== null)));

page_header("Surat untuk diri sendiri", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("letters"); ?>

    <section class="dashboard-header">
        <div>
            <h1>Surat untuk dirimu</h1>
            <p class="muted">Tulis pesan hari ini, dan baca lagi di masa depan. Suratnya terkunci sampai tanggal yang kamu pilih.</p>
        </div>
    </section>

    <?php foreach ($ready as $l): ?>
        <section class="card letter-ready" id="s<?= (int) $l["id"] ?>">
            <div class="letter-seal" aria-hidden="true">💌</div>
            <div>
                <h2>Ada surat dari dirimu</h2>
                <p class="muted">Ditulis <?= h(date_id(substr($l["created_at"], 0, 10))) ?>. Sekarang sudah boleh dibuka.</p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="open">
                    <input type="hidden" name="id" value="<?= (int) $l["id"] ?>">
                    <button class="btn" type="submit">Buka surat</button>
                </form>
            </div>
        </section>
    <?php endforeach; ?>

    <?php foreach ($opened as $l): ?>
        <article class="card letter-paper" id="s<?= (int) $l["id"] ?>">
            <p class="letter-date muted">Ditulis <?= h(date_id(substr($l["created_at"], 0, 10))) ?> · dibuka <?= h(date_id(substr((string) $l["opened_at"], 0, 10))) ?></p>
            <div class="letter-text"><?= nl2br(h($l["body"])) ?></div>
            <form method="POST" data-confirm="Hapus surat ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $l["id"] ?>">
                <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus</button>
            </form>
        </article>
    <?php endforeach; ?>

    <section class="grid-2">
        <div class="card">
            <h2>Tulis surat baru</h2>
            <form method="POST" class="letter-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="letter-body">Isi suratmu</label>
                    <textarea class="form-control" id="letter-body" name="body" rows="7" maxlength="<?= LETTER_MAX_CHARS ?>" required
                              placeholder="Halo aku di masa depan. Hari ini aku merasa…"></textarea>
                </div>
                <div class="form-group">
                    <label for="letter-preset">Buka dalam</label>
                    <select class="form-control" id="letter-preset" name="preset">
                        <option value="30">30 hari</option>
                        <option value="90">3 bulan</option>
                        <option value="180">6 bulan</option>
                        <option value="365">1 tahun</option>
                        <option value="custom">Pilih tanggal sendiri</option>
                    </select>
                </div>
                <div class="form-group" id="letter-date-wrap" hidden>
                    <label for="letter-date">Tanggal buka</label>
                    <input class="form-control" type="date" id="letter-date" name="open_on"
                           min="<?= h(date("Y-m-d", strtotime("+7 days"))) ?>" max="<?= h(date("Y-m-d", strtotime("+5 years"))) ?>">
                </div>
                <button class="btn" type="submit">Kunci surat</button>
                <p class="muted card-foot">Setelah dikunci, isinya tidak bisa dibaca sampai tanggalnya tiba, termasuk olehmu.</p>
            </form>
        </div>

        <div class="card">
            <h2>Surat yang menunggu</h2>
            <?php if (!$locked): ?>
                <p class="muted">Belum ada surat yang terkunci.</p>
            <?php else: ?>
                <ul class="adm-list">
                    <?php foreach ($locked as $l):
                        $left = (int) ceil((strtotime($l["open_on"]) - strtotime($today)) / 86400); ?>
                        <li>
                            <span>🔒 Terbuka <?= h(date_id($l["open_on"])) ?>
                                <small class="muted"><?= $left ?> hari lagi · ditulis <?= h(date_id(substr($l["created_at"], 0, 10))) ?></small></span>
                            <form method="POST" data-confirm="Hapus surat ini? Isinya tidak bisa dikembalikan.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $l["id"] ?>">
                                <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

</div>
<script>
(function () {
    var sel = document.getElementById("letter-preset"), wrap = document.getElementById("letter-date-wrap"), date = document.getElementById("letter-date");
    if (!sel) return;
    function sync() { var c = sel.value === "custom"; wrap.hidden = !c; date.required = c; }
    sel.addEventListener("change", sync); sync();
})();
</script>
<?php page_footer();
