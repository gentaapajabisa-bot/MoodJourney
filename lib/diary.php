<?php
/**
 * Fungsi bersama untuk riwayat, ekspor, dan tampilan entri diary.
 */

/**
 * Baca filter dari query string dan bangun klausa WHERE yang aman.
 * @return array{where:string, types:string, params:array, filters:array}
 */
function diary_filters(int $userId, array $src): array
{
    $q    = trim((string) ($src["q"] ?? ""));
    $mood = (string) ($src["mood"] ?? "");
    $tag  = (string) ($src["tag"] ?? "");
    $from = (string) ($src["from"] ?? "");
    $to   = (string) ($src["to"] ?? "");

    $where  = "user_id = ?";
    $types  = "i";
    $params = [$userId];

    if ($q !== "") {
        $like = "%" . like_escape(mb_substr($q, 0, 100)) . "%";
        $where .= " AND (activity LIKE ? OR note LIKE ?)";
        $types .= "ss";
        array_push($params, $like, $like);
    }
    if ($mood !== "" && isset(MOODS[$mood])) {
        $where .= " AND mood = ?";
        $types .= "s";
        $params[] = $mood;
    } else {
        $mood = "";
    }
    if ($tag !== "" && isset(TAGS[$tag])) {
        // tags disimpan "a,b,c": bungkus dengan koma agar cocok per tag utuh
        $where .= " AND CONCAT(',', COALESCE(tags, ''), ',') LIKE ?";
        $types .= "s";
        $params[] = "%," . $tag . ",%";
    } else {
        $tag = "";
    }
    if ($from !== "" && valid_date($from)) {
        $where .= " AND diary_date >= ?";
        $types .= "s";
        $params[] = $from;
    } else {
        $from = "";
    }
    if ($to !== "" && valid_date($to)) {
        $where .= " AND diary_date <= ?";
        $types .= "s";
        $params[] = $to;
    } else {
        $to = "";
    }

    return [
        "where"   => $where,
        "types"   => $types,
        "params"  => $params,
        "filters" => ["q" => $q, "mood" => $mood, "tag" => $tag, "from" => $from, "to" => $to],
    ];
}

/** Query string dari filter aktif (tanpa nilai kosong). */
function filters_query(array $filters, array $extra = []): string
{
    $all = array_merge(array_filter($filters, fn($v) => $v !== ""), $extra);
    return http_build_query($all);
}

/** Satu kartu entri diary. $actions=true menampilkan tombol ubah/hapus. */
function render_diary_item(array $entry, bool $actions = true, string $returnTo = "/history.php"): string
{
    $mood     = (string) $entry["mood"];
    $title    = $entry["activity"] !== null && $entry["activity"] !== "" ? $entry["activity"] : "Catatan hari ini";
    $note     = $entry["note"] !== null && $entry["note"] !== "" ? $entry["note"] : "";
    $tags     = tags_to_array($entry["tags"] ?? "");
    $intensity = (int) ($entry["intensity"] ?? 3);
    $score    = (int) round(mood_score($mood));

    ob_start();
    ?>
    <article class="diary-item">
        <div class="diary-mood" data-score="<?= $score ?>" title="<?= h(mood_label($mood)) ?>">
            <?= h($mood) ?>
        </div>

        <div class="diary-body">
            <div class="diary-head">
                <h3><?= h($title) ?></h3>
                <span class="diary-date"><?= h(date_id($entry["diary_date"])) ?></span>
            </div>

            <?php if ($note !== ""): ?>
                <p><?= nl2br(h($note)) ?></p>
            <?php endif; ?>

            <?php if (!empty($entry["photo_id"])): ?>
                <a class="diary-photo" href="/photo.php?id=<?= (int) $entry["photo_id"] ?>" target="_blank" rel="noopener">
                    <img src="/photo.php?id=<?= (int) $entry["photo_id"] ?>" alt="Foto pada catatan <?= h(date_id($entry["diary_date"])) ?>" loading="lazy">
                </a>
            <?php endif; ?>

            <div class="chip-row">
                <span class="chip chip-static"><?= h(mood_label($mood)) ?> · <?= h(strtolower(INTENSITY_LABELS[$intensity] ?? "Sedang")) ?></span>
                <?php foreach ($tags as $t): ?>
                    <span class="chip chip-static"><?= h(TAGS[$t]) ?></span>
                <?php endforeach; ?>
            </div>

            <?php if ($actions): ?>
                <div class="diary-actions">
                    <a class="btn btn-outline btn-small" href="/diary.php?id=<?= (int) $entry["id"] ?>">Ubah</a>
                    <form method="POST" action="/diary_delete.php" data-confirm="Hapus catatan ini? Tindakan ini tidak bisa dibatalkan.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $entry["id"] ?>">
                        <input type="hidden" name="return" value="<?= h($returnTo) ?>">
                        <button class="btn btn-outline btn-small btn-quiet-danger" type="submit">Hapus</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

/** Hanya izinkan redirect balik ke path internal. */
function safe_return(string $path, string $fallback = "/dashboard.php"): string
{
    if ($path !== "" && $path[0] === "/" && !str_starts_with($path, "//") && !str_contains($path, "\\")) {
        return $path;
    }
    return $fallback;
}
