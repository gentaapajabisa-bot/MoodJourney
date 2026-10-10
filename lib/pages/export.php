<?php
$user = require_member();
$userId = (int) $user["id"];

$f = diary_filters($userId, $_GET);
$format = ($_GET["format"] ?? "csv") === "print" ? "print" : "csv";

$entries = db_all(
    "SELECT * FROM diary WHERE " . $f["where"] . " ORDER BY diary_date DESC, id DESC LIMIT 5000",
    $f["types"],
    $f["params"]
);

if ($format === "csv") {
    $name = "moodjourney-" . date("Y-m-d") . ".csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="' . $name . '"');

    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 (emoji, huruf Indonesia)
    fputcsv($out, ["Tanggal", "Mood", "Nama mood", "Skor (1-5)", "Intensitas (1-5)", "Aktivitas", "Tag", "Cerita"], ";");

    $csvSafe = function (string $v): string {
        // cegah formula injection saat dibuka di Excel/Sheets
        return $v !== "" && in_array($v[0], ["=", "+", "-", "@", "\t", "\r"], true) ? "'" . $v : $v;
    };

    foreach ($entries as $e) {
        $tagNames = array_map(fn($t) => TAGS[$t], tags_to_array($e["tags"] ?? ""));
        fputcsv($out, [
            $e["diary_date"],
            $e["mood"],
            mood_label($e["mood"]),
            (int) mood_score($e["mood"]),
            (int) $e["intensity"],
            $csvSafe((string) $e["activity"]),
            implode(", ", $tagNames),
            $csvSafe(str_replace(["\r\n", "\r"], "\n", (string) $e["note"])),
        ], ";");
    }
    fclose($out);
    exit;
}

/* Tampilan cetak: pengguna memilih "Simpan sebagai PDF" di dialog cetak browser. */
page_header("Catatan mood", ["nav" => "none", "body" => "print-view"]);
?>
<div class="container print-page">
    <div class="print-bar no-print">
        <button class="btn" type="button" data-print>Cetak / simpan PDF</button>
        <a class="btn btn-outline" href="/history.php">Kembali</a>
    </div>

    <h1>Catatan mood — <?= h($user["name"]) ?></h1>
    <p class="muted">Diekspor <?= h(date_id(date("Y-m-d"))) ?> · <?= count($entries) ?> catatan</p>

    <?php if (!$entries): ?>
        <p>Tidak ada catatan.</p>
    <?php endif; ?>

    <?php foreach ($entries as $e): ?>
        <article class="print-entry">
            <h3><?= h($e["mood"]) ?> <?= h(mood_label($e["mood"])) ?> <span class="muted">· <?= h(date_id($e["diary_date"], true)) ?></span></h3>
            <?php if ($e["activity"]): ?><p><strong><?= h($e["activity"]) ?></strong></p><?php endif; ?>
            <?php $tn = array_map(fn($t) => TAGS[$t], tags_to_array($e["tags"] ?? "")); ?>
            <?php if ($tn): ?><p class="muted"><?= h(implode(", ", $tn)) ?></p><?php endif; ?>
            <?php if ($e["note"]): ?><p><?= nl2br(h($e["note"])) ?></p><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php page_footer();
