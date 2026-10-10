<?php
$user = require_login();
if ($user["role"] === "admin") {
    redirect("/admin/");
}
$userId = (int) $user["id"];

if (is_post()) {
    csrf_verify("/target.php");
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "add") {
        $tag = (string) ($_POST["tag"] ?? "");
        $target = (int) ($_POST["target"] ?? 0);

        if (!isset(TAGS[$tag]) || $target < 1 || $target > 7) {
            flash("error", "Pilih aktivitas dan jumlah hari (1 sampai 7).");
        } elseif ((int) db_value("SELECT COUNT(*) FROM goals WHERE user_id = ?", "i", [$userId], 0) >= MAX_GOALS) {
            flash("error", "Maksimal " . MAX_GOALS . " target. Hapus salah satu dulu.");
        } elseif (db_one("SELECT id FROM goals WHERE user_id = ? AND tag = ? LIMIT 1", "is", [$userId, $tag])) {
            flash("error", "Target untuk aktivitas itu sudah ada.");
        } else {
            db_exec("INSERT INTO goals (user_id, tag, target) VALUES (?, ?, ?)", "isi", [$userId, $tag, $target]);
            flash("success", "Target ditambahkan.");
        }
    } elseif ($action === "delete") {
        db_exec("DELETE FROM goals WHERE id = ? AND user_id = ?", "ii", [(int) ($_POST["id"] ?? 0), $userId]);
        flash("success", "Target dihapus.");
    }
    redirect("/target.php");
}

$goals = goals_with_progress($userId);
$usedTags = array_column($goals, "tag");
$available = array_diff_key(TAGS, array_flip($usedTags));

$history = [];
foreach ($goals as $g) {
    $history[$g["id"]] = goal_history($userId, $g["tag"], 4);
}

page_header("Target", ["active" => "statistics"]);
?>
<div class="container">

    <?php insights_tabs("goals"); ?>

    <section class="page-title">
        <h1>Target kebiasaan</h1>
        <p class="muted">Pilih aktivitas yang ingin kamu lakukan beberapa hari dalam seminggu. Targetnya dihitung dari tag di catatan diary-mu, dari Senin sampai Minggu.</p>
    </section>

    <section class="card">
        <h2>Minggu ini</h2>

        <?php if (!$goals): ?>
            <div class="empty">
                <p>Belum ada target. Mulai dengan satu yang realistis, misalnya olahraga 3 hari seminggu.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($goals as $g):
            $pct = min(100, round($g["done"] / max(1, $g["target"]) * 100)); ?>
            <div class="goal-row">
                <div class="goal-head">
                    <strong><?= h($g["label"]) ?></strong>
                    <span class="<?= $g["met"] ? "delta up" : "muted" ?>"><?= $g["met"] ? "Tercapai ✓ " : "" ?><?= (int) $g["done"] ?>/<?= (int) $g["target"] ?> hari</span>
                </div>
                <div class="bar-bg" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $g["target"] ?>" aria-valuenow="<?= (int) $g["done"] ?>" aria-label="<?= h($g["label"]) ?>">
                    <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                </div>
                <div class="goal-foot">
                    <span class="muted goal-history" aria-label="Empat minggu terakhir">
                        4 minggu lalu
                        <?php foreach ($history[$g["id"]] as $w): $ok = $w["count"] >= $g["target"]; ?>
                            <span class="dot <?= $ok ? "ok" : "" ?>" title="<?= h(week_label($w["start"]) . ": " . $w["count"] . " hari") ?>"><?= $ok ? "✓" : (int) $w["count"] ?></span>
                        <?php endforeach; ?>
                    </span>
                    <form method="POST" data-confirm="Hapus target ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $g["id"] ?>">
                        <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if (count($goals) < MAX_GOALS && $available): ?>
    <section class="card">
        <h2>Tambah target</h2>
        <form method="POST" class="goal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label for="tag">Aktivitas</label>
                <select class="form-control" id="tag" name="tag" required>
                    <?php foreach ($available as $key => $label): ?>
                        <option value="<?= h($key) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="target">Berapa hari per minggu?</label>
                <select class="form-control" id="target" name="target" required>
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === 3 ? "selected" : "" ?>><?= $i ?> hari</option>
                    <?php endfor; ?>
                </select>
            </div>
            <button class="btn" type="submit">Tambah target</button>
        </form>
    </section>
    <?php endif; ?>

</div>
<?php page_footer();
