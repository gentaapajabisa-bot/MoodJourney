<?php
$user = require_login();
$userId = (int) $user["id"];

$f = diary_filters($userId, $_GET);
$filters = $f["filters"];

$perPage = 10;
$total = (int) db_value("SELECT COUNT(*) FROM diary WHERE " . $f["where"], $f["types"], $f["params"], 0);
$pages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($pages, (int) ($_GET["page"] ?? 1)));
$offset = ($page - 1) * $perPage;

$entries = db_all(
    "SELECT * FROM diary WHERE " . $f["where"] . " ORDER BY diary_date DESC, id DESC LIMIT ? OFFSET ?",
    $f["types"] . "ii",
    array_merge($f["params"], [$perPage, $offset])
);

$hasFilter = array_filter($filters, fn($v) => $v !== "") !== [];
$self = "/history.php";
$returnTo = $self . ($hasFilter || $page > 1 ? "?" . filters_query($filters, $page > 1 ? ["page" => $page] : []) : "");

page_header("Riwayat", ["active" => "history"]);
?>
<div class="container">

    <section class="dashboard-header">
        <div>
            <h1>Riwayat catatan</h1>
            <p class="muted"><?= $total ?> catatan<?= $hasFilter ? " cocok dengan filter" : "" ?>.</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-outline" href="/export.php?<?= h(filters_query($filters, ["format" => "csv"])) ?>">Unduh CSV</a>
            <a class="btn btn-outline" href="/export.php?<?= h(filters_query($filters, ["format" => "print"])) ?>" target="_blank" rel="noopener">Cetak / PDF</a>
            <a class="btn" href="/diary.php">+ Catat mood</a>
        </div>
    </section>

    <form class="card filter-card" method="GET" action="/history.php">
        <div class="filter-grid">
            <div class="form-group">
                <label for="q">Cari</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= h($filters["q"]) ?>" placeholder="Kata di cerita atau aktivitas">
            </div>
            <div class="form-group">
                <label for="mood">Mood</label>
                <select class="form-control" id="mood" name="mood">
                    <option value="">Semua</option>
                    <?php foreach (MOODS as $emoji => $info): ?>
                        <option value="<?= h($emoji) ?>" <?= $filters["mood"] === $emoji ? "selected" : "" ?>><?= h($emoji . " " . $info["label"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tag">Aktivitas</label>
                <select class="form-control" id="tag" name="tag">
                    <option value="">Semua</option>
                    <?php foreach (TAGS as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $filters["tag"] === $key ? "selected" : "" ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="from">Dari tanggal</label>
                <input class="form-control" type="date" id="from" name="from" value="<?= h($filters["from"]) ?>">
            </div>
            <div class="form-group">
                <label for="to">Sampai tanggal</label>
                <input class="form-control" type="date" id="to" name="to" value="<?= h($filters["to"]) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Terapkan filter</button>
            <?php if ($hasFilter): ?><a class="btn btn-outline" href="/history.php">Hapus filter</a><?php endif; ?>
        </div>
    </form>

    <section class="card">
        <?php if (!$entries): ?>
            <div class="empty">
                <?php if ($hasFilter): ?>
                    <p>Tidak ada catatan yang cocok. Coba ubah atau hapus filternya.</p>
                    <a class="btn btn-outline" href="/history.php">Hapus filter</a>
                <?php else: ?>
                    <p>Belum ada catatan.</p>
                    <a class="btn" href="/diary.php">Buat catatan pertama</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($entries as $entry): ?>
                <?= render_diary_item($entry, true, $returnTo) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Halaman riwayat">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline btn-small" href="/history.php?<?= h(filters_query($filters, ["page" => $page - 1])) ?>">← Sebelumnya</a>
            <?php endif; ?>
            <span class="muted">Halaman <?= $page ?> dari <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-outline btn-small" href="/history.php?<?= h(filters_query($filters, ["page" => $page + 1])) ?>">Berikutnya →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</div>
<?php page_footer();
